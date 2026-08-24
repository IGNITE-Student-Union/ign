<?php
/**
 * Plugin Name: MyIGNITE Event Importer
 * Description: Imports events from MyIGNITE (CampusGroups) via the CampusGroups Data Export API, replacing the Event Aggregator ICS pipeline for The Events Calendar. <strong>Runs automatically once a day at 6:00 PM Toronto time.</strong> Manual run: <code>wp myignite sync-events</code> (add <code>--dry-run</code> to preview). Activity log: <code>wp-content/myignite-event-sync.log</code>. Note: CampusGroups' Data Export API is itself refreshed by a batch job on their side, so a brand-new event typically appears here within about a day, not instantly. Requires MYIGNITE_CG_SCHOOL_CODE / MYIGNITE_CG_API_SECRET in wp-content/mu-plugins.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------
// CONFIG
// -----------------------------------------------------------------------

define( 'MYIGNITE_IMPORTER_CRON_HOOK', 'myignite_event_sync_event' );
define( 'MYIGNITE_IMPORTER_LOG_PATH', WP_CONTENT_DIR . '/myignite-event-sync.log' );
// Safety ceiling on one run. Deliberately well above the real corpus (~550
// records) - when this was 200 a full sweep silently stopped a third of the
// way through, and because the run still advanced the incremental checkpoint,
// the untouched remainder was never looked at again. If this is ever hit, the
// checkpoint is now left alone so the next run re-covers the same window.
define( 'MYIGNITE_IMPORTER_MAX_PER_RUN', 2000 );

// The only CampusGroups groups whose events should ever be imported -
// confirmed live against the /data/v1/groups endpoint. Every other
// club/group is excluded even if it cohosts an event with one of these
// (see myignite_importer_event_should_import()). Id => display name,
// the name doubles as the tribe_events_cat term.
define( 'MYIGNITE_ALLOWED_GROUPS', array(
	35454 => 'IGNITE Services',
	35455 => 'IGNITE Events',
	35456 => 'IGNITE Clubs',
	35458 => 'IGNITE Advocacy',
	35461 => 'IGNITE Promotions', // NOT 35457 - that is a deactivated duplicate.
	35442 => 'IGNITE Governance',
) );

// Undocumented endpoint that backs the public MyIGNITE events list
// client-side - no auth required. This is the only source that carries
// each event's own photo; the Data Export API has no image field at all.
define( 'MYIGNITE_CG_EVENTS_LIST_API', 'https://my.ignitestudentunion.ca/mobile_ws/v17/mobile_events_list' );
define( 'MYIGNITE_CG_HOST', 'https://my.ignitestudentunion.ca' );


// -----------------------------------------------------------------------
// SETTINGS
//
// MYIGNITE_ALLOWED_GROUPS above is the *default* only. Once an admin saves
// the settings page these options take over, so the group list can be
// changed without editing code.
//
//   myignite_importer_groups          id => name of every group the admin
//                                     has added to the list (starts as the
//                                     six IGNITE groups).
//   myignite_importer_enabled_groups  ids currently ticked. Absent means
//                                     "all of them"; a saved empty array is
//                                     respected as a deliberate "none".
// -----------------------------------------------------------------------

define( 'MYIGNITE_OPT_GROUPS', 'myignite_importer_groups' );
define( 'MYIGNITE_OPT_ENABLED', 'myignite_importer_enabled_groups' );
define( 'MYIGNITE_OPT_LAST_ERROR', 'myignite_importer_last_error' );
define( 'MYIGNITE_TZ', 'America/Toronto' );

/**
 * Every group known to the settings page, ticked or not.
 *
 * @return array<int,string> id => display name.
 */
function myignite_importer_known_groups() {
	$stored = get_option( MYIGNITE_OPT_GROUPS, false );

	if ( ! is_array( $stored ) || ! $stored ) {
		return MYIGNITE_ALLOWED_GROUPS; // Never saved yet, or unreadable.
	}

	$clean = array();
	foreach ( $stored as $id => $name ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$clean[ $id ] = sanitize_text_field( (string) $name );
		}
	}

	return $clean ? $clean : MYIGNITE_ALLOWED_GROUPS;
}

/**
 * The groups events are actually imported from right now.
 *
 * A saved-but-empty list is honoured (an admin may deliberately pause all
 * importing); only a missing or corrupt option falls back to "all known".
 *
 * @return array<int,string> id => display name.
 */
function myignite_importer_enabled_groups() {
	$known   = myignite_importer_known_groups();
	$enabled = get_option( MYIGNITE_OPT_ENABLED, false );

	if ( false === $enabled || ! is_array( $enabled ) ) {
		return $known;
	}

	$out = array();
	foreach ( $enabled as $id ) {
		$id = (int) $id;
		if ( isset( $known[ $id ] ) ) {
			$out[ $id ] = $known[ $id ];
		}
	}

	return $out;
}

/**
 * Converts a Toronto wall-clock date/time into a UTC timestamp.
 *
 * Admins only ever enter Toronto local time; UTC exists solely at the API
 * boundary. DateTimeZone carries the IANA database, so the EDT/EST switch
 * is applied automatically for whatever date is passed - there is nothing
 * to adjust twice a year.
 *
 * @param string $date 'Y-m-d'.
 * @param string $time 'H:i' (defaults to midnight when blank).
 * @return int|null Unix timestamp, or null if the date was unparseable.
 */
