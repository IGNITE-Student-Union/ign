<?php
/**
 * MyIGNITE — CampusGroups Event Image Sync
 *
 * Problem this solves:
 * Events imported from the ReadyEducation CampusGroups ICS feed (via The
 * Events Calendar's Event Aggregator) never carry an image, because ICS
 * feeds have no image field. This script:
 *
 *   1. Finds events that have a Website URL but no featured image yet.
 *   2. Looks up the event's own uploaded photo via CampusGroups' public
 *      events-list API (mobile_ws), matched by the CampusGroups event ID
 *      embedded in the Website URL (?id=NNNNNN). This is the same photo
 *      shown on the MyIGNITE events list, and is unaffected by whatever
 *      "Event Website" link an organizer set on the event.
 *   3. Falls back to scraping <meta property="og:image"> off the Website
 *      URL itself if the API doesn't have the event (e.g. it's no longer
 *      listed as upcoming), or skips if neither has an image — this is
 *      expected and not an error (CampusGroups organizer just didn't
 *      upload one).
 *   4. Downloads whichever image was found and sets it as the event's
 *      WordPress featured image (which is what The Events Calendar uses
 *      for event listing/single-event images).
 *
 * Why not just always scrape og:image (the original, simpler approach):
 * confirmed against a real event ("Sleep Lounge Opens") that og:image on
 * a CampusGroups RSVP page does NOT reliably reflect the event's own
 * photo — CampusGroups let an unrelated "Event Website" link set on the
 * event override it, resolving to this site's default logo (an SVG,
 * which WordPress rejects). Because the old approach never marked the
 * event as "resolved," that produced an identical error every single
 * hour indefinitely, with no way for it to ever self-correct. See
 * myignite_fetch_campusgroups_event_photos() for the full story.
 *
 * Runs two ways:
 *   - On a schedule, hourly, via WP-Cron (paired with WP Engine's
 *     "Alternate cron" toggle in the User Portal, so this isn't dependent
 *     on live site traffic to fire on time).
 *   - On demand, manually, via WP-CLI:  wp myignite sync-images
 *     (Useful for testing without waiting for the hourly run.)
 *
 * Logs every run to: wp-content/myignite-image-sync.log
 * Each line is timestamped and says exactly what happened to each event
 * (updated / skipped-no-og-image / skipped-already-has-image /
 * skipped-no-website / error-with-reason) so that anyone debugging this
 * later — including someone with zero prior context on this script —
 * can read the log and understand exactly what the script saw and did,
 * without needing to read the code first.
 *
 * Where this file lives:
 *   wp-content/themes/YOUR-THEME/inc/helpers/myignite-image-sync.php
 *   required from the theme's functions.php — see the one-line snippet
 *   in the comment at the very bottom of this file.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't allow this file to be loaded directly outside WordPress.
}

// -----------------------------------------------------------------------
// CONFIG — the only section you should need to touch to adjust behavior.
// -----------------------------------------------------------------------

// How often the sync runs when triggered by WP-Cron.
// Must match a registered WP-Cron interval name — 'hourly' is built into
// WordPress core already, so no custom interval needs to be registered.
define( 'MYIGNITE_SYNC_CRON_INTERVAL', 'hourly' );

// Where the log file is written. wp-content/ is writable on WP Engine and
// is NOT publicly web-accessible in a way that exposes raw PHP, but a
// .log file sitting there IS fetchable by URL if someone guesses the path,
// so we deny direct access to it via .htaccess-equivalent logic further
// down (myignite_block_log_file_access).
define( 'MYIGNITE_SYNC_LOG_PATH', WP_CONTENT_DIR . '/myignite-image-sync.log' );

// Safety cap: max events processed in a single run, so a feed problem or
// huge backlog can't make one run hang indefinitely or hammer
// CampusGroups with hundreds of rapid requests.
define( 'MYIGNITE_SYNC_MAX_PER_RUN', 50 );

// Pause between each external HTTP request to CampusGroups, in seconds.
// A small delay is courteous to CampusGroups' servers and reduces the
// chance of being rate-limited or blocked outright.
define( 'MYIGNITE_SYNC_REQUEST_DELAY', 1 );

// CampusGroups' mobile web service that backs the public events list at
// my.ignitestudentunion.ca/events. Confirmed via the site's own network
// requests to require no authentication. Returns each event's own
// uploaded photo directly (field "eventPicture", keyed by "eventId") —
// this is the authoritative source and is preferred over scraping
// og:image (see myignite_fetch_campusgroups_event_photos() below for why).
define( 'MYIGNITE_CAMPUSGROUPS_EVENTS_API', 'https://my.ignitestudentunion.ca/mobile_ws/v17/mobile_events_list' );
define( 'MYIGNITE_CAMPUSGROUPS_HOST', 'https://my.ignitestudentunion.ca' );


// -----------------------------------------------------------------------
// LOGGING
// -----------------------------------------------------------------------

/**
 * Append one line to the sync log, with a timestamp.
 *
 * @param string $message Human-readable line, e.g. "Event 1234: updated featured image".
 */
function myignite_sync_log( $message ) {
	$line = sprintf( '[%s] %s' . PHP_EOL, current_time( 'Y-m-d H:i:s' ), $message );
	// FILE_APPEND keeps adding to the same file rather than overwriting it.
	// LOCK_EX avoids two overlapping runs corrupting the file if they ever
	// somehow run at the same time.
	file_put_contents( MYIGNITE_SYNC_LOG_PATH, $line, FILE_APPEND | LOCK_EX );
}

/**
 * Block direct web access to the log file, since wp-content/ is inside
 * the web root and the file would otherwise be fetchable by anyone who
 * guesses or finds its URL. This denies the request at the WordPress
 * level for any request that matches the log file's name.
 *
 * This is a basic safety net, not a replacement for proper server-level
 * rules — if you have access to edit your site's main .htaccess file
 * (or equivalent on WP Engine), adding a rule there to block
 * myignite-image-sync.log directly is a more robust belt-and-suspenders
 * option, but isn't required for this to work.
 */
add_action( 'init', 'myignite_block_log_file_access' );
function myignite_block_log_file_access() {
	if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], 'myignite-image-sync.log' ) ) {
		wp_die( 'Not found.', '', array( 'response' => 404 ) );
	}
}


// -----------------------------------------------------------------------
// CORE SYNC LOGIC
// -----------------------------------------------------------------------

/**
 * Fetches every event CampusGroups currently has listed and returns each
 * one's own uploaded photo, keyed by CampusGroups event ID.
 *
 * Why this exists (replaces og:image scraping as the primary source):
 * og:image on an event's CampusGroups RSVP page is NOT reliably the
 * event's own photo — confirmed against a real event ("Sleep Lounge
 * Opens") where CampusGroups had a perfectly good photo (visible on the
 * MyIGNITE events list) but the RSVP page's og:image tag instead reflected
 * an unrelated external "Event Website" link that had been set on the
 * event, which itself resolved to this site's default logo (an SVG,
 * which WordPress won't accept — so the old scrape-based sync failed on
 * this event every single hour, forever, with no path to ever self-correct).
 * This mobile_ws endpoint is what CampusGroups' own public events list
 * uses, so eventPicture here is the same photo staff actually uploaded to
 * the event — unaffected by whatever "Event Website" link is set.
 *
 * The response is a flat array of rows (event rows interleaved with date-
 * separator rows) where each row is a positional p0../p49 map rather than
 * named fields. Every row carries a "fields" string naming what each pN
 * means for that row, so we read field positions from that rather than
 * hardcoding index numbers — CampusGroups could reorder them without
 * notice since this is an undocumented internal API, not a public one.
 *
 * @return array<int,string> Map of CampusGroups event ID => full photo URL.
 *                            Empty array if the request fails or nothing
 *                            back was parseable — callers should treat
 *                            that as "fall back to og:image", not an error.
 */
