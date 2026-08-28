<?php
/**
 * Plugin Name: MyIGNITE Event Importer
 * Description: Imports events from MyIGNITE (CampusGroups) via the CampusGroups Data API (rss_events), replacing the Event Aggregator ICS pipeline for The Events Calendar. <strong>Runs automatically once a day at 6:00 PM Toronto time.</strong> Manual run: <code>wp myignite sync-events</code> (add <code>--dry-run</code> to preview). Activity log: <code>wp-content/myignite-event-sync.log</code>. Note: the Data API answers from CampusGroups' live database directly (confirmed: an event created there is visible here within seconds), unlike the old Data Export API this plugin used until 2026-08-28, which was fed by a batch job roughly 18-30 hours behind live. Requires MYIGNITE_CG_SCHOOL_CODE / MYIGNITE_CG_API_SECRET in wp-content/mu-plugins.
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

// Base host for CampusGroups' Data API (see myignite_importer_data_api_fetch_events()
// below) - the same host every rsvp/event link on the site already points at.
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
// CAMPUSGROUPS DATA API (title / dates / location / description / image)
//
// Replaced the Data Export API here on 2026-08-28. That API was a
// batch-materialized, async (POST-then-poll) endpoint fed by a job on
// CampusGroups' side roughly 18-30 hours behind live - confirmed directly:
// 13 real, approved events were completely absent from its results as late
// as a same-day 6pm sync, appearing only some hours after. The Data API's
// rss_events resource instead answers from CampusGroups' live database on a
// single synchronous request - confirmed live (create an event, query
// seconds later, it's already there). It also carries the event's own photo
// directly, so the separate mobile_ws image-only pipeline this file used to
// run alongside the Data Export API is gone too - one feed, one HTTP call.
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
 * Fetches events from CampusGroups' Data API (rss_events).
 *
 * Auth is a plain X-CG-API-Secret header - the same header/value the old
 * Data Export API call used, confirmed working against this endpoint too.
 * (rss_events also accepts a ts+preauth query-string scheme - its own 403
 * error text even describes a formula for it - but the header matches this
 * codebase's existing pattern exactly, so use that and skip the extra
 * moving part.)
 *
 * Always pulls broadly (time_range=all, deleted=1 so removed events still
 * carry the eventDelete signal that trashes them here - CampusGroups
 * otherwise excludes deleted events from results entirely) and lets
 * myignite_sync_events() do the real filtering by event date, exactly as
 * the old Data Export API integration did. Unlike that API, there is no
 * batch snapshot to window around here, so no incremental/checkpoint logic
 * is needed - a single request each run is enough.
 *
 * @param array $args Extra/overriding query args, e.g. ['updated_after' => '2026-08-01 00:00:00'].
 * @return array|WP_Error Flat array of event rows (see
 *                         myignite_importer_data_api_item_to_array()), or WP_Error.
 */
function myignite_importer_data_api_fetch_events( $args = array() ) {
	$creds = myignite_importer_cg_credentials();
	if ( ! $creds ) {
		return new WP_Error( 'myignite_missing_credentials', 'MYIGNITE_CG_SCHOOL_CODE / MYIGNITE_CG_API_SECRET are not defined.' );
	}
	list( , $secret ) = $creds;

	$query_args = array_merge( array(
		'deleted'    => 1,
		'time_range' => 'all',
		'limit'      => 5000, // Comfortably above the real corpus; MYIGNITE_IMPORTER_MAX_PER_RUN caps processing regardless.
	), $args );

	$url = add_query_arg( $query_args, MYIGNITE_CG_HOST . '/rss_events' );

	$response = wp_remote_get( $url, array(
		'timeout' => 30,
		'headers' => array( 'X-CG-API-Secret' => $secret ),
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error( 'myignite_cg_fetch_failed', "Data API GET /rss_events returned HTTP {$code}: " . wp_remote_retrieve_body( $response ) );
	}

	$body = wp_remote_retrieve_body( $response );
	libxml_use_internal_errors( true );
	// LIBXML_NOCDATA folds CDATA into plain text; LIBXML_NONET blocks any
	// external-entity network fetch the XML might otherwise try to trigger.
	$xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );

	if ( false === $xml ) {
		return new WP_Error( 'myignite_cg_bad_xml', 'Data API GET /rss_events returned unparseable XML.' );
	}
	if ( ! isset( $xml->channel->item ) ) {
		return array(); // A run with nothing to report is a valid, ordinary response - not an error.
	}

	$events = array();
	foreach ( $xml->channel->item as $item ) {
		$events[] = myignite_importer_data_api_item_to_array( $item );
	}

	if ( count( $events ) === (int) $query_args['limit'] ) {
		myignite_importer_log( 'WARNING: Data API returned exactly the requested limit (' . $query_args['limit'] . ') of events - results may have been truncated.' );
	}

	return $events;
}