function myignite_importer_toronto_to_timestamp( $date, $time = '00:00' ) {
	$date = trim( (string) $date );
	if ( '' === $date ) {
		return null;
	}
	$time = trim( (string) $time );
	if ( '' === $time ) {
		$time = '00:00';
	}

	try {
		$dt = new DateTime( $date . ' ' . $time, new DateTimeZone( MYIGNITE_TZ ) );
	} catch ( Exception $e ) {
		return null;
	}

	return $dt->getTimestamp();
}

/**
 * Formats a UTC timestamp for display in Toronto local time.
 *
 * @param int    $timestamp Unix timestamp.
 * @param string $format    PHP date format.
 * @return string
 */
function myignite_importer_toronto_format( $timestamp, $format = 'M j, Y g:i A T' ) {
	try {
		$dt = new DateTime( '@' . (int) $timestamp );
		$dt->setTimezone( new DateTimeZone( MYIGNITE_TZ ) );
		return $dt->format( $format );
	} catch ( Exception $e ) {
		return '';
	}
}

/**
 * Records the most recent import failure so the admin notice can surface it.
 * Cleared by myignite_importer_clear_error() after any successful run, so a
 * fixed problem stops nagging on its own.
 *
 * @param string $code    Short machine-ish code, e.g. 'auth'.
 * @param string $message Human-readable detail.
 */
function myignite_importer_record_error( $code, $message ) {
	update_option( MYIGNITE_OPT_LAST_ERROR, array(
		'code'    => (string) $code,
		'message' => (string) $message,
		'time'    => time(),
	), false );
}

function myignite_importer_clear_error() {
	delete_option( MYIGNITE_OPT_LAST_ERROR );
}


// -----------------------------------------------------------------------
// LOGGING
// -----------------------------------------------------------------------

function myignite_importer_log( $message ) {
	$line = sprintf( '[%s] %s' . PHP_EOL, current_time( 'Y-m-d H:i:s' ), $message );
	file_put_contents( MYIGNITE_IMPORTER_LOG_PATH, $line, FILE_APPEND | LOCK_EX );
}

add_action( 'init', 'myignite_importer_block_log_access' );
function myignite_importer_block_log_access() {
	if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], 'myignite-event-sync.log' ) ) {
		wp_die( 'Not found.', '', array( 'response' => 404 ) );
	}
}


// -----------------------------------------------------------------------
// CAMPUSGROUPS DATA EXPORT API (title / dates / location / description)
// -----------------------------------------------------------------------

/**
 * @return array{0:string,1:string}|false [school code, API secret], or
 *                                         false if not configured.
 */
function myignite_importer_cg_credentials() {
	if ( ! defined( 'MYIGNITE_CG_SCHOOL_CODE' ) || ! defined( 'MYIGNITE_CG_API_SECRET' )
		|| ! MYIGNITE_CG_SCHOOL_CODE || ! MYIGNITE_CG_API_SECRET ) {
		return false;
	}
	return array( MYIGNITE_CG_SCHOOL_CODE, MYIGNITE_CG_API_SECRET );
}

/**
 * Fetches every page of a Data Export API resource for a given
 * updatedOn window (this API filters by when a record was last
 * touched, not by event start date - so a brand-new event dated months
 * out still shows up immediately).
 *
 * The API is asynchronous: POST registers a query and returns a
 * queryId; GET with that queryId returns HTTP 202 ("still running")
 * until results are ready, then 200 with a page of Results and an
 * optional NextToken for the next page. Confirmed live that a single
 * fixed delay is not reliably enough, so this polls.
 *
 * @return array|WP_Error Flat array of result rows, or WP_Error.
 */
function myignite_importer_cg_data_api_fetch_all( $resource, $updated_start, $updated_end ) {
	$creds = myignite_importer_cg_credentials();
	if ( ! $creds ) {
		return new WP_Error( 'myignite_missing_credentials', 'MYIGNITE_CG_SCHOOL_CODE / MYIGNITE_CG_API_SECRET are not defined.' );
	}
	list( $school, $secret ) = $creds;

	$base    = sprintf( 'https://%s.service.campusgroups.com/data/v1/%s', $school, $resource );
	$headers = array(
		'X-CG-API-Secret' => $secret,
		'X-CG-School'     => $school,
	);

	$post_url      = add_query_arg( array( 'updatedStart' => $updated_start, 'updatedEnd' => $updated_end ), $base );
	$post_response = wp_remote_post( $post_url, array( 'headers' => $headers, 'timeout' => 20 ) );

	if ( is_wp_error( $post_response ) ) {
		return $post_response;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $post_response ) ) {
		return new WP_Error( 'myignite_cg_query_failed', "Data Export API POST /{$resource} returned HTTP " . wp_remote_retrieve_response_code( $post_response ) . ': ' . wp_remote_retrieve_body( $post_response ) );
	}

	$post_data = json_decode( wp_remote_retrieve_body( $post_response ), true );
	$query_id  = $post_data['queryId'] ?? null;
	if ( ! $query_id ) {
		return new WP_Error( 'myignite_cg_no_query_id', "Data Export API POST /{$resource} did not return a queryId." );
	}

	$results = array();
	$token   = null;

	for ( $page = 0; $page < 50; $page++ ) {
		$page_args = array( 'queryId' => $query_id, 'size' => 999 );
		if ( $token ) {
			$page_args['token'] = $token;
		}
		$get_url = add_query_arg( $page_args, $base );

		$page_data = null;
		for ( $poll = 0; $poll < 15; $poll++ ) {
			sleep( 0 === $poll ? 2 : 3 );
			$get_response = wp_remote_get( $get_url, array( 'headers' => $headers, 'timeout' => 20 ) );
			if ( is_wp_error( $get_response ) ) {
				return $get_response;
			}
			$code = (int) wp_remote_retrieve_response_code( $get_response );
			if ( 202 === $code ) {
				continue;
			}
			if ( 200 !== $code ) {
				return new WP_Error( 'myignite_cg_fetch_failed', "Data Export API GET /{$resource} returned HTTP {$code}: " . wp_remote_retrieve_body( $get_response ) );
			}
			$page_data = json_decode( wp_remote_retrieve_body( $get_response ), true );
			break;
		}

		if ( null === $page_data ) {
			return new WP_Error( 'myignite_cg_query_timeout', "Data Export API query for /{$resource} never finished running." );
		}

		if ( ! empty( $page_data['Results'] ) ) {
			$results = array_merge( $results, $page_data['Results'] );
		}
		$token = $page_data['NextToken'] ?? null;
		if ( ! $token ) {
			break;
		}
	}

	return $results;
}


