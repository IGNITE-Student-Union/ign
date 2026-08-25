<?php
/**
 * TEC's SEO Headers Controller hard-404s month/day views whose eventDate falls
 * outside the site's known event date range, but only list view honors the
 * "soft noindex" setting (Settings > Display > SEO & URL Handling) — day and
 * month views always hard-404 regardless of that setting, even though TEC
 * itself still renders a "no events this month" fallback for the same view.
 * That's why a direct load of e.g. /events/month/2026-07/ 404s while the
 * same view reached via in-page AJAX navigation renders fine.
 *
 * The same out-of-range condition also leaves is_post_type_archive/is_archive
 * false on $wp_query (that's what TEC's own 404 check keys off of). This
 * site's events page is a custom Site Editor template ("archive-events",
 * wrapping the tec/archive-events block) that only gets selected when WP
 * recognizes the request as a real post-type archive — with those flags
 * false, WordPress resolves a different template instead and the whole page
 * — hero, calendar, everything — renders blank. So both flags need
 * restoring alongside is_404, not is_404 alone.
 *
 * Reverse this specifically for that out-of-range case; every other 404
 * TEC's controller can set (disabled views, single-event requests) is left
 * alone.
 *
 * @see TEC\Events\SEO\Headers\Controller::check_month_view()
 * @see TEC\Events\SEO\Headers\Controller::check_day_view()
 */
add_action( 'send_headers', function () {
	global $wp_query;

	if ( empty( $wp_query->is_404 ) || ! $wp_query->is_main_query() ) {
		return;
	}

	$query = $wp_query->query;

	if ( ! isset( $query['post_type'], $query['eventDisplay'], $query['eventDate'] )
		|| $query['post_type'] !== 'tribe_events'
		|| ! in_array( $query['eventDisplay'], [ 'month', 'day' ], true )
		|| ! function_exists( 'tribe_get_option' )
	) {
		return;
	}

	$enabled_views = tribe_get_option( 'tribeEnableViews', [] );
	if ( ! in_array( $query['eventDisplay'], $enabled_views, true ) ) {
		return; // Disabled-view 404 — leave TEC's own guard in place.
	}

	if ( 'day' === $query['eventDisplay'] ) {
		$event_timestamp = strtotime( $query['eventDate'] );
		$earliest        = tribe_events_earliest_date( 'Y-m-d' );
		$latest          = tribe_events_latest_date( 'Y-m-d' );
		$out_of_range    = $earliest && $latest
			&& ( strtotime( $earliest ) > $event_timestamp || strtotime( $latest ) < $event_timestamp );
	} else {
		$earliest     = tribe_events_earliest_date( 'Y-m' );
		$latest       = tribe_events_latest_date( 'Y-m' );
		$out_of_range = $earliest && $latest
			&& ( $earliest > $query['eventDate'] || $latest < $query['eventDate'] );
	}

	if ( ! $out_of_range ) {
		return;
	}

	$wp_query->is_404              = false;
	$wp_query->is_post_type_archive = true;
	$wp_query->is_archive           = true;
	status_header( 200 );
}, 20 );