function myignite_fetch_campusgroups_event_photos() {
	$response = wp_remote_get(
		add_query_arg(
			array(
				'range' => 0,
				// Comfortably above the number of events CampusGroups has
				// listed at once in practice; MYIGNITE_SYNC_MAX_PER_RUN
				// caps how many WP events we process per run regardless.
				'limit' => 500,
			),
			MYIGNITE_CAMPUSGROUPS_EVENTS_API
		),
		array( 'timeout' => 15 )
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		myignite_sync_log( 'CampusGroups events API request failed — falling back to og:image scraping for this run.' );
		return array();
	}

	$rows = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $rows ) ) {
		myignite_sync_log( 'CampusGroups events API returned unparseable data — falling back to og:image scraping for this run.' );
		return array();
	}

	$photos_by_event_id = array();

	foreach ( $rows as $row ) {
		if ( empty( $row['fields'] ) ) {
			continue;
		}

		$field_names = explode( ',', $row['fields'] );
		$id_index    = array_search( 'eventId', $field_names, true );
		$photo_index = array_search( 'eventPicture', $field_names, true );

		if ( false === $id_index || false === $photo_index ) {
			continue; // A date-separator row, not an event row.
		}

		$event_id  = $row[ 'p' . $id_index ] ?? '';
		$photo_url = $row[ 'p' . $photo_index ] ?? '';

		if ( ! ctype_digit( (string) $event_id ) || empty( $photo_url ) ) {
			continue;
		}

		// eventPicture is a path relative to the CampusGroups host, e.g.
		// "/upload/ignite/2026/r2_image_upload_...jpeg".
		$photos_by_event_id[ (int) $event_id ] = MYIGNITE_CAMPUSGROUPS_HOST . $photo_url;
	}

	myignite_sync_log( sprintf( 'CampusGroups events API returned photos for %d event(s).', count( $photos_by_event_id ) ) );

	return $photos_by_event_id;
}

/**
 * Extracts the numeric CampusGroups event ID from an Event Website URL
 * such as "https://my.ignitestudentunion.ca/rsvp?id=375489".
 *
 * @param string $website_url The _EventURL postmeta value.
 * @return int|false The event ID, or false if the URL doesn't carry one
 *                    (e.g. an organizer overwrote it with something else).
 */
function myignite_get_campusgroups_event_id( $website_url ) {
	if ( preg_match( '/[?&]id=(\d+)/', $website_url, $matches ) ) {
		return (int) $matches[1];
	}
	return false;
}

/**
 * Memoized wrapper around myignite_fetch_campusgroups_event_photos() so
 * a single hourly batch run (processing up to MYIGNITE_SYNC_MAX_PER_RUN
 * events) only ever fetches the CampusGroups events list once.
 *
 * @return array<int,string>
 */
function myignite_get_campusgroups_photos_cached() {
	static $photos = null;

	if ( null === $photos ) {
		$photos = myignite_fetch_campusgroups_event_photos();
	}

	return $photos;
}

/**
 * Resolves and sets a featured image for one event: prefers the event's
 * own CampusGroups photo, falls back to scraping og:image, skips a URL
 * that's already known to fail (see the postmeta check below), then
 * downloads and sets whichever image was found. Called once per event
 * by the hourly batch sync (myignite_run_image_sync()) below.
 *
 * @param int        $event_id            WordPress post ID of the event.
 * @param array|null $campusgroups_photos Pass a pre-fetched map to avoid
 *                                        a redundant API call inside a
 *                                        loop; omit to fetch (and cache) it.
 * @return string One of 'updated', 'skipped', 'error'.
 */
function myignite_sync_single_event_image( $event_id, $campusgroups_photos = null ) {
	if ( null === $campusgroups_photos ) {
		$campusgroups_photos = myignite_get_campusgroups_photos_cached();
	}

	// The Events Calendar stores the "Event Website" field in this post
	// meta key. Confirmed by checking an imported event's custom fields
	// in wp-admin (classic editor "Event Website" box writes here).
	$website_url = get_post_meta( $event_id, '_EventURL', true );

	if ( empty( $website_url ) ) {
		myignite_sync_log( "Event {$event_id}: skipped — no Event Website URL set." );
		return 'skipped';
	}

	$cg_event_id = myignite_get_campusgroups_event_id( $website_url );

	if ( $cg_event_id && ! empty( $campusgroups_photos[ $cg_event_id ] ) ) {
		// Preferred source: the event's own uploaded CampusGroups photo,
		// unaffected by whatever "Event Website" link happens to be set
		// on the event.
		$image_url = $campusgroups_photos[ $cg_event_id ];
	} else {
		// Fall back to scraping og:image off the Website URL itself — for
		// events CampusGroups' events-list API didn't return (e.g. no
		// longer listed as upcoming), or a non-CampusGroups URL.
		$image_url = myignite_extract_og_image( $website_url, $event_id );

		if ( false === $image_url ) {
			// myignite_extract_og_image() already logged the specific
			// reason, so we don't log again here.
			return 'skipped';
		}
	}

	// If this exact image URL already failed on a previous attempt, don't
	// re-attempt it every time this runs. CampusGroups filenames are
	// UUID-based, so an actual fix (new photo uploaded, Website link
	// changed) always produces a new URL and clears this on its own — no
	// need for anyone to read the log or intervene by hand.
	$last_failed_url = get_post_meta( $event_id, '_myignite_last_failed_image_url', true );

	if ( $last_failed_url && $last_failed_url === $image_url ) {
		myignite_sync_log( "Event {$event_id}: skipped — {$image_url} already failed on a previous attempt and hasn't changed since." );
		return 'skipped';
	}

	$result = myignite_set_featured_image_from_url( $event_id, $image_url );

	if ( is_wp_error( $result ) ) {
		myignite_sync_log( "Event {$event_id}: ERROR — " . $result->get_error_message() );
		update_post_meta( $event_id, '_myignite_last_failed_image_url', $image_url );
		return 'error';
	}

	delete_post_meta( $event_id, '_myignite_last_failed_image_url' );
	myignite_sync_log( "Event {$event_id}: updated featured image from {$image_url}" );
	return 'updated';
}

/**
 * Run the full batch sync: find every eligible event, attempt to pull an
 * image for each, log a summary. This is the single function both the
 * WP-Cron hook and the WP-CLI command call — so behavior is identical
 * whether it's triggered automatically or run manually. Runs hourly, so
 * an event with no usable image yet (e.g. CampusGroups didn't have the
 * photo at the time) just gets retried on a later run — self-correcting
 * without anyone needing to intervene by hand.
 *
 * Deliberately NOT triggered directly from the ICS import itself: doing so
 * previously ran the sync work synchronously inside Event Aggregator's own
 * import request, which caused two confirmed incidents — one import
 * running unusually long and silently dropping an event entirely, and
 * (even after switching to wp_schedule_single_event(), which itself is
 * near-instant) Event Aggregator's self-perpetuating import queue never
 * advancing past the first item, because our hook running inside that
 * same request disrupted the queue's own dispatch chain. Confirmed via
 * live A/B testing: disabling only the on-import hook let the queue
 * process every event in the feed; enabling it again reproduced the
 * stall on the very next run. The hourly cron is fully decoupled from
 * Event Aggregator's import request, so it can't interfere with it at all.
 */
