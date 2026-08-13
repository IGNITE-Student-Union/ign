<?php

// Load front-end assets.
function takt_assets() {
	$asset = include get_theme_file_path( 'public/css/screen.asset.php' );

	wp_enqueue_style(
		'takt',
		get_theme_file_uri( 'public/css/screen.css' ),
		$asset['dependencies'],
		$asset['version']
	);

	wp_enqueue_script(
		'takt',
		get_theme_file_uri( 'public/js/screen.js' ),
		$asset['dependencies'],
		$asset['version'],
		true
	);
}
add_action( 'wp_enqueue_scripts', 'takt_assets' );

/**
 * Dequeue The Events Calendar / Events Calendar Pro's frontend CSS bundle on
 * pages that don't actually render any of its markup.
 *
 * The Featured Events and Dynamic Content Carousel blocks query `tribe_events`
 * posts directly and render them through the theme's own card templates
 * (parts/card-tribe_events*.php) — none of TEC's CSS classes are used. TEC/ECP
 * still self-enqueue their full frontend bundle (common + views + tooltipster
 * + bootstrap-datepicker + the Mini Calendar block's styles) on every request
 * regardless. On a page like the homepage that's ~9 extra render-blocking
 * stylesheet requests for styles nothing on the page references.
 *
 * Real TEC views still need the bundle: the events archive and single-event
 * page (themed via tribe-events/single-event.php) are excluded via
 * tribe_is_event_query()/is_singular(), and any page where an editor has
 * actually placed one of TEC's own blocks is excluded via has_block() so
 * that markup doesn't lose its styling.
 *
 * Runs at priority 20 — after TEC registers its styles (priority 10) but
 * before takt_assets_after_tec() (priority 100) decides whether to wire
 * 'takt' up as a dependent of tribe-events-views-v2-full.
 */
function takt_dequeue_unused_tribe_styles() {
	if ( ! function_exists( 'tribe_is_event_query' ) ) {
		return;
	}

	if ( tribe_is_event_query() || is_singular( 'tribe_events' ) ) {
		return;
	}

	$tec_block_names = [
		'tribe/events-list',
		'tribe/events-pro-mini-calendar',
		'tribe/rsvp',
		'tribe/tickets',
		'tribe/event-countdown',
		'tribe/event-schedule',
		'tribe/events-single-venue',
		'tribe/events-single-organizer',
	];

	foreach ( $tec_block_names as $block_name ) {
		if ( has_block( $block_name ) ) {
			return;
		}
	}

	$handles = [
		'tribe-events-pro-mini-calendar-block-styles',
		'tec-variables-skeleton',
		'tec-variables-full',
		'tribe-common-skeleton-style',
		'tribe-common-full-style',
		'tribe-events-views-v2-bootstrap-datepicker-styles',
		'tribe-tooltipster-css',
		'tribe-events-views-v2-skeleton',
		'tribe-events-views-v2-full',
	];

	foreach ( $handles as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}
}
// Disabled ahead of launch. The saving is ~24 KB gzipped of render-blocking
// CSS, which is not worth the failure modes on launch day:
//
//   - It works by having wp_deregister_style() make the `registered` half of
//     takt_assets_after_tec()'s guard fail, so 'takt' is never wired up as a
//     dependent. That ordering (priority 20 before 100) is load-bearing and
//     silent if broken — the theme stylesheet would stop printing entirely.
//   - Only has_block() is checked, so a page using a TEC shortcode or widget
//     would render its calendar unstyled. No page does today, but content is
//     editable post-launch.
//   - has_block() reads the global $post, which is unreliable on archives.
//
// To re-enable: uncomment, then verify the events archive, a single event, and
// a page embedding a calendar all still render styled.
// add_action( 'wp_enqueue_scripts', 'takt_dequeue_unused_tribe_styles', 20 );

/**
 * Ensure the theme stylesheet loads after The Events Calendar.
 *
 * TEC registers its styles via tribe_asset() at priority 10.
 * This runs later to add the dependency once TEC's handle exists.
 */