// -----------------------------------------------------------------------
// CAMPUSGROUPS EVENTS-LIST API (image only - Data Export API has none)
// -----------------------------------------------------------------------

/**
 * @return array<int,string> Map of CampusGroups event ID => full photo URL.
 */
function myignite_importer_fetch_event_photos() {
	$response = wp_remote_get(
		add_query_arg( array( 'range' => 0, 'limit' => 500 ), MYIGNITE_CG_EVENTS_LIST_API ),
		array( 'timeout' => 15 )
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		myignite_importer_log( 'CampusGroups events-list API request failed - events will import without images this run.' );
		return array();
	}

	$rows = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$photos = array();
	foreach ( $rows as $row ) {
		if ( empty( $row['fields'] ) ) {
			continue;
		}
		$field_names = explode( ',', $row['fields'] );
		$id_index    = array_search( 'eventId', $field_names, true );
		$photo_index = array_search( 'eventPicture', $field_names, true );
		if ( false === $id_index || false === $photo_index ) {
			continue;
		}
		$event_id  = $row[ 'p' . $id_index ] ?? '';
		$photo_url = $row[ 'p' . $photo_index ] ?? '';
		if ( ! ctype_digit( (string) $event_id ) || empty( $photo_url ) ) {
			continue;
		}
		$photos[ (int) $event_id ] = MYIGNITE_CG_HOST . $photo_url;
	}

	return $photos;
}


// -----------------------------------------------------------------------
// MATCHING EXISTING POSTS
//
// Deliberately raw $wpdb, not WP_Query: The Events Calendar's Custom
// Tables V1 layer substitutes real post IDs with "occurrence" pseudo-IDs
// on WP_Query results for tribe_events (confirmed elsewhere in this
// codebase, see wp myignite clean-descriptions), which silently no-ops
// any wp_update_post()/update_post_meta() call made against them.
// -----------------------------------------------------------------------

function myignite_importer_find_post_by_cg_id( $cg_event_id ) {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
		 WHERE p.post_type = 'tribe_events'
		   AND pm.meta_key = '_myignite_cg_event_id'
		   AND pm.meta_value = %s
		   AND p.post_status != 'trash'
		 LIMIT 1",
		$cg_event_id
	) );
}

/**
 * One-time backfill safety net: matches a pre-existing event (imported
 * by the old Aggregator/ICS pipeline, so it has no
 * _myignite_cg_event_id yet) by title + start date, so the first
 * automated run adopts it instead of creating a duplicate.
 */
function myignite_importer_find_backfill_post_id( $title, $start_date_local ) {
	global $wpdb;
	$date_prefix = substr( $start_date_local, 0, 10 );
	return $wpdb->get_var( $wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} cg ON cg.post_id = p.ID AND cg.meta_key = '_myignite_cg_event_id'
		 INNER JOIN {$wpdb->postmeta} sd ON sd.post_id = p.ID AND sd.meta_key = '_EventStartDate'
		 WHERE p.post_type = 'tribe_events'
		   AND p.post_title = %s
		   AND p.post_status != 'trash'
		   AND cg.meta_id IS NULL
		   AND sd.meta_value LIKE %s
		 LIMIT 1",
		$title,
		$wpdb->esc_like( $date_prefix ) . '%'
	) );
}


// -----------------------------------------------------------------------
// FIELD MAPPING
// -----------------------------------------------------------------------

/**
 * Has the event gone away on CampusGroups itself?
 *
 * These are the ONLY conditions that remove an event already published on the
 * website. They all mean the same thing: the source of truth no longer has
 * this as a live, published event, so leaving it on the public site would
 * advertise something that is cancelled or was never meant to go out.
 *
 * @return string Human-readable reason, or '' if the event is still live.
 */
function myignite_importer_event_removed_at_source( $event ) {
	if ( ! empty( $event['deleted'] ) ) {
		return 'deleted on CampusGroups';
	}
	if ( ! empty( $event['draft'] ) ) {
		return 'moved back to draft on CampusGroups';
	}
	if ( ( $event['approvalStatus'] ?? '' ) !== 'Approved' ) {
		return 'no longer approved on CampusGroups (' . ( $event['approvalStatus'] ?? 'unknown status' ) . ')';
	}
	return '';
}

/**
 * Do we choose to publish this event?
 *
 * Failing these means "do not import, and stop updating" - it does NOT mean
 * "remove what is already on the website". The distinction matters: these are
 * OUR filters, and a settings change on our side should never silently delete
 * content an editor can see. Only myignite_importer_event_removed_at_source()
 * above removes anything.
 *
 * @return string Reason it is filtered out, or '' if it should be imported.
 */