function myignite_run_image_sync() {
	myignite_sync_log( 'Sync run started.' );

	// Fetched once per run rather than per-event: it's a single request
	// regardless of how many events need it, and gives every event in
	// this run access to the same up-to-date CampusGroups photo list.
	$campusgroups_photos = myignite_get_campusgroups_photos_cached();

	// Pull events that don't yet have a featured image. We query by
	// post type directly with WP_Query rather than going through any
	// REST layer, since this all runs server-side inside WordPress —
	// no HTTP round-trip to itself needed.
	$query = new WP_Query(
		array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
			'posts_per_page' => MYIGNITE_SYNC_MAX_PER_RUN,
			'meta_query'     => array(
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	if ( ! $query->have_posts() ) {
		myignite_sync_log( 'No events without a featured image were found. Nothing to do.' );
		return;
	}

	$processed = 0;
	$updated   = 0;
	$skipped   = 0;
	$errors    = 0;

	foreach ( $query->posts as $event_post ) {
		$event_id = $event_post->ID;
		$processed++;

		switch ( myignite_sync_single_event_image( $event_id, $campusgroups_photos ) ) {
			case 'updated':
				$updated++;
				break;
			case 'error':
				$errors++;
				break;
			default:
				$skipped++;
				break;
		}

		// Be polite to CampusGroups' servers between events.
		sleep( MYIGNITE_SYNC_REQUEST_DELAY );
	}

	myignite_sync_log(
		sprintf(
			'Sync run finished. Processed: %d, Updated: %d, Skipped: %d, Errors: %d.',
			$processed,
			$updated,
			$skipped,
			$errors
		)
	);
}

/**
 * Fetch a CampusGroups event page and pull the og:image URL out of it,
 * if one exists.
 *
 * @param string $page_url The CampusGroups event page URL (from the Event Website field).
 * @param int    $event_id Used only for logging context.
 * @return string|false The image URL if found, or false if no og:image
 *                       tag exists, or the page couldn't be fetched.
 */
function myignite_extract_og_image( $page_url, $event_id ) {
	$response = wp_remote_get(
		$page_url,
		array(
			'timeout'    => 15,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
		)
	);

	if ( is_wp_error( $response ) ) {
		myignite_sync_log( "Event {$event_id}: skipped — could not fetch {$page_url} ({$response->get_error_message()})." );
		return false;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== (int) $status_code ) {
		myignite_sync_log( "Event {$event_id}: skipped — {$page_url} returned HTTP {$status_code}." );
		return false;
	}

	$html = wp_remote_retrieve_body( $response );

	// Look for <meta property="og:image" content="...">, tolerant of
	// single or double quotes and attribute order, since we don't fully
	// control CampusGroups' markup and it could vary between events or
	// change over time.
	$pattern = '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i';

	if ( ! preg_match( $pattern, $html, $matches ) ) {
		// Also try the reversed attribute order (content before property),
		// since some platforms emit it that way.
		$pattern_reversed = '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\'][^>]*>/i';
		if ( ! preg_match( $pattern_reversed, $html, $matches ) ) {
			myignite_sync_log( "Event {$event_id}: skipped — no og:image tag on {$page_url} (event likely has no image on CampusGroups)." );
			return false;
		}
	}

	$image_url = html_entity_decode( $matches[1] );

	if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
		myignite_sync_log( "Event {$event_id}: skipped — og:image tag found but content was not a valid URL ('{$image_url}')." );
		return false;
	}

	return $image_url;
}

/**
 * Download an image from a URL and set it as the featured image for a
 * given event post.
 *
 * Uses media_handle_sideload() (lower-level than media_sideload_image)
 * so we can control the filename — the event title is used rather than
 * inheriting whatever CampusGroups named the file (e.g. the long UUID
 * strings like r3_image_upload_599695_EventPhoto_…). Works correctly
 * whether CampusGroups serves jpg, png, webp, or anything else — file
 * type is detected from the actual downloaded content, not hardcoded.
 *
 * @param int    $event_id  The WordPress post ID of the event.
 * @param string $image_url The image URL to download and attach.
 * @return true|WP_Error True on success, WP_Error with a reason on failure.
 */
function myignite_set_featured_image_from_url( $event_id, $image_url ) {
	// These helpers aren't autoloaded outside wp-admin contexts
	// (e.g. when this runs via WP-Cron or WP-CLI).
	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}
	if ( ! function_exists( 'download_url' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	// Build a clean filename from the event title rather than inheriting
	// whatever CampusGroups named the file (e.g. r3_image_upload_599695_…).
	// Strip the query string before reading the extension so URLs like
	// "…/photo.jpeg?v=2" still resolve to "jpeg".
	$ext      = strtolower( pathinfo( strtok( $image_url, '?' ), PATHINFO_EXTENSION ) ) ?: 'jpg';
	$filename = sanitize_title( get_the_title( $event_id ) ) . '_myignite_import.' . $ext;

	$tmp = download_url( $image_url );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	$file_array = array(
		'name'     => $filename,
		'tmp_name' => $tmp,
	);

	$attachment_id = media_handle_sideload( $file_array, $event_id );

	if ( is_wp_error( $attachment_id ) ) {
		// media_handle_sideload() doesn't clean up the temp file on failure.
		@unlink( $tmp );
		return $attachment_id;
	}

	$thumbnail_set = set_post_thumbnail( $event_id, $attachment_id );

	if ( false === $thumbnail_set ) {
		return new WP_Error(
			'myignite_thumbnail_set_failed',
			"media_handle_sideload succeeded (attachment {$attachment_id}) but set_post_thumbnail failed."
		);
	}

	// Keep a record of where this image actually came from, directly on
	// the attachment — helpful later if anyone wonders why a particular
	// image is attached to a particular event.
	update_post_meta( $attachment_id, '_myignite_source_url', $image_url );

	return true;
}


// -----------------------------------------------------------------------
// WP-CRON: scheduled automatic runs
// -----------------------------------------------------------------------

/**
 * Register the cron event on theme activation / first load if it isn't
 * already scheduled. wp_next_scheduled() prevents this from stacking up
 * duplicate scheduled events on every page load — it only schedules once.
 */
add_action( 'init', 'myignite_schedule_image_sync' );
function myignite_schedule_image_sync() {
	if ( ! wp_next_scheduled( 'myignite_image_sync_event' ) ) {
		wp_schedule_event( time(), MYIGNITE_SYNC_CRON_INTERVAL, 'myignite_image_sync_event' );
	}
}

// When the scheduled event fires, run the sync.
add_action( 'myignite_image_sync_event', 'myignite_run_image_sync' );

/**
 * Unschedule the cron event if this file is ever removed from the theme
 * — without this, WordPress would keep trying to fire an action hook
 * that no longer exists. There's no plugin "deactivation" hook available
 * since this lives in the theme rather than a plugin, so the practical
 * way to clean this up if you ever remove this feature entirely is to
 * temporarily add a one-time call to myignite_unschedule_image_sync()
 * in functions.php, load the site once, then remove that call.
 */
function myignite_unschedule_image_sync() {
	$timestamp = wp_next_scheduled( 'myignite_image_sync_event' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'myignite_image_sync_event' );
	}
}


// -----------------------------------------------------------------------
// WP-CLI: manual on-demand runs
// -----------------------------------------------------------------------

/**
 * Registers WP-CLI commands under `wp myignite`, only when running under
 * WP-CLI (this class/registration would error if loaded in a normal web
 * request, since the WP_CLI base class wouldn't exist).
 *
 * Available commands:
 *   wp myignite sync-images       — pull each event's own photo from
 *                                   CampusGroups (falling back to
 *                                   og:image scraping) and set it as
 *                                   featured image for events that don't
 *                                   have one yet. Also runs automatically
 *                                   every hour via WP-Cron.
 *   wp myignite clean-descriptions — one-time cleanup to strip the
 *                                   "--- Event Details: URL" footer that
 *                                   CampusGroups appends to descriptions
 *                                   in the ICS feed, from all existing events.
 *   wp myignite fix-club-categories — one-time cleanup to rename/merge
 *                                   existing concatenated club_acronym
 *                                   categories (e.g. IGNITECLUBS) already
 *                                   sitting in the database from before the
 *                                   import fix existed.
 *   wp myignite survey-description-whitespace — read-only report on which
 *                                   whitespace patterns (multi-space runs,
 *                                   real newlines, CRLF, literal "\n" text,
 *                                   non-breaking spaces, tabs) actually
 *                                   appear across every existing event's
 *                                   description, to inform a future
 *                                   paragraph-break reconstruction fix.
 *                                   Changes nothing.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class MyIGNITE_CLI_Commands {

		/**
		 * Sync event images from CampusGroups.
		 *
		 * Finds published events with a CampusGroups URL in the Event Website
		 * field but no featured image set. For each, looks up the event's own
		 * uploaded photo via CampusGroups' events-list API, falling back to
		 * scraping og:image off the Website URL if that lookup doesn't have
		 * it. Downloads whichever image was found and sets it as the
		 * WordPress featured image. Events with no image available anywhere
		 * are silently skipped — this is expected, not an error.
		 *
		 * All activity is logged to wp-content/myignite-image-sync.log.
		 *
		 * ## EXAMPLES
		 *
		 *     wp myignite sync-images
		 */
		public function sync_images( $args, $assoc_args ) {
			myignite_run_image_sync();
			WP_CLI::success( 'Image sync run complete. Check wp-content/myignite-image-sync.log for details.' );
		}

		/**
		 * Remove the "--- Event Details: URL" footer CampusGroups appends to
		 * event descriptions in the ICS feed, from all existing events.
		 *
		 * New imports are cleaned automatically on save via a filter hook
		 * elsewhere in the codebase. This command is a one-time cleanup for
		 * events already in the database before that filter was in place.
		 * Safe to run multiple times — events already clean are skipped.
		 *
		 * Queries wp_posts directly via $wpdb rather than WP_Query: The
		 * Events Calendar's Custom Tables V1 layer substitutes real post IDs
		 * with "occurrence" pseudo-IDs on WP_Query results for tribe_events
		 * (confirmed even with 'suppress_filters' => true — it happens below
		 * that stage), and wp_update_post() against one of those pseudo-IDs
		 * silently matches zero rows instead of erroring. A WP_Query-based
		 * version of this command reported "cleaned" on every single run
		 * without ever actually changing anything.
		 *
		 * ## EXAMPLES
		 *
		 *     wp myignite clean-descriptions
		 */
		public function clean_descriptions( $args, $assoc_args ) {
			// Matches " --- Event Details: https://..." at the end of the
			// string, tolerant of em-dash, en-dash, or a run of hyphens (CampusGroups'
			// real separator is three, "---", confirmed from raw import data), and any
			// amount of surrounding whitespace. The "Event Details: URL" part is
			// optional — CampusGroups sometimes sends just the bare
			// separator with no URL after it.
			$pattern = '/\s*(?:\x{2013}|\x{2014}|-+)\s*(?:Event Details:\s*https?:\/\/\S+)?\s*$/u';

			global $wpdb;
			$rows = $wpdb->get_results(
				"SELECT ID, post_content, post_excerpt FROM {$wpdb->posts}
				 WHERE post_type = 'tribe_events' AND post_status = 'publish'"
			);

			$cleaned = 0;
			$skipped = 0;

			foreach ( $rows as $row ) {
				$new_content = preg_replace( $pattern, '', $row->post_content );
				$new_excerpt = preg_replace( $pattern, '', $row->post_excerpt );

				if ( $new_content === $row->post_content && $new_excerpt === $row->post_excerpt ) {
					$skipped++;
					continue;
				}

				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new_content, 'post_excerpt' => $new_excerpt ),
					array( 'ID' => $row->ID )
				);
				clean_post_cache( $row->ID );

				WP_CLI::log( "Event {$row->ID}: cleaned." );
				$cleaned++;
			}

			WP_CLI::success( "Done. Cleaned: {$cleaned}, Already clean: {$skipped}." );
		}

		/**
		 * Rename or merge existing tribe_events_cat terms that are still in
		 * the concatenated club_acronym form (e.g. IGNITECLUBS) from before
		 * myignite_fix_club_acronym_categories() existed, or from a club
		 * feed that wasn't yet covered by it.
		 *
		 * New imports are already corrected automatically going forward
		 * (see myignite_fix_club_acronym_categories() below) — this command
		 * only cleans up terms that were created before that fix was in
		 * place, or before the fix was generalized to cover every
		 * IGNITE+word acronym rather than a fixed list. Safe to run
		 * multiple times — terms already correct are left untouched.
		 *
		 * Two cases per bad term found:
		 *   - Corrected name doesn't exist as a term yet: rename the bad
		 *     term in place (wp_update_term) — same term_id, so every
		 *     event already categorized with it keeps the category, just
		 *     readable.
		 *   - Corrected name already exists as its own term (e.g. some
		 *     events got categorized "IGNITE Clubs" after the fix went in,
		 *     while older events still carry "IGNITECLUBS" from before):
		 *     re-categorize every event under the bad term with the
		 *     correct term, then delete the bad term, so the two don't sit
		 *     side by side as duplicate categories.
		 *
		 * ## EXAMPLES
		 *
		 *     wp myignite fix-club-categories
		 */
		public function fix_club_categories( $args, $assoc_args ) {
			$terms = get_terms( array(
				'taxonomy'   => 'tribe_events_cat',
				'hide_empty' => false,
			) );

			if ( is_wp_error( $terms ) ) {
				WP_CLI::error( 'Could not fetch tribe_events_cat terms: ' . $terms->get_error_message() );
				return;
			}

			$renamed = 0;
			$merged  = 0;
			$skipped = 0;

			foreach ( $terms as $term ) {
				$corrected = myignite_correct_club_acronym_name( $term->name );

				if ( $corrected === $term->name ) {
					$skipped++;
					continue;
				}

				$existing = get_term_by( 'name', $corrected, 'tribe_events_cat' );

				if ( $existing && $existing->term_id !== $term->term_id ) {
					$post_ids = get_objects_in_term( $term->term_id, 'tribe_events_cat' );
					$post_ids = is_wp_error( $post_ids ) ? array() : $post_ids;

					foreach ( $post_ids as $post_id ) {
						// Third arg `true` appends rather than replacing,
						// so any other categories on the event are left alone.
						wp_set_object_terms( $post_id, array( (int) $existing->term_id ), 'tribe_events_cat', true );
					}

					wp_delete_term( $term->term_id, 'tribe_events_cat' );

					WP_CLI::log( "Category \"{$term->name}\": merged " . count( $post_ids ) . " event(s) into existing \"{$corrected}\" and removed the old category." );
					$merged++;
				} else {
					wp_update_term( $term->term_id, 'tribe_events_cat', array(
						'name' => $corrected,
						'slug' => sanitize_title( $corrected ),
					) );

					WP_CLI::log( "Category \"{$term->name}\": renamed to \"{$corrected}\"." );
					$renamed++;
				}
			}

			WP_CLI::success( "Done. Renamed: {$renamed}, Merged: {$merged}, Already correct: {$skipped}." );
		}

		/**
		 * Read-only survey of every existing tribe_events post for whitespace
		 * patterns that likely represent a paragraph break CampusGroups'
		 * plain-text ICS export flattened away.
		 *
		 * Different authoring styles on the CampusGroups side (rich-text
		 * editor vs. plain text) leave different signatures behind when
		 * flattened: some collapse to runs of 2+ spaces, some keep a real
		 * newline, some might carry CRLF, a literal un-escaped "\n" (two
		 * characters, not a real newline), a non-breaking space, or a tab.
		 * A single space is indistinguishable from normal sentence spacing
		 * and can't be recovered — this only surfaces detectable patterns.
		 *
		 * Changes nothing. Purpose is to base the eventual paragraph-break
		 * reconstruction on what's actually in the database across every
		 * real event, not just the one or two test events seen so far.
		 *
		 * ## EXAMPLES
		 *
		 *     wp myignite survey-description-whitespace
		 */
		public function survey_description_whitespace( $args, $assoc_args ) {
			global $wpdb;
			$rows = $wpdb->get_results(
				"SELECT ID, post_content, post_excerpt FROM {$wpdb->posts}
				 WHERE post_type = 'tribe_events' AND post_status = 'publish'"
			);

			$space_run_counts        = array();
			$has_crlf                = 0;
			$has_cr_only             = 0;
			$has_real_newline        = 0;
			$has_literal_backslash_n = 0;
			$has_nbsp                = 0;
			$has_tab                 = 0;
			$examples                = array();

			$snippet = function ( $text, $pos ) {
				return trim( substr( $text, max( 0, $pos - 20 ), 60 ) );
			};

			foreach ( $rows as $row ) {
				$text = $row->post_content . "\n---\n" . $row->post_excerpt;

				if ( preg_match_all( '/ {2,}/', $text, $matches, PREG_OFFSET_CAPTURE ) ) {
					foreach ( $matches[0] as $match ) {
						$len = strlen( $match[0] );
						$space_run_counts[ $len ] = ( $space_run_counts[ $len ] ?? 0 ) + 1;
					}
					if ( ! isset( $examples['multi-space'] ) ) {
						$examples['multi-space'] = "Event {$row->ID}: ..." . $snippet( $text, $matches[0][0][1] ) . '...';
					}
				}

				if ( strpos( $text, "\r\n" ) !== false ) {
					$has_crlf++;
				} elseif ( strpos( $text, "\r" ) !== false ) {
					$has_cr_only++;
				}

				if ( strpos( $text, "\n" ) !== false ) {
					$has_real_newline++;
					if ( ! isset( $examples['real-newline'] ) ) {
						$examples['real-newline'] = "Event {$row->ID}: ..." . $snippet( $text, strpos( $text, "\n" ) ) . '...';
					}
				}

				if ( strpos( $text, '\\n' ) !== false ) {
					$has_literal_backslash_n++;
					if ( ! isset( $examples['literal-backslash-n'] ) ) {
						$examples['literal-backslash-n'] = "Event {$row->ID}: ..." . $snippet( $text, strpos( $text, '\\n' ) ) . '...';
					}
				}

				if ( strpos( $text, "\u{00A0}" ) !== false ) {
					$has_nbsp++;
				}

				if ( strpos( $text, "\t" ) !== false ) {
					$has_tab++;
				}
			}

			WP_CLI::log( 'Scanned ' . count( $rows ) . ' published tribe_events posts.' );
			WP_CLI::log( '' );
			WP_CLI::log( 'Multi-space runs (likely collapsed paragraph breaks), by run length:' );
			ksort( $space_run_counts );
			foreach ( $space_run_counts as $len => $count ) {
				WP_CLI::log( "  {$len} spaces: {$count} occurrence(s) across all events" );
			}
			WP_CLI::log( '' );
			WP_CLI::log( "Events containing a real newline character: {$has_real_newline}" );
			WP_CLI::log( "Events containing CRLF (\\r\\n): {$has_crlf}" );
			WP_CLI::log( "Events containing a lone CR (\\r) with no LF: {$has_cr_only}" );
			WP_CLI::log( "Events containing literal escaped \"\\n\" as text (not a real newline): {$has_literal_backslash_n}" );
			WP_CLI::log( "Events containing a non-breaking space: {$has_nbsp}" );
			WP_CLI::log( "Events containing a tab character: {$has_tab}" );
			WP_CLI::log( '' );
			WP_CLI::log( 'Sample snippets:' );
			foreach ( $examples as $label => $text ) {
				WP_CLI::log( "  [{$label}] {$text}" );
			}

			WP_CLI::success( 'Survey complete. No data was changed.' );
		}
	}

	WP_CLI::add_command( 'myignite', 'MyIGNITE_CLI_Commands' );
}


