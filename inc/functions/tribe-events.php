<?php

/**
 * Dequeue The Events Calendar editor styles.
 * Prevents incorrect iframe styles in the block editor.
 */
function takt_dequeue_tribe_editor_assets() {
	wp_dequeue_style( 'tribe-common-gutenberg-vendor-styles' );
	wp_dequeue_style( 'tribe-common-gutenberg-main-styles' );
	wp_dequeue_style( 'tribe-block-editor-main' );
	wp_dequeue_style( 'tec-widget-blocks-styles' );
}
add_action( 'enqueue_block_editor_assets', 'takt_dequeue_tribe_editor_assets', 200 );

/**
 * Strip role="main" from TEC's tribe-common wrapper div.
 *
 * TEC v2 (since 6.15.12.2) injects role="main" into the first element of the
 * rendered view HTML via a preg_replace inside Template_Bootstrap::maybe_add_main_landmark().
 * That ends up as <div class="tribe-common ..." role="main"> on every events
 * archive + single-event page. When that wrapper lands inside our theme's
 * <main id="wp--skip-link--target">, axe-core flags three rules on every TEC page:
 *   - landmark-main-is-top-level (TEC's main nested in our main)
 *   - landmark-no-duplicate-main (two main landmarks)
 *   - landmark-unique           (both lack distinguishing accessible names)
 *
 * Our <main> is the canonical main landmark — it owns the skip-link target.
 * TEC's role="main" is defensive (added for themes that don't provide one);
 * with our theme supplying <main>, dropping TEC's role is the right shape.
 *
 * The injection is a direct preg_replace, not a filterable attributes array,
 * so we hook the wrapping `tribe_events_views_v2_bootstrap_html` filter AFTER
 * TEC's own callback (TEC adds role at priority 100 — we strip at 200) and
 * remove the first occurrence of role="main" from the HTML.
 */
add_filter(
	'tribe_events_views_v2_bootstrap_html',
	static function ( $html ) {
		if ( ! is_string( $html ) || strpos( $html, 'role="main"' ) === false ) {
			return $html;
		}
		return preg_replace( '/\s+role="main"(?=\s|>)/', '', $html, 1 );
	},
	200,
	1
);

/**
 * Append a Related Events carousel after the single-event template.
 *
 * Instead of overriding ECP's `pro/related-events.php` template with markup
 * that duplicates the Dynamic Content Carousel, we render the actual DCC
 * block — events + related mode. The block owns the query, card template,
 * and Swiper carousel, so there's a single source of truth.
 *
 * `tribe_events_views_v2_bootstrap_html` filters the entire single-event
 * markup AFTER the `#tribe-events-pg-template` wrapper closes (see
 * Template_Bootstrap::get_v1_single_event_html()). Appending here places the
 * carousel OUTSIDE TEC's max-width content column, so the DCC block renders
 * full-width like it does elsewhere on the site rather than being clamped.
 *
 * do_blocks() is called explicitly because the bootstrap already ran
 * do_blocks() on its own output before this filter fires. The global post is
 * pointed at the queried event so the block's "related" mode resolves the
 * current event's `tribe_events_cat` terms.
 *
 * ECP's own Related Events output is separately suppressed by the no-op
 * `tribe-events/pro/related-events.php` template override.
 *
 * @param string $html Bootstrapped single-event / view HTML.
 * @return string
 */
function theme_append_related_events_carousel( $html ) {
	if ( ! is_singular( 'tribe_events' ) ) {
		return $html;
	}

	global $post;
	$post = get_queried_object();
	setup_postdata( $post );

	$carousel = do_blocks(
		'<!-- wp:takt/dynamic-content-carousel {"postsType":"tribe_events","postsSource":"related","heading":"Related Events","postsLimit":6,"showTags":true} /-->'
	);

	wp_reset_postdata();

	return $html . $carousel;
}
add_filter( 'tribe_events_views_v2_bootstrap_html', 'theme_append_related_events_carousel', 210, 1 );

/**
 * Strip the CampusGroups "— Event Details: URL" footer from event descriptions
 * at save time, so the database stores clean content rather than filtering it
 * on every page render.
 *
 * CampusGroups appends this to the ICS DESCRIPTION field automatically.
 * The URL is already surfaced as the "Visit Event Website" button (via _EventURL),
 * so the footer is purely redundant.
 *
 * Fires inside wp_insert_post() / wp_update_post(), which covers both
 * Event Aggregator imports and manual wp-admin saves.
 */
add_filter( 'wp_insert_post_data', 'theme_strip_campusgroups_event_details_footer' );
function theme_strip_campusgroups_event_details_footer( $data ) {
	if ( $data['post_type'] !== 'tribe_events' ) {
		return $data;
	}

	// Matches: optional whitespace · em dash (U+2014), en dash (U+2013), or a run of hyphens
	// (CampusGroups' real separator is three, "---", confirmed from raw import data — allow
	// any run so 1/2/3+ all match) · optionally "Event Details:" + URL · trailing whitespace.
	// The "Event Details: URL" part is optional because CampusGroups sometimes emits just the bare
	// separator with nothing after it (e.g. when an occurrence has no distinct detail-page URL to
	// link back to) — without this, the lone trailing dash was leaking into post_content/post_excerpt.
	// The 'u' flag treats the string as UTF-8 so the em/en dash match correctly.
	$pattern = '/\s*(?:\x{2013}|\x{2014}|-+)\s*(?:Event Details:\s*https?:\/\/\S+)?\s*$/u';

	$data['post_content'] = preg_replace( $pattern, '', $data['post_content'] );
	$data['post_excerpt'] = preg_replace( $pattern, '', $data['post_excerpt'] );

	return $data;
}