function myignite_importer_event_filtered_out( $event ) {
	$group_id = (int) ( $event['groupId'] ?? 0 );
	// Reads the saved settings, falling back to MYIGNITE_ALLOWED_GROUPS
	// until an admin has saved the settings page for the first time.
	if ( ! array_key_exists( $group_id, myignite_importer_enabled_groups() ) ) {
		return 'its group is not ticked in the importer settings';
	}

	// Only publish events CampusGroups itself shows publicly.
	//
	// Confirmed against real data: every event with
	// whoCanSeeEventOnCalendar = "No one" is absent from the public events
	// list (the mobile_ws feed), which is also our only source of images -
	// so these events could never have a featured image, and they are not
	// meant for a public audience in the first place (they also carry
	// privacyLevel = "Some IGNITE Student Union users only").
	//
	// FAILS OPEN on purpose. If the key is missing entirely - because
	// CampusGroups renamed or dropped it - we treat the event as visible
	// rather than hidden. Only an explicit, non-"Everyone" value excludes.
	if ( array_key_exists( 'whoCanSeeEventOnCalendar', $event )
		&& 'Everyone' !== $event['whoCanSeeEventOnCalendar'] ) {
		return 'not publicly visible (See Event on Calendar = "' . $event['whoCanSeeEventOnCalendar'] . '")';
	}

	return '';
}

/**
 * Should this event be created/updated on the website? Both gates must pass.
 */
function myignite_importer_event_should_import( $event ) {
	return '' === myignite_importer_event_removed_at_source( $event )
		&& '' === myignite_importer_event_filtered_out( $event );
}

/**
 * Organizers use shortDescription/description inconsistently - some
 * events have real content only in shortDescription with description
 * empty, and vice versa. content prefers the longer `description`;
 * excerpt prefers the (usually shorter, purpose-written) shortDescription,
 * trimmed since it is not always actually short.
 */
function myignite_importer_build_event_fields( $event, $image_url ) {
	$name        = trim( (string) ( $event['name'] ?? '' ) );
	$short_desc  = trim( (string) ( $event['shortDescription'] ?? '' ) );
	$description = trim( (string) ( $event['description'] ?? '' ) );

	$content = '' !== $description ? $description : $short_desc;
	$excerpt_source = '' !== $short_desc ? $short_desc : $description;
	$excerpt = '' !== $excerpt_source ? wp_trim_words( wp_strip_all_tags( $excerpt_source ), 30 ) : '';

	$tz = wp_timezone();
	$start_utc  = new DateTime( $event['startDate'], new DateTimeZone( 'UTC' ) );
	$end_source = ! empty( $event['endDate'] ) ? $event['endDate'] : $event['startDate'];
	$end_utc    = new DateTime( $end_source, new DateTimeZone( 'UTC' ) );

	$start_local = ( clone $start_utc )->setTimezone( $tz );
	$end_local   = ( clone $end_utc )->setTimezone( $tz );

	// locationType wins when it says the event is online. Confirmed against
	// real data (event 375536, "New Club Application Deadline"): CampusGroups
	// lets an organizer set locationType = "Online Only" while `address` still
	// holds an unrelated on-campus room left over from before the event was
	// switched to virtual - address is then not authoritative for where the
	// event actually is. locationName is left as the one exception, since an
	// organizer could legitimately use it for a Zoom link/description on an
	// online event.
	$location_type = trim( (string) ( $event['locationType'] ?? '' ) );
	$location_name = trim( (string) ( $event['locationName'] ?? '' ) );
	$is_online     = false !== stripos( $location_type, 'online' );

	if ( '' !== $location_name ) {
		$location = $location_name;
	} elseif ( $is_online ) {
		$location = 'Online';
	} else {
		$location = trim( (string) ( $event['address'] ?? '' ) );
	}

	$tags = array();
	foreach ( (array) ( $event['tags'] ?? array() ) as $tag ) {
		if ( ! empty( $tag['name'] ) ) {
			$tags[] = $tag['name'];
		}
	}

	$group_id = (int) ( $event['groupId'] ?? 0 );

	$fields = array(
		'title'       => $name,
		'content'     => $content,
		'excerpt'     => $excerpt,
		'start_local' => $start_local->format( 'Y-m-d H:i:s' ),
		'end_local'   => $end_local->format( 'Y-m-d H:i:s' ),
		'start_utc'   => $start_utc->format( 'Y-m-d H:i:s' ),
		'end_utc'     => $end_utc->format( 'Y-m-d H:i:s' ),
		'timezone'    => $tz->getName(),
		'location'    => $location,
		'website'     => MYIGNITE_CG_HOST . '/rsvp?id=' . (int) $event['id'],
		// known_groups(), not the constant: a group added via the settings
		// page must resolve to its name here too, or its events would import
		// with a blank category/organizer.
		'category'    => myignite_importer_known_groups()[ $group_id ] ?? '',
		'event_type'  => trim( (string) ( $event['type']['value'] ?? '' ) ),
		'tags'        => $tags,
		'image_url'   => $image_url,
	);

	$fields['hash'] = md5( (string) wp_json_encode( $fields ) );

	return $fields;
}


// -----------------------------------------------------------------------
// IMAGE
// -----------------------------------------------------------------------