// -----------------------------------------------------------------------
// ICS IMPORT: RENAME CLUB ACRONYM CATEGORIES
// -----------------------------------------------------------------------

/**
 * Renames CampusGroups club_acronym categories from concatenated uppercase
 * (e.g. IGNITEEVENTS) to a readable form (e.g. IGNITE Events) on import.
 *
 * Why this is needed:
 * CampusGroups exports a CATEGORIES line with X-CG-CATEGORY=club_acronym
 * containing the club acronym as a single concatenated uppercase string
 * with no spaces. Event Aggregator imports this verbatim as a
 * tribe_events_cat term, producing unreadable categories like
 * IGNITEEVENTS, IGNITEADVOCACY, IGNITECLUBS, etc.
 *
 * The other two CATEGORIES lines CampusGroups exports per event are:
 *   X-CG-CATEGORY=event_type  e.g. "Social Event", "Orientation"
 *   X-CG-CATEGORY=event_tags  e.g. "Campus - North", "Campus - Downtown"
 * These are already human-readable and are left completely alone.
 *
 * GENERAL RULE (no per-feed maintenance needed):
 * Every club_acronym observed so far is "IGNITE" immediately followed by
 * one concatenated all-caps word (EVENTS, ADVOCACY, GOVERNANCE, SERVICES,
 * PROMOTIONS, CLUBS, ...). Rather than maintaining a hardcoded list that
 * has to be updated (and redeployed) every time a new club feed shows up
 * — which is exactly how IGNITECLUBS slipped through uncaught — this
 * splits any category matching /^IGNITE([A-Z]+)$/ into "IGNITE " + Title
 * Case automatically, so a brand-new club feed is fixed the moment it
 * imports, with no category named IGNITEEVENTS, IGNITEADVOCACY,
 * IGNITECLUBS, or any other IGNITE+word variant ever landing in the
 * database going forward.
 *
 * $acronym_map below is now only a fallback for exceptions that don't fit
 * "IGNITE" + one plain word (e.g. an acronym that should stay all-caps
 * like "IGNITE HR" instead of becoming "IGNITE Hr"). Add entries there
 * only when the generic rule gets a specific case wrong.
 */
