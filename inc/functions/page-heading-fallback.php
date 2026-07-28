<?php
/**
 * Guarantee a single <h1> on plain 'page' post types.
 *
 * templates/single.html and templates/single-policy.html both include
 * takt/post-hero, which renders the post title as an <h1>. templates/page.html
 * has no such guarantee — it renders post_content as-is, so a page built
 * without a Hero block at the top (e.g. one that opens with a Text block)
 * ends up with zero <h1> elements on the page.
 */

if ( ! function_exists( 'takt_ensure_page_has_h1' ) ) {
	/**
	 * Prepend a visually-hidden <h1> using the page title if the rendered
	 * content doesn't already contain one.
	 *
	 * @param string $content Post content HTML.
	 * @return string Modified content.
	 */
	function takt_ensure_page_has_h1( $content ) {
		if ( ! is_page() || stripos( $content, '<h1' ) !== false ) {
			return $content;
		}

		$title = get_the_title();
		if ( '' === $title ) {
			return $content;
		}

		return '<h1 class="screen-reader-text">' . esc_html( $title ) . '</h1>' . $content;
	}
}

add_filter( 'the_content', 'takt_ensure_page_has_h1', 20 );