/**
 * Builds the list of image URLs to try, best quality first.
 *
 * CampusGroups' events-list API always reports the "r2_" variant of an
 * event photo, which is a downscaled 640x320 copy - noticeably soft once the
 * theme renders it at full width. The same file exists at other prefixes,
 * confirmed by measuring a real event photo:
 *
 *     r1_<name>   320x160    13 KB
 *     r2_<name>   640x320    43 KB   <- what the API hands us
 *     r3_<name>  1280x640   193 KB
 *        <name>  1280x640   223 KB   <- original upload, best quality
 *
 * So we strip the prefix to reach the original, keep r3_ as a same-resolution
 * fallback, and finally fall back to the URL the API actually gave us, which
 * is guaranteed to exist. Only the filename is rewritten - the directory can
 * itself contain "r2_"-like segments and must not be touched.
 *
 * @param string $api_url Full URL as reported by the events-list API.
 * @return string[] Candidate URLs, highest quality first.
 */
function myignite_importer_image_url_candidates( $api_url ) {
	$path = wp_parse_url( $api_url, PHP_URL_PATH );
	if ( ! $path ) {
		return array( $api_url );
	}

	$dir  = dirname( $path );
	$file = basename( $path );

	if ( ! preg_match( '/^r(\d+)_(.+)$/', $file, $m ) ) {
		return array( $api_url ); // Unrecognised shape - use as-is.
	}

	$bare   = $m[2];
	$scheme = wp_parse_url( $api_url, PHP_URL_SCHEME ) ?: 'https';
	$host   = wp_parse_url( $api_url, PHP_URL_HOST );
	$prefix = $scheme . '://' . $host . $dir . '/';

	$candidates = array(
		$prefix . $bare,          // original upload
		$prefix . 'r3_' . $bare,  // largest generated variant
		$api_url,                 // whatever the API said (always exists)
	);

	return array_values( array_unique( $candidates ) );
}

function myignite_importer_maybe_update_featured_image( $post_id, $image_url ) {
	if ( empty( $image_url ) ) {
		return 'no image available from CampusGroups';
	}

	$stored_url = get_post_meta( $post_id, '_myignite_source_image_url', true );
	if ( $stored_url === $image_url && has_post_thumbnail( $post_id ) ) {
		return 'unchanged, skipped re-download';
	}

	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}
	if ( ! function_exists( 'download_url' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$ext      = strtolower( pathinfo( strtok( $image_url, '?' ), PATHINFO_EXTENSION ) ) ?: 'jpg';
	$filename = sanitize_title( get_the_title( $post_id ) ) . '_myignite_import.' . $ext;

	// Walk candidates best-quality-first and take the first that downloads.
	// The final candidate is the URL the API gave us, so this can only fail
	// if the image is genuinely unreachable.
	$tmp        = null;
	$downloaded = '';
	$errors     = array();

	foreach ( myignite_importer_image_url_candidates( $image_url ) as $candidate ) {
		$attempt = download_url( $candidate );
		if ( ! is_wp_error( $attempt ) ) {
			$tmp        = $attempt;
			$downloaded = $candidate;
			break;
		}
		$errors[] = basename( $candidate ) . ': ' . $attempt->get_error_message();
	}

	if ( null === $tmp ) {
		return 'ERROR downloading image - ' . implode( ' | ', $errors );
	}

	$attachment_id = media_handle_sideload( array( 'name' => $filename, 'tmp_name' => $tmp ), $post_id );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp );
		return 'ERROR sideloading image - ' . $attachment_id->get_error_message();
	}

	set_post_thumbnail( $post_id, $attachment_id );
	// Store the API-reported URL for change detection (that is what the next
	// run will compare against) and the resolved one for traceability.
	update_post_meta( $post_id, '_myignite_source_image_url', $image_url );
	update_post_meta( $attachment_id, '_myignite_source_url', $downloaded );

	return ( $downloaded === $image_url ) ? 'updated' : 'updated (upgraded to ' . basename( $downloaded ) . ')';
}


// -----------------------------------------------------------------------
// PER-EVENT SYNC
// -----------------------------------------------------------------------