/**
 * Corrects a single club_acronym name if it needs it, otherwise returns it
 * unchanged. Shared by the live import filter below and the
 * `wp myignite fix-club-categories` cleanup command, so both apply exactly
 * the same rule and can't drift apart.
 *
 * @param string $name Raw term name, e.g. "IGNITECLUBS".
 * @return string Corrected term name, e.g. "IGNITE Clubs" — or the
 *                original string unchanged if it didn't match.
 */
function myignite_correct_club_acronym_name( $name ) {
	// Exceptions only — cases the generic IGNITE+word split below would
	// get wrong. Empty for now; every acronym seen so far fits the rule.
	$acronym_map = array();

	$trimmed = trim( $name );

	if ( isset( $acronym_map[ $trimmed ] ) ) {
		return $acronym_map[ $trimmed ];
	}

	if ( preg_match( '/^IGNITE([A-Z]+)$/', $trimmed, $matches ) ) {
		return 'IGNITE ' . ucfirst( strtolower( $matches[1] ) );
	}

	return $name;
}

/**
 * Originally hooked to `tribe_aggregator_save_event_args`, on the (wrong)
 * assumption that it mirrored the documented `categories`/`tags` schema
 * for other Aggregator filters. Confirmed via debug logging against a real
 * import that this filter never fires at all in the installed TEC version
 * — so this fix silently never ran, and every sync kept creating fresh
 * IGNITECLUBS/IGNITEEVENTS terms alongside whatever the retroactive
 * cleanup had already renamed.
 *
 * The filters that actually fire, confirmed the same way, with
 * $event['categories'] present as the raw string/array at that point:
 * `tribe_aggregator_before_save_event`, `before_insert_event` (new events),
 * and `before_update_event` (recurring events being re-synced — this is
 * the one that actually fired in testing, since the test event's UID
 * already existed from a prior sync).
 */