function takt_assets_after_tec() {
	if ( ! wp_style_is( 'tribe-events-views-v2-full', 'enqueued' ) && ! wp_style_is( 'tribe-events-views-v2-full', 'registered' ) ) {
		return;
	}

	$style = wp_styles()->query( 'takt' );
	if ( $style && ! in_array( 'tribe-events-views-v2-full', $style->deps, true ) ) {
		$style->deps[] = 'tribe-events-views-v2-full';
	}
}
add_action( 'wp_enqueue_scripts', 'takt_assets_after_tec', 100 );

/**
 * Preload the primary body font.
 *
 * General Sans is a single variable font (weight 200–700) covering the
 * regular-style text on every page. It's declared in screen/fonts.css, so
 * without a preload the browser only discovers it after screen.css has
 * downloaded and been parsed. Preloading lets the font fetch start in
 * parallel with the CSS instead of after it — purely additive, no existing
 * behaviour changes. The italic cut is used far less and is left unpreloaded.
 */
function takt_preload_fonts() {
	printf(
		'<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
		esc_url( get_theme_file_uri( 'public/fonts/GeneralSans-Variable.woff2' ) )
	);
}
add_action( 'wp_head', 'takt_preload_fonts', 1 );

/**
 * Defer Smush's lazy-load script.
 *
 * wp-smushit enqueues smush-lazy-load.min.js render-blocking; it only wires
 * up an IntersectionObserver on DOMContentLoaded, so deferring it is safe —
 * it isn't needed before the DOM exists — and takes it off the critical
 * rendering path. Matched on $src rather than $handle since the plugin's
 * exact handle name isn't something this theme controls.
 */
function takt_defer_smush_lazy_load( $tag, $handle, $src ) {
	if ( false === strpos( $src, 'smush-lazy-load' ) ) {
		return $tag;
	}

	if ( false !== strpos( $tag, ' defer' ) ) {
		return $tag;
	}

	return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'takt_defer_smush_lazy_load', 10, 3 );

// Load editor stylesheets.
function takt_editor_styles() {
	// Anton's @font-face now lives in screen/fonts.css (self-hosted, see
	// takt_assets()), so this one editor style also covers the heading font.
	add_editor_style( 'public/css/screen.css' );
}
add_action( 'after_setup_theme', 'takt_editor_styles' );

// Apply the theme's `.discourse` typography scope to the Classic (TinyMCE)
// editor body so bare h1–h6 / p / ul / ol / blockquote pick up the same
// heading/sans tokens authors see on the frontend. Gutenberg applies the
// discourse styling through its own block wrappers and is unaffected.
function takt_tinymce_discourse_body_class( $init ) {
	$existing = isset( $init['body_class'] ) ? $init['body_class'] : '';
	$init['body_class'] = trim( $existing . ' discourse' );
	return $init;
}
add_filter( 'tiny_mce_before_init', 'takt_tinymce_discourse_body_class' );

// Load editor scripts.
function takt_editor_assets() {
	$script_asset = include get_theme_file_path( 'public/js/editor.asset.php' );
	$style_asset  = include get_theme_file_path( 'public/css/editor.asset.php' );

	wp_enqueue_script(
		'takt-editor',
		get_theme_file_uri( 'public/js/editor.js' ),
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);
	wp_enqueue_style(
		'takt-editor',
		get_theme_file_uri( 'public/css/editor.css' ),
		$style_asset['dependencies'],
		$style_asset['version']
	);
}
add_action( 'enqueue_block_editor_assets', 'takt_editor_assets' );

// Load admin assets (wp-admin screens, not Gutenberg).
function takt_admin_assets() {
	$css_asset_file = get_theme_file_path( 'public/css/admin.asset.php' );
	$js_asset_file  = get_theme_file_path( 'public/js/admin.asset.php' );

	if ( file_exists( $css_asset_file ) ) {
		$css_asset = include $css_asset_file;
		wp_enqueue_style(
			'takt-admin',
			get_theme_file_uri( 'public/css/admin.css' ),
			$css_asset['dependencies'],
			$css_asset['version']
		);
	}

	if ( file_exists( $js_asset_file ) ) {
		$js_asset = include $js_asset_file;
		wp_enqueue_script(
			'takt-admin',
			get_theme_file_uri( 'public/js/admin.js' ),
			$js_asset['dependencies'],
			$js_asset['version'],
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'takt_admin_assets' );