/**
 * Pulls exactly the fields the rest of this file needs out of one <item>, as
 * plain strings. Deliberately explicit field-by-field rather than a generic
 * XML-to-array cast of the whole item - several sibling elements
 * (customFields, eventTopicsSeparated, externalReferences) are structured,
 * not scalar, and are not needed here.
 *
 * @return array<string,string>
 */
function myignite_importer_data_api_item_to_array( SimpleXMLElement $item ) {
	return array(
		'id'                        => (string) $item->eventId,
		'groupId'                   => (string) $item->groupId,
		'group'                     => (string) $item->group,
		'title'                     => (string) $item->title,
		'description'               => (string) $item->description,
		'fullDescription'           => (string) $item->fullDescription,
		'eventLocation'             => (string) $item->eventLocation,
		'locationType'              => (string) $item->locationType,
		'eventStartDateTime'        => (string) $item->eventStartDateTime,
		'eventEndDateTime'          => (string) $item->eventEndDateTime,
		'eventLink'                 => (string) $item->eventLink,
		'eventType'                 => (string) $item->eventType,
		'eventTopics'               => (string) $item->eventTopics,
		'eventOriginalPhotoFullUrl' => (string) $item->eventOriginalPhotoFullUrl,
		'eventPhotoFullUrl'         => (string) $item->eventPhotoFullUrl,
		'draft'                     => (string) $item->draft,
		'eventDelete'               => (string) $item->eventDelete,
		'approvalStatus'            => (string) $item->approvalStatus,
		'publishCalendar'           => (string) $item->publishCalendar,
	);
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
	if ( ! empty( $event['eventDelete'] ) ) {
		return 'deleted on CampusGroups';
	}
	if ( ! empty( $event['draft'] ) ) {
		return 'moved back to draft on CampusGroups';
	}

	// Strict allow-list, not a guess at what to exclude: approvalStatus has
	// no documented value legend beyond 1 = approved, confirmed repeatedly
	// against the same real events' approvalStatus = "Approved" on the old
	// Data Export API (Bursary, all 13 "Block Party" events, etc.). We do
	// not know what other codes mean, and production must only ever import
	// approved events - so anything except the one confirmed-good value,
	// known or not, is treated as not approved. This mirrors exactly how the
	// old string field was already handled: only the literal "Approved"
	// passed, every other value failed closed with no carve-out.
	$approval = (int) ( $event['approvalStatus'] ?? 0 );
	if ( 1 !== $approval ) {
		// Observability, not noise: every event seen so far reports 1, so a
		// hit here should be rare - if a real non-1 code ever does occur,
		// this is the first record of what it actually was.
		myignite_importer_log( 'NOTE: event ' . ( $event['id'] ?? '?' ) . " reported approvalStatus={$approval} (only 1 is currently known to mean approved)." );
		return 'not approved on CampusGroups (approvalStatus=' . $approval . ')';
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

	// Only publish events CampusGroups itself shows publicly. publishCalendar
	// is numeric (confirmed from the Data API's own documentation):
	//   0 = Everyone, 1 = No one, 2 = registration-only,
	//   3 = group members only, 4 = logged-on users only.
	//
	// FAILS OPEN on purpose, same as before. If the key is missing entirely -
	// because CampusGroups renamed or dropped it - we treat the event as
	// visible rather than hidden. Only an explicit, non-zero value excludes.
	if ( array_key_exists( 'publishCalendar', $event )
		&& '' !== (string) $event['publishCalendar']
		&& 0 !== (int) $event['publishCalendar'] ) {
		return 'not publicly visible (Who can see this event on the calendar = code ' . $event['publishCalendar'] . ')';
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
 * Organizers use description/fullDescription inconsistently - some events
 * have real content only in one with the other empty, and vice versa.
 * content prefers the longer `fullDescription`; excerpt prefers the
 * (usually shorter, purpose-written) `description`, trimmed since it is not
 * always actually short. Confirmed field-for-field against a live event
 * (375533, "IGNITE Bursary Opens"): `description` here is an exact match for
 * what the site already shows as the post excerpt.
 */
function myignite_importer_build_event_fields( $event ) {
	$name        = trim( (string) ( $event['title'] ?? '' ) );
	$short_desc  = trim( (string) ( $event['description'] ?? '' ) );
	$description = trim( (string) ( $event['fullDescription'] ?? '' ) );

	$content = '' !== $description ? $description : $short_desc;
	$excerpt_source = '' !== $short_desc ? $short_desc : $description;
	$excerpt = '' !== $excerpt_source ? wp_trim_words( wp_strip_all_tags( $excerpt_source ), 30 ) : '';

	$tz = wp_timezone();
	// eventStartDateTime/eventEndDateTime already carry an explicit UTC
	// offset (e.g. "2026-10-13T09:00:00.0000000-04:00"), so unlike the old
	// Data Export API's startDate/endDate there is no separate timezone
	// field to combine them with.
	$start_utc = new DateTime( $event['eventStartDateTime'] );
	$start_utc->setTimezone( new DateTimeZone( 'UTC' ) );
	$end_source = ! empty( $event['eventEndDateTime'] ) ? $event['eventEndDateTime'] : $event['eventStartDateTime'];
	$end_utc = new DateTime( $end_source );
	$end_utc->setTimezone( new DateTimeZone( 'UTC' ) );

	$start_local = ( clone $start_utc )->setTimezone( $tz );
	$end_local   = ( clone $end_utc )->setTimezone( $tz );

	// eventLocation is already a clean, ready-to-display string from the
	// Data API - including "Online Event" automatically for virtual events.
	// The old Data Export API had no equivalent: it split location across
	// locationName/address, and address was not authoritative once an event
	// went virtual (confirmed: event 375536, "New Club Application
	// Deadline" - locationType said "Online Only" while address still held
	// a leftover physical room from before it was switched to virtual).
	// locationType is kept only as a defensive fallback for the rare case
	// eventLocation itself comes back blank.
	$location_type = trim( (string) ( $event['locationType'] ?? '' ) );
	$location      = trim( (string) ( $event['eventLocation'] ?? '' ) );
	if ( '' === $location && false !== stripos( $location_type, 'virtual' ) ) {
		$location = 'Online';
	}

	$tags = array();
	foreach ( explode( ',', (string) ( $event['eventTopics'] ?? '' ) ) as $topic ) {
		$topic = trim( $topic );
		if ( '' !== $topic ) {
			$tags[] = $topic;
		}
	}

	$group_id = (int) ( $event['groupId'] ?? 0 );

	// The Data API hands us the true original upload directly - no more
	// guessing between resize-prefix variants (see the retired
	// myignite_importer_image_url_candidates()). Falls back to the
	// non-original URL only if the original is somehow blank.
	$image_url = trim( (string) ( $event['eventOriginalPhotoFullUrl'] ?? '' ) );
	if ( '' === $image_url ) {
		$image_url = trim( (string) ( $event['eventPhotoFullUrl'] ?? '' ) );
	}

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
		'website'     => trim( (string) ( $event['eventLink'] ?? '' ) ),
		// known_groups(), not the API's own `group` name: a group added via
		// the settings page must resolve to its configured display name
		// here too, or its events would import with a blank category/organizer.
		'category'    => myignite_importer_known_groups()[ $group_id ] ?? '',
		'event_type'  => trim( (string) ( $event['eventType'] ?? '' ) ),
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
 * Downloads and sets the featured image, if the source URL has changed
 * since the last run.
 *
 * $image_url is eventOriginalPhotoFullUrl straight from the Data API (see
 * myignite_importer_build_event_fields()) - the true original upload, no
 * resize-variant guessing needed (the old mobile_ws-based pipeline only ever
 * reported a downscaled "r2_" copy, which is what
 * myignite_importer_image_url_candidates() used to work around; that
 * function and the guessing it did are gone along with it).
 */
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

	$tmp = download_url( $image_url );
	if ( is_wp_error( $tmp ) ) {
		return 'ERROR downloading image - ' . $tmp->get_error_message();
	}

	$attachment_id = media_handle_sideload( array( 'name' => $filename, 'tmp_name' => $tmp ), $post_id );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp );
		return 'ERROR sideloading image - ' . $attachment_id->get_error_message();
	}

	set_post_thumbnail( $post_id, $attachment_id );
	// Store the API-reported URL for change detection - that is what the
	// next run will compare against.
	update_post_meta( $post_id, '_myignite_source_image_url', $image_url );
	update_post_meta( $attachment_id, '_myignite_source_url', $image_url );

	return 'updated';
}


// -----------------------------------------------------------------------
// PER-EVENT SYNC
// -----------------------------------------------------------------------

function myignite_importer_sync_single_event( $event, $dry_run ) {
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

	$fields = myignite_importer_build_event_fields( $event );

	// Adoption by title + start date, for events not yet tagged with a
	// CampusGroups ID. This is DELIBERATE and load-bearing - do not remove it
	// as a "safety" measure (it was removed once for exactly that reason and
	// had to be restored).
	//
	// The workflow it supports: the sync only runs once a day, so an event
	// created on CampusGroups today isn't visible on the website until the
	// next scheduled run at the earliest, even with the near-real-time Data
	// API. When something needs to be live immediately, staff create it by
	// hand in wp-admin using the SAME title and start time as the
	// CampusGroups event. On the next sync it is adopted rather than
	// duplicated, and from then on CampusGroups is the source of truth for it.
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
			// Core event data is unchanged, but a first-attempt image
			// download/sideload can fail on its own (network hiccup, a
			// momentarily unreachable URL) independently of everything else
			// being fine - retry it on every skip so a failed download
			// doesn't stay missing forever alongside otherwise-unchanged
			// core data.
			if ( ! $dry_run && $fields['image_url'] && ! get_post_thumbnail_id( $existing_id ) ) {
				$image_status = myignite_importer_maybe_update_featured_image( $existing_id, $fields['image_url'] );
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

	$image_status = myignite_importer_maybe_update_featured_image( $post_id, $fields['image_url'] );

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

	// The Data API answers from CampusGroups' live database directly - unlike
	// the old Data Export API there is no batch snapshot to window around, so
	// we always pull broadly (time_range=all, inside
	// myignite_importer_data_api_fetch_events()) and do the real filtering on
	// event date below, exactly as before.
	$fetch_args = array();
	if ( ! empty( $options['since'] ) ) {
		// Optional scoping, kept for parity with the old --since flag: only
		// ask for events CampusGroups has touched since this time.
		$fetch_args['updated_after'] = $options['since'];
	}

	$events = myignite_importer_data_api_fetch_events( $fetch_args );
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
	myignite_importer_log( 'CampusGroups returned ' . count( $events ) . ' event record(s) (before group filtering).' );

	// Event-date filter. Anything outside the window is dropped here, so it is
	// never created, never updated, and never trashed - an event that simply
	// aged into the past is left exactly as it is.
	$before = count( $events );
	$events = array_values( array_filter( $events, static function ( $event ) use ( $start_min, $start_max, $inclusive ) {
		$start = ! empty( $event['eventStartDateTime'] ) ? strtotime( $event['eventStartDateTime'] ) : false;
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
		$result = myignite_importer_sync_single_event( $event, $dry_run );
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
// Kept at once a day for now even though the Data API (unlike the old Data
// Export API) has no batch lag that would make more-frequent polling
// pointless - increasing this is a separate, deliberate follow-up, not a
// side effect of the API migration. 6:00 PM local was chosen so a day's
// edits are picked up after business hours.
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