add_filter( 'tribe_aggregator_before_save_event', 'myignite_fix_club_acronym_categories' );
add_filter( 'tribe_aggregator_before_insert_event', 'myignite_fix_club_acronym_categories' );
add_filter( 'tribe_aggregator_before_update_event', 'myignite_fix_club_acronym_categories' );
function myignite_fix_club_acronym_categories( $event ) {

	if ( empty( $event['categories'] ) ) {
		return $event;
	}

	// Categories arrive as either a comma-separated string or an array
	// depending on Event Aggregator version — handle both.
	$was_string = ! is_array( $event['categories'] );
	$categories = $was_string
		? array_map( 'trim', explode( ',', $event['categories'] ) )
		: $event['categories'];

	$categories = array_map( 'myignite_correct_club_acronym_name', $categories );

	$event['categories'] = $was_string ? implode( ', ', $categories ) : $categories;

	return $event;
}


// -----------------------------------------------------------------------
// ICS IMPORT: STOP AUTO-CREATING/LINKING VENUE + ORGANIZER POSTS
// -----------------------------------------------------------------------

/**
 * Why this is needed:
 * Event Aggregator's ICS import auto-creates a `tribe_venue` and/or
 * `tribe_organizer` post for every distinct venue/organizer name it sees,
 * and links the event to them. CampusGroups' feed always sends the same
 * one or two organizer names and a small set of venue names, so every
 * import run was slowly accumulating archive-page posts nobody asked for.
 * We want the venue/organizer name to survive as plain text on the event,
 * with no linked post and no archive page created at all.
 *
 * Where TEC actually creates these posts (confirmed against the installed
 * plugin, TEC 6.16.5):
 *   wp-content/plugins/the-events-calendar/src/Tribe/Aggregator/Record/Abstract.php
 *   Venue post created ~line 1839, Organizer post created ~line 2023 —
 *   BOTH happen *before* the `tribe_aggregator_before_save_event` /
 *   `before_update_event` / `before_insert_event` filters (lines 2089,
 *   2104, 2140). Hooking any of those three is too late — by then the
 *   Venue/Organizer posts already exist in the database. The only point
 *   that runs early enough is `tribe_aggregator_translate_service_data`,
 *   applied in Tribe__Events__Aggregator__Event::translate_service_data()
 *   (src/Tribe/Aggregator/Event.php:208) — this fires immediately after
 *   the raw item is translated into the `$event` array and BEFORE
 *   Abstract.php's venue/organizer creation blocks even check for data
 *   to act on. Emptying `$event['Venue']` / `$event['Organizer']` there
 *   means those blocks never run at all: no matching, no creation, no
 *   linking.
 *
 * That filter only receives ($event, $item) — no origin/record info — so
 * we pair it with `tribe_aggregator_before_insert_posts`, which fires once
 * per import batch (Abstract.php ~line 1541, before its loop starts) and
 * does receive $meta['origin']. We use it to flag "this batch is an ICS
 * import" for the duration of that batch.
 *
 * Confirmed data shapes for ICS items (Event.php:67-207):
 *   $item->venue->venue        — venue name, single object.
 *   $item->organizer           — either a single stdClass or an array of
 *                                 them (one per organizer); each has
 *                                 ->organizer as the name.
 *
 * IMPORTANT — the real origin string is 'ical', not 'ics':
 * Event Aggregator has two distinct origins that both read .ics-format
 * calendar data:
 *   - 'ics'  → Tribe__Events__Aggregator__Record__ICS (uploading a local
 *              .ics FILE — a one-off, not what CampusGroups uses).
 *   - 'ical' → Tribe__Events__Aggregator__Record__iCal (polling a live
 *              .ics URL on a recurring schedule).
 * The CampusGroups feeds on this site (e.g. .../ical_club_35455.ics,
 * scheduled daily) are 'ical' origin, confirmed directly from an existing
 * import record's postmeta (_tribe_aggregator_origin = ical) and its
 * activity log (which shows a Venue and Organizer post actually being
 * created on a real run). Both origins are handled below, since either
 * could plausibly be used for a CampusGroups-style feed.
 */

$myignite_ea_is_ics_import = false;

add_action( 'tribe_aggregator_before_insert_posts', 'myignite_flag_ics_import_batch', 10, 2 );
function myignite_flag_ics_import_batch( $items, $meta ) {
	global $myignite_ea_is_ics_import;
	$myignite_ea_is_ics_import = isset( $meta['origin'] ) && in_array( $meta['origin'], array( 'ics', 'ical' ), true );
}

add_filter( 'tribe_aggregator_translate_service_data', 'myignite_strip_venue_organizer_for_ics', 10, 2 );
function myignite_strip_venue_organizer_for_ics( $event, $item ) {
	global $myignite_ea_is_ics_import;

	if ( empty( $myignite_ea_is_ics_import ) ) {
		return $event;
	}

	// Removing these keys entirely (not just the name) means TEC's venue/
	// organizer matching-or-create blocks in Abstract.php never trigger —
	// no post gets created, none gets linked.
	unset( $event['Venue'] );
	unset( $event['Organizer'] );

	return $event;
}