function myignite_importer_sync_single_event( $event, $photos, $dry_run ) {
	$cg_id = (int) ( $event['id'] ?? 0 );
	if ( ! $cg_id ) {
		myignite_importer_log( 'Skipped a record with no id - unparseable response row.' );
		return 'error';
	}

	$existing_id = myignite_importer_find_post_by_cg_id( $cg_id );

	// ------------------------------------------------------------------
	// Two distinct outcomes, deliberately kept apart.
	// ------------------------------------------------------------------

	// 1. Gone at the source (deleted / back to draft / approval revoked):
	//    the event is cancelled or unpublished on CampusGroups, so it must
	//    not keep advertising itself on the public website. Trashed, never
	//    hard-deleted, and the log names the exact reason - a removal with
	//    no stated cause is the silent behaviour we left Aggregator to escape.
	$removed_why = myignite_importer_event_removed_at_source( $event );
	if ( '' !== $removed_why ) {
		if ( $existing_id ) {
			myignite_importer_log( "Event {$cg_id} (post {$existing_id}): trashed - {$removed_why}." );
			if ( ! $dry_run ) {
				wp_trash_post( $existing_id );
			}
			return 'trashed';
		}
		return 'skipped';
	}

	// 2. Filtered out by OUR settings (group not ticked, not publicly
	//    visible): we simply do not manage this event. Anything already on
	//    the website is left exactly as it is - published, untouched, and
	//    no longer updated. Our own configuration must never delete content
	//    an editor can see; removing it stays a human decision.
	$filtered_why = myignite_importer_event_filtered_out( $event );
	if ( '' !== $filtered_why ) {
		if ( $existing_id ) {
			myignite_importer_log( "Event {$cg_id} (post {$existing_id}): left as-is, no longer managed - {$filtered_why}." );
		}
		return 'skipped';
	}

	$photo_url = $photos[ $cg_id ] ?? '';
	$fields    = myignite_importer_build_event_fields( $event, $photo_url );

	// Adoption by title + start date, for events not yet tagged with a
	// CampusGroups ID. This is DELIBERATE and load-bearing - do not remove it
	// as a "safety" measure (it was removed once for exactly that reason and
	// had to be restored).
	//
	// The workflow it supports: CampusGroups' Data Export API is fed by a
	// batch job on their side, so an event created there today generally is
	// not visible to this importer until tomorrow. When something needs to be
	// live on the website immediately, staff create it by hand in wp-admin
	// using the SAME title and start time as the CampusGroups event. On the
	// next sync it is adopted rather than duplicated, and from then on
	// CampusGroups is the source of truth for it.
	//
	// The tradeoff, accepted knowingly: a hand-made event that coincidentally
	// shares BOTH an exact title and an exact start time with a CampusGroups
	// event will also be adopted, and its content overwritten by the
	// CampusGroups version. Anything differing in either field is untouched.
	// Every adoption is logged below, so this is always traceable after the
	// fact.
	if ( ! $existing_id ) {
		$existing_id = myignite_importer_find_backfill_post_id( $fields['title'], $fields['start_local'] );
		if ( $existing_id ) {
			myignite_importer_log( "Event {$cg_id}: matched pre-existing post {$existing_id} by title+date (not yet tagged with a CampusGroups ID) - adopting it instead of creating a duplicate." );
		}
	}

	if ( $existing_id ) {
		$stored_hash = get_post_meta( $existing_id, '_myignite_source_hash', true );
		if ( $stored_hash === $fields['hash'] ) {
			// Core event data is unchanged, but the image comes from a
			// separate, narrower "upcoming events" feed that a far-future
			// event may not have been listed in yet when first imported -
			// retry it independently of the hash so a photo that becomes
			// available later still gets picked up, instead of being
			// skipped forever alongside the unchanged core data.
			if ( ! $dry_run && $photo_url && ! get_post_thumbnail_id( $existing_id ) ) {
				$image_status = myignite_importer_maybe_update_featured_image( $existing_id, $photo_url );
				myignite_importer_log( "Event {$cg_id} (post {$existing_id}): skipped - no change since last sync. Image backfill: {$image_status}." );
			} else {
				myignite_importer_log( "Event {$cg_id} (post {$existing_id}): skipped - no change since last sync." );
			}
			return 'skipped';
		}
	}

	if ( $dry_run ) {
		myignite_importer_log( "Event {$cg_id}" . ( $existing_id ? " (post {$existing_id})" : '' ) . ': DRY RUN - would ' . ( $existing_id ? 'update' : 'create' ) . " \"{$fields['title']}\"." );
		return $existing_id ? 'updated' : 'created';
	}

	$args = array(
		'post_title'     => $fields['title'],
		'post_content'   => $fields['content'],
		'post_excerpt'   => $fields['excerpt'],
		'post_status'    => 'publish',
		'EventStartDate' => substr( $fields['start_local'], 0, 10 ),
		'EventStartTime' => substr( $fields['start_local'], 11 ),
		'EventEndDate'   => substr( $fields['end_local'], 0, 10 ),
		'EventEndTime'   => substr( $fields['end_local'], 11 ),
		'EventTimezone'  => $fields['timezone'],
		'EventURL'       => $fields['website'],
	);

	$is_new = ! $existing_id;
	// No 'Venue' key passed - see _myignite_venue_name below. Passing
	// one here is what makes TEC auto-create a linked tribe_venue post,
	// which the site's display templates deliberately do not use.
	$post_id = $is_new ? tribe_create_event( $args ) : tribe_update_event( $existing_id, $args );

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		$msg = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'tribe_create_event()/tribe_update_event() returned no post ID';
		myignite_importer_log( "Event {$cg_id}: ERROR - {$msg}" );
		return 'error';
	}

	update_post_meta( $post_id, '_myignite_cg_event_id', $cg_id );
	update_post_meta( $post_id, '_myignite_venue_name', $fields['location'] );
	// Clears any tribe_venue link left over from an event adopted from the old
	// Aggregator/ICS pipeline. The display templates (EventHero.php,
	// card-tribe_events.php, etc.) prefer a linked _EventVenueID over
	// _myignite_venue_name, so a stale link here would keep showing the old
	// venue post's title forever, even though the field above is being kept
	// current from CampusGroups on every sync.
	delete_post_meta( $post_id, '_EventVenueID' );
	// Same source as the category term - the CampusGroups group name is
	// the closest equivalent to the ORGANIZER;CN=... field the old ICS
	// feed carried, and this is the meta key EventHero.php /
	// card-tribe_events.php already fall back to for organizer display.
	update_post_meta( $post_id, '_myignite_organizer_names', $fields['category'] );
	update_post_meta( $post_id, '_myignite_source_hash', $fields['hash'] );

	$categories = array_filter( array( $fields['category'], $fields['event_type'] ) );
	if ( $categories ) {
		wp_set_object_terms( $post_id, $categories, 'tribe_events_cat', false );
	}
	if ( $fields['tags'] ) {
		wp_set_object_terms( $post_id, $fields['tags'], 'post_tag', false );
	}

	$image_status = myignite_importer_maybe_update_featured_image( $post_id, $photo_url );

	myignite_importer_log( "Event {$cg_id} (post {$post_id}): " . ( $is_new ? 'created' : 'updated' ) . ". Image: {$image_status}." );

	return $is_new ? 'created' : 'updated';
}


