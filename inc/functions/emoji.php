<?php

/**
 * Remove WordPress's emoji detection script and styles from the frontend.
 *
 * Worth ~26.5 KB per page load: 22.8 KB for wp-emoji-release.min.js plus
 * ~3.8 KB of inline loader/settings/CSS. The JS file is only fetched when a
 * browser fails the emoji support test — most real devices pass and skip it,
 * but Lighthouse's headless Chrome fails and downloads it, which is why it
 * shows up under "unused JavaScript" in PageSpeed reports.
 *
 * WP 7.0 defers the detection script: print_emoji_detection_script() runs on
 * wp_head (priority 7) but only registers _print_emoji_detection_script() on
 * wp_print_footer_scripts, which is why the markup lands before </body>.
 * Unhooking the wp_head callback stops that deferral being registered at all.
 *
 * The styles need the other half. wp_enqueue_emoji_styles() (on
 * wp_enqueue_scripts) bails early when nothing is hooked to
 * wp_print_styles/print_emoji_styles — core keeps that check specifically as
 * the supported opt-out — so removing the back-compat action is what
 * suppresses the inline wp-emoji-styles CSS.
 *
 * Frontend and oEmbed only. wp-admin and the block editor keep emoji support,
 * because wp_enqueue_emoji_styles() branches to admin_print_styles there.
 */
function takt_disable_frontend_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );

	// oEmbed iframes render through their own head/enqueue hooks.
	remove_action( 'embed_head', 'print_emoji_detection_script' );
	remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
}
add_action( 'init', 'takt_disable_frontend_emojis' );