/**
 * Recovers paragraph breaks lost from $item->description.
 *
 * Confirmed via debug logging against real test imports: Event Aggregator's
 * "safe" $item->description has already collapsed every line break down to
 * a single space (indistinguishable from normal word spacing) by the time
 * any of our filters see it — regardless of whether the break was typed
 * with Enter or Shift+Enter, and regardless of which MyIGNITE description
 * field was used. $item->unsafe_description, however, still carries the
 * break — as a literal two-character "\n" escape sequence (backslash + n
 * as text), not an actual newline control character. Un-escaping that
 * back into a real newline recovers the paragraph structure entirely.
 *
 * Confirmed via debug logging that $event['post_content'] — not
 * $event['description'] — is the WordPress-native key Event Aggregator
 * actually uses to build the saved post. It's already present, already
 * populated from the lossy $item->description, before this filter ever
 * runs. Writing the recovered text to 'description' had zero effect since
 * nothing downstream reads that key.
 */
add_filter( 'tribe_aggregator_translate_service_data', 'myignite_recover_description_linebreaks', 10, 2 );
function myignite_recover_description_linebreaks( $event, $item ) {
	global $myignite_ea_is_ics_import;

	if ( empty( $myignite_ea_is_ics_import ) || empty( $item->unsafe_description ) ) {
		return $event;
	}

	// Literal "\r\n" / "\n" text (backslash followed by the letter, not a
	// real control character) — un-escape into actual line breaks.
	$recovered = str_replace( array( '\\r\\n', '\\n' ), array( "\n", "\n" ), $item->unsafe_description );

	$event['post_content'] = $recovered;

	return $event;
}


/**
 * Persists the venue/organizer name(s) as plain-text post meta on the
 * event, once it has a real ID.
 *
 * Timing: `tribe_aggregator_after_insert_post` (Abstract.php line 2261)
 * is the earliest point where $event['ID'] is reliably set for BOTH new
 * events (just created via tribe_create_event()) and updated ones — the
 * "before" filters above don't have an ID yet for new events. $item here
 * is still the original raw item, untouched by our unset() above (that
 * only modified the derived $event array), so the real names are intact.
 *
 * $record (3rd arg) is used instead of the global flag since this action
 * gets the actual record object with ->origin directly.
 */
add_action( 'tribe_aggregator_after_insert_post', 'myignite_save_plain_venue_organizer_names', 10, 3 );
function myignite_save_plain_venue_organizer_names( $event, $item, $record ) {
	if ( empty( $record->origin ) || ! in_array( $record->origin, array( 'ics', 'ical' ), true ) ) {
		return;
	}

	if ( empty( $event['ID'] ) ) {
		return;
	}

	if ( get_post_meta( $event['ID'], '_myignite_lock_from_import', true ) ) {
		return;
	}

	if ( ! empty( $item->venue->venue ) ) {
		update_post_meta( $event['ID'], '_myignite_venue_name', sanitize_text_field( $item->venue->venue ) );
	}

	if ( ! empty( $item->organizer ) ) {
		$organizer_entries = is_array( $item->organizer ) ? $item->organizer : array( $item->organizer );
		$names             = array();

		foreach ( $organizer_entries as $organizer_entry ) {
			if ( ! empty( $organizer_entry->organizer ) ) {
				$names[] = sanitize_text_field( $organizer_entry->organizer );
			}
		}

		if ( ! empty( $names ) ) {
			// Comma-separated plain text — good enough for however many
			// organizers CampusGroups lists on one event.
			update_post_meta( $event['ID'], '_myignite_organizer_names', implode( ', ', $names ) );
		}
	}
}

/**
 * If an event is locked (see myignite_render_import_meta_box() below),
 * CampusGroups' own tribe_create_event()/tribe_update_event() call has
 * already overwritten post_title/post_content/post_excerpt by the time this
 * fires — tribe_aggregator_after_insert_post is the earliest point with a
 * reliable post ID for both new and updated events (see the long comment
 * above myignite_save_plain_venue_organizer_names()), which is already too
 * late to intercept TEC's own save. Rather than guessing at the exact
 * earlier filter TEC uses internally to persist the event (undocumented and
 * unconfirmed without the plugin source in this repo), we correct the
 * content back to the locked snapshot immediately within the same import
 * cycle instead.
 *
 * Deliberately does NOT restore event dates: TEC's Custom Tables V1 keeps
 * date/time in a separate wp_tec_occurrences table, and a postmeta-only
 * restore here would leave that table and the post disagreeing about when
 * the event actually happens. If CampusGroups sends the wrong dates, fix
 * them at the source rather than relying on this lock.
 *
 * Priority 20 (after myignite_save_plain_venue_organizer_names's default 10)
 * so this always has the final say on venue/organizer meta for locked events.
 */
add_action( 'tribe_aggregator_after_insert_post', 'myignite_restore_locked_event_content', 20, 3 );
function myignite_restore_locked_event_content( $event, $item, $record ) {
	if ( empty( $event['ID'] ) ) {
		return;
	}

	if ( ! get_post_meta( $event['ID'], '_myignite_lock_from_import', true ) ) {
		return;
	}

	$snapshot = json_decode( get_post_meta( $event['ID'], '_myignite_import_lock_snapshot', true ), true );

	if ( empty( $snapshot ) ) {
		return;
	}

	wp_update_post( array(
		'ID'           => $event['ID'],
		'post_title'   => $snapshot['post_title'],
		'post_content' => $snapshot['post_content'],
		'post_excerpt' => $snapshot['post_excerpt'],
	) );

	update_post_meta( $event['ID'], '_myignite_venue_name', $snapshot['venue_name'] );
	update_post_meta( $event['ID'], '_myignite_organizer_names', $snapshot['organizer'] );
}


// -----------------------------------------------------------------------
// ADMIN UI: EDITABLE VENUE/ORGANIZER + LOCK FROM CAMPUSGROUPS IMPORT
// -----------------------------------------------------------------------

/**
 * Exposes the plain-text venue/organizer fields in a real meta box.
 *
 * Without this, _myignite_venue_name and _myignite_organizer_names are
 * invisible in wp-admin: WordPress hides `_`-prefixed postmeta keys from the
 * default Custom Fields panel, and these are only ever written
 * programmatically by myignite_save_plain_venue_organizer_names() above —
 * there was previously no way to edit them by hand at all.
 *
 * Also exposes the "lock" checkbox that protects an event's title,
 * description, venue, and organizer from being overwritten by the next
 * scheduled CampusGroups import (see myignite_restore_locked_event_content()
 * and the guard clause in myignite_save_plain_venue_organizer_names()).
 */
add_action( 'add_meta_boxes_tribe_events', 'myignite_register_import_meta_box' );
function myignite_register_import_meta_box() {
	add_meta_box(
		'myignite-campusgroups-import',
		'CampusGroups Import',
		'myignite_render_import_meta_box',
		'tribe_events',
		'side',
		'high'
	);
}