// -----------------------------------------------------------------------
// MAIN ENTRY - called by WP-Cron and `wp myignite sync-events`
// -----------------------------------------------------------------------

function myignite_sync_events( $options = array() ) {
	$dry_run = ! empty( $options['dry_run'] );

	// Which event dates to import. Defaults to today onward, i.e. upcoming
	// only - past events are never created or modified. Evaluated at run time,
	// so "today" advances by itself with no scheduled maintenance.
	$inclusive = ! array_key_exists( 'inclusive', $options ) || ! empty( $options['inclusive'] );
	$start_min = array_key_exists( 'event_start_min', $options ) && null !== $options['event_start_min']
		? (int) $options['event_start_min']
		: myignite_importer_toronto_to_timestamp( myignite_importer_toronto_format( time(), 'Y-m-d' ), '00:00' );
	$start_max = ! empty( $options['event_start_max'] ) ? (int) $options['event_start_max'] : 0;

	myignite_importer_log( sprintf(
		'Sync run started.%s Event window: %s to %s (%s).',
		$dry_run ? ' (DRY RUN - no changes will be written)' : '',
		myignite_importer_toronto_format( $start_min ),
		$start_max ? myignite_importer_toronto_format( $start_max ) : 'all upcoming',
		$inclusive ? 'inclusive' : 'exclusive'
	) );

	if ( ! myignite_importer_cg_credentials() ) {
		$msg = 'MYIGNITE_CG_SCHOOL_CODE / MYIGNITE_CG_API_SECRET are not defined (see wp-content/mu-plugins/myignite-secrets.php).';
		myignite_importer_log( 'ERROR - ' . $msg . ' Aborting.' );
		myignite_importer_record_error( 'auth', $msg );
		return false;
	}

	// The API window is on updatedOn, which is NOT the event date. An event
	// happening next month may not have been edited in a year, so an
	// incremental updatedOn window would never surface it. We always pull a
	// wide window and do the real filtering on event date below. This also
	// retires the incremental checkpoint that previously let a truncated run
	// skip events permanently.
	$updated_end   = gmdate( 'Y-m-d\TH:i:s\Z' );
	$updated_start = ! empty( $options['since'] )
		? $options['since']
		: gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-2 years' ) );

	myignite_importer_log( "Querying CampusGroups for events updated between {$updated_start} and {$updated_end}." );

	$events = myignite_importer_cg_data_api_fetch_all( 'events', $updated_start, $updated_end );
	if ( is_wp_error( $events ) ) {
		$msg = $events->get_error_message();
		myignite_importer_log( 'ERROR fetching events - ' . $msg );
		// Abort BEFORE touching any post. A failed or empty response must
		// never be read as "CampusGroups deleted everything", which would
		// trash the calendar on a transient outage or a rotated API secret.
		myignite_importer_record_error(
			( false !== stripos( $msg, '401' ) || false !== stripos( $msg, '403' ) ) ? 'auth' : 'fetch',
			$msg
		);
		return false;
	}
	myignite_importer_log( 'CampusGroups returned ' . count( $events ) . ' updated event record(s) in this window (before group filtering).' );

	// Event-date filter. Anything outside the window is dropped here, so it is
	// never created, never updated, and never trashed - an event that simply
	// aged into the past is left exactly as it is.
	$before = count( $events );
	$events = array_values( array_filter( $events, static function ( $event ) use ( $start_min, $start_max, $inclusive ) {
		$start = isset( $event['startDate'] ) ? strtotime( $event['startDate'] ) : false;
		if ( ! $start ) {
			return false;
		}
		if ( $inclusive ) {
			if ( $start < $start_min ) { return false; }
			if ( $start_max && $start > $start_max ) { return false; }
		} else {
			if ( $start <= $start_min ) { return false; }
			if ( $start_max && $start >= $start_max ) { return false; }
		}
		return true;
	} ) );
	myignite_importer_log( sprintf(
		'%d of %d record(s) fall inside the event window; %d outside it were left untouched.',
		count( $events ), $before, $before - count( $events )
	) );

	$photos = myignite_importer_fetch_event_photos();
	myignite_importer_log( 'CampusGroups events-list API returned photos for ' . count( $photos ) . ' event(s).' );

	$counts    = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'trashed' => 0, 'error' => 0 );
	$processed = 0;

	$hit_cap = false;

	foreach ( $events as $event ) {
		if ( $processed >= MYIGNITE_IMPORTER_MAX_PER_RUN ) {
			$hit_cap = true;
			myignite_importer_log( 'WARNING: hit MYIGNITE_IMPORTER_MAX_PER_RUN (' . MYIGNITE_IMPORTER_MAX_PER_RUN . ') with ' . ( count( $events ) - $processed ) . ' record(s) still unexamined. The sync checkpoint will NOT be advanced, so the next run re-covers this same window rather than skipping past them.' );
			break;
		}
		$processed++;
		$result = myignite_importer_sync_single_event( $event, $photos, $dry_run );
		if ( isset( $counts[ $result ] ) ) {
			$counts[ $result ]++;
		}
	}

	// Only a normal, unscoped sweep advances the checkpoint - a custom
	// --since run must not, or the next regular cron cycle would start
	// its window from here and skip anything that changed in between on
	// events this scoped run never looked at.
	if ( ! $dry_run ) {
		// Kept for display on the settings screen only; no longer used to
		// window the API query.
		update_option( 'myignite_last_event_sync', current_time( 'mysql', true ) );
	}

	// A clean run clears any previous failure, so a fixed problem stops
	// nagging without anyone dismissing the notice by hand.
	if ( ! $hit_cap && 0 === $counts['error'] ) {
		myignite_importer_clear_error();
	}

	myignite_importer_log( sprintf(
		'Sync run finished%s. Processed: %d, Created: %d, Updated: %d, Skipped: %d, Trashed: %d, Errors: %d.',
		$dry_run ? ' (DRY RUN)' : '',
		$processed,
		$counts['created'],
		$counts['updated'],
		$counts['skipped'],
		$counts['trashed'],
		$counts['error']
	) );

	return $counts;
}


// -----------------------------------------------------------------------
// WP-CRON: once daily at 6:00 PM Toronto
//
// Deliberately once a day, not more: CampusGroups' Data Export API is fed by
// a batch job on their side, so polling it more often returns the same data
// and gains nothing. 6:00 PM local was chosen so a day's edits are picked up
// after business hours.
//
// Note this is 6:00 PM *Toronto*, pinned to America/Toronto explicitly rather
// than the site's timezone setting, so it does not silently drift by an hour
// at daylight-saving changeovers.
// -----------------------------------------------------------------------

add_filter( 'cron_schedules', 'myignite_importer_cron_schedules' );
function myignite_importer_cron_schedules( $schedules ) {
	$schedules['myignite_daily_6pm'] = array(
		'interval' => DAY_IN_SECONDS,
		'display'  => 'Once daily at 6:00 PM Toronto (MyIGNITE event import)',
	);
	return $schedules;
}

/**
 * Timestamp of the next 6:00 PM in Toronto. Used as the cron's anchor so the
 * daily interval lands at that wall-clock time rather than 24h after whenever
 * the plugin happened to be activated.
 *
 * @return int Unix timestamp.
 */
function myignite_importer_next_6pm_toronto() {
	$tz   = new DateTimeZone( 'America/Toronto' );
	$now  = new DateTime( 'now', $tz );
	$next = new DateTime( 'today 18:00', $tz );
	if ( $next <= $now ) {
		$next->modify( '+1 day' );
	}
	return $next->getTimestamp();
}

register_activation_hook( __FILE__, 'myignite_importer_activate' );
function myignite_importer_activate() {
	if ( ! wp_next_scheduled( MYIGNITE_IMPORTER_CRON_HOOK ) ) {
		wp_schedule_event( myignite_importer_next_6pm_toronto(), 'myignite_daily_6pm', MYIGNITE_IMPORTER_CRON_HOOK );
	}
}

/**
 * Moves an already-scheduled sync onto the current schedule/anchor if it is
 * still running on an older one (e.g. the original every-8-hours schedule).
 * Without this, changing the constants above would have no effect on an
 * install where the event was already registered - WP-Cron keeps whatever
 * interval it was first scheduled with.
 */
add_action( 'init', 'myignite_importer_maybe_reschedule' );
function myignite_importer_maybe_reschedule() {
	$scheduled = wp_get_scheduled_event( MYIGNITE_IMPORTER_CRON_HOOK );

	if ( $scheduled && 'myignite_daily_6pm' === $scheduled->schedule ) {
		return; // Already on the intended schedule.
	}

	if ( $scheduled ) {
		wp_unschedule_event( $scheduled->timestamp, MYIGNITE_IMPORTER_CRON_HOOK );
	}

	wp_schedule_event( myignite_importer_next_6pm_toronto(), 'myignite_daily_6pm', MYIGNITE_IMPORTER_CRON_HOOK );
}

register_deactivation_hook( __FILE__, 'myignite_importer_deactivate' );
function myignite_importer_deactivate() {
	$timestamp = wp_next_scheduled( MYIGNITE_IMPORTER_CRON_HOOK );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, MYIGNITE_IMPORTER_CRON_HOOK );
	}
}

add_action( MYIGNITE_IMPORTER_CRON_HOOK, 'myignite_sync_events' );


// -----------------------------------------------------------------------
// WP-CLI
//
// Registered as a single leaf command ('myignite sync-events'), not a
// second class under the 'myignite' namespace - the theme
// (inc/helpers/myignite-image-sync.php) already registers a
// MyIGNITE_CLI_Commands class there (sync-images, clean-descriptions,
// etc.); re-registering the whole namespace here would collide with it.
// -----------------------------------------------------------------------

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'myignite sync-events', 'myignite_cli_sync_events' );
}

function myignite_cli_sync_events( $args, $assoc_args ) {
	$options = array( 'dry_run' => isset( $assoc_args['dry-run'] ) );
	if ( ! empty( $assoc_args['since'] ) ) {
		$options['since'] = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $assoc_args['since'] ) );
	}
	myignite_sync_events( $options );
	WP_CLI::success( 'Event sync run complete. Check wp-content/myignite-event-sync.log for details.' );
}


// -----------------------------------------------------------------------
// ADMIN UI
//
// Required last, and only inside wp-admin: everything the settings screen
// calls (constants, settings helpers, myignite_sync_events()) is defined
// above by this point, and a front-end request never parses that file at
// all - so a bug on the settings screen cannot take down the public site.
// -----------------------------------------------------------------------

if ( is_admin() ) {
	require_once __DIR__ . '/admin-settings.php';
}