function myignite_render_import_meta_box( $post ) {
	wp_nonce_field( 'myignite_import_meta_box', 'myignite_import_meta_box_nonce' );

	$venue     = get_post_meta( $post->ID, '_myignite_venue_name', true );
	$organizer = get_post_meta( $post->ID, '_myignite_organizer_names', true );
	$locked    = get_post_meta( $post->ID, '_myignite_lock_from_import', true );
	?>
	<p>
		<label for="myignite_venue_name"><strong>Venue name</strong></label><br />
		<input type="text" id="myignite_venue_name" name="myignite_venue_name" class="widefat" value="<?php echo esc_attr( $venue ); ?>" />
	</p>
	<p>
		<label for="myignite_organizer_names"><strong>Organizer name(s)</strong></label><br />
		<input type="text" id="myignite_organizer_names" name="myignite_organizer_names" class="widefat" value="<?php echo esc_attr( $organizer ); ?>" />
	</p>
	<p>
		<label>
			<input type="checkbox" name="myignite_lock_from_import" value="1" <?php checked( $locked, '1' ); ?> />
			Lock title, description, venue &amp; organizer against the next CampusGroups import
		</label>
	</p>
	<p class="description">
		Dates are never locked — TEC stores those separately from this post,
		so a lock here can't keep them in sync. Fix wrong dates at the
		CampusGroups source instead.
	</p>
	<?php
}

function myignite_save_import_meta_box( $post_id, $post ) {
	if ( ! isset( $_POST['myignite_import_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['myignite_import_meta_box_nonce'], 'myignite_import_meta_box' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$venue     = isset( $_POST['myignite_venue_name'] ) ? sanitize_text_field( wp_unslash( $_POST['myignite_venue_name'] ) ) : '';
	$organizer = isset( $_POST['myignite_organizer_names'] ) ? sanitize_text_field( wp_unslash( $_POST['myignite_organizer_names'] ) ) : '';
	$locked    = ! empty( $_POST['myignite_lock_from_import'] );

	update_post_meta( $post_id, '_myignite_venue_name', $venue );
	update_post_meta( $post_id, '_myignite_organizer_names', $organizer );
	update_post_meta( $post_id, '_myignite_lock_from_import', $locked ? '1' : '' );

	// Refresh the protected snapshot every time a locked event is saved, so
	// the lock always protects the admin's latest edit rather than whatever
	// was on the post the moment the checkbox was first ticked.
	if ( $locked ) {
		update_post_meta( $post_id, '_myignite_import_lock_snapshot', wp_json_encode( array(
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'venue_name'   => $venue,
			'organizer'    => $organizer,
		) ) );
	}
}
add_action( 'save_post_tribe_events', 'myignite_save_import_meta_box', 10, 2 );


// -----------------------------------------------------------------------
// DISPLAY: REMOVE LINKS FROM VENUE AND ORGANIZER OUTPUT (TEC TEMPLATES)
// -----------------------------------------------------------------------

/**
 * Makes venue names display as plain text instead of clickable links, on
 * any TEC-rendered surface that still calls tribe_get_venue_link() (e.g.
 * widgets, embeds, the Venue block) — for events that DO have a real
 * linked Venue post (manually created, or imported before this change).
 *
 * IMPORTANT — this does NOT cover the site's actual single-event and
 * listing-card displays. Those are custom theme templates
 * (blocks/EventHero/EventHero.php, parts/card-tribe_events.php) that read
 * _EventVenueID postmeta directly and never call this TEC function at
 * all — see the fallback added to those two files for ICS-imported
 * events with no linked Venue post.
 *
 * Confirmed current signature (src/functions/template-tags/venue.php:321):
 *   apply_filters( 'tribe_get_venue_link', $link, $venue_id, $full_link, $url )
 * NOTE: the previous version of this filter used the WRONG argument
 * order ($link, $deprecated, $venue_id) — a leftover from an older TEC
 * signature. That meant get_the_title() was being called with the
 * $full_link boolean instead of the real venue ID. Fixed here.
 */
add_filter( 'tribe_get_venue_link', 'myignite_remove_venue_link', 10, 4 );
function myignite_remove_venue_link( $link, $venue_id, $full_link, $url ) {
	return esc_html( get_the_title( $venue_id ) );
}

/**
 * Makes organizer names display as plain text instead of clickable links,
 * on any TEC-rendered surface that still calls tribe_get_organizer_link().
 *
 * Same "doesn't cover the actual site templates" caveat as the venue
 * filter above applies here too.
 *
 * Confirmed current signature (src/functions/template-tags/organizer.php:319):
 *   apply_filters( 'tribe_get_organizer_link', $link, $post_id, $full_link, $url )
 * NOTE: $post_id here is the ORIGINAL argument passed into
 * tribe_get_organizer_link() (often an event ID), NOT the resolved
 * organizer post ID — unlike the venue equivalent. tribe_get_organizer_id()
 * resolves it correctly. The previous version of this filter assumed the
 * 3rd argument was the organizer ID; it was actually the $full_link
 * boolean. Fixed here.
 *
 * Also note: tribe_get_organizer_link() only reaches this filter at all
 * when Events Calendar Pro is active AND the organizer post is published
 * (src/functions/template-tags/organizer.php:356-380) — true for existing
 * linked organizers on this site, but irrelevant for ICS-imported events
 * going forward since they won't have a linked organizer post to begin
 * with.
 */
add_filter( 'tribe_get_organizer_link', 'myignite_remove_organizer_link', 10, 4 );
function myignite_remove_organizer_link( $link, $post_id, $full_link, $url ) {
	$organizer_id = tribe_get_organizer_id( $post_id );
	return esc_html( get_the_title( $organizer_id ) );
}


/**
 * ---------------------------------------------------------------------
 * SETUP — one-time steps, not code to run automatically:
 * ---------------------------------------------------------------------
 *
 * 1. Save this file as:
 *    wp-content/themes/YOUR-THEME/inc/helpers/myignite-image-sync.php
 *
 * 2. In your theme's functions.php, add this single line:
 *
 *    require_once 'inc/helpers/myignite-image-sync.php';
 *
 * 3. In the WP Engine User Portal, enable "Alternate cron" for this
 *    environment (Sites → [your install] → [environment] → Utilities →
 *    Advanced → Alternate cron toggle). This makes the hourly schedule
 *    registered above actually fire on time, instead of depending on
 *    someone visiting the site.
 *
 * 4. Test manually before waiting for the schedule. SSH in via WP
 *    Engine's SSH Gateway, navigate to the site, run:
 *
 *      wp myignite sync-images
 *
 *    Then check wp-content/myignite-image-sync.log to see exactly what
 *    happened, and check a real event in wp-admin to visually confirm
 *    the featured image actually landed.
 *
 * ---------------------------------------------------------------------
 * WIPE AND REIMPORT PROCEDURE (if resetting all event data):
 * ---------------------------------------------------------------------
 *
 * 1. Deploy this file with all changes FIRST, before wiping anything.
 * 2. Delete all existing events, venues, organizers, and tags via WP-CLI:
 *
 *      wp post delete $(wp post list --post_type=tribe_events --format=ids) --force
 *      wp post delete $(wp post list --post_type=tribe_venue --format=ids) --force
 *      wp post delete $(wp post list --post_type=tribe_organizer --format=ids) --force
 *      wp term delete $(wp term list post_tag --format=ids) --by=id
 *
 * 3. Manually trigger each Event Aggregator import from:
 *    wp-admin → Events → Import → [each feed] → Import Now
 * 4. Run the image sync immediately rather than waiting for the schedule:
 *      wp myignite sync-images
 * ---------------------------------------------------------------------
 */