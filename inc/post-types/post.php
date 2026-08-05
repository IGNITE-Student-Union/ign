<?php

function theme_post_rewrite_rules( $wp_rewrite ) {
	// Order matters. PHP `+` keeps the left side's keys, and WP_Rewrite walks
	// rules in array order — first match wins. So pagination must be matched
	// before the post-permalink catch-all, otherwise /blog/page/2/ would be
	// interpreted as a post slug "page/2" and 404 the Posts-page archive.
	//
	// The post-permalink pattern is also tightened from `(.+?)` (matches any
	// number of slashes) to `([^/]+)` (single segment), so it can never again
	// swallow URLs that should belong to other rules.
	$new_rules = array(
		// pagename=blog tells WP this URL belongs to the page with that slug.
		// When the Posts page setting (page_for_posts) points at /blog/, WP
		// then switches the query to the home/posts loop. Without pagename
		// here, `?paged=N` would be interpreted as pagination of the front
		// page query and route to the wrong content.
		'blog/page/?([0-9]{1,})/?$' => 'index.php?pagename=blog&paged=' . $wp_rewrite->preg_index( 1 ),
		'blog/([^/]+)/?$'           => 'index.php?post_type=post&name=' . $wp_rewrite->preg_index( 1 ),
	);

	$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
}
add_action( 'generate_rewrite_rules', 'theme_post_rewrite_rules' );

// Bump this whenever theme_post_rewrite_rules changes so the cached rewrite
// rules get flushed once on the next request.
function theme_maybe_flush_post_rewrite_rules() {
	$version_key = 'theme_post_rewrite_rules_version';
	$current     = '3'; // 3 = pin pagename=blog to /blog/page/N/ rule
	if ( get_option( $version_key ) !== $current ) {
		flush_rewrite_rules( false );
		update_option( $version_key, $current );
	}
}
add_action( 'init', 'theme_maybe_flush_post_rewrite_rules', 99 );

function theme_change_blog_links($post_link, $id = 0){
    $post = get_post($id);

    // Only published posts have a stable slug and a canonical /blog/ URL to
    // redirect to. Draft/pending/future/auto-draft posts often have an empty
    // post_name, and WP core already builds a correct ?p={ID} link (and, for
    // previews, appends preview=true/nonce to it) for these statuses — let
    // that through instead of producing a malformed /blog//slug.
    if( is_object($post) && $post->post_type == 'post' && $post->post_status === 'publish' && $post->post_name !== '' ){
        return home_url('/blog/'. $post->post_name.'/');
    }

    return $post_link;
}
add_filter('post_link', 'theme_change_blog_links', 1, 3);

// --- Rename "Posts" menu to "Blog" ---

function theme_rename_posts_menu_label($args, $post_type) {
  if ($post_type !== 'post') return $args;

  $args['labels']['name'] = __('Blog', 'takt');
  $args['labels']['all_items'] = __('All Posts', 'takt');
  $args['labels']['menu_name'] = __('Blog', 'takt');

  return $args;
}
add_filter('register_post_type_args', 'theme_rename_posts_menu_label', 10, 2);

// --- Rename "Categories" to "Topics" ---

function theme_rename_category_labels($args, $taxonomy) {
  if ($taxonomy !== 'category') return $args;

  $args['labels'] = array_merge($args['labels'] ?? [], [
    'name'              => __('Topics', 'takt'),
    'singular_name'     => __('Topic', 'takt'),
    'search_items'      => __('Search Topics', 'takt'),
    'all_items'         => __('All Topics', 'takt'),
    'parent_item'       => __('Parent Topic', 'takt'),
    'parent_item_colon' => __('Parent Topic:', 'takt'),
    'edit_item'         => __('Edit Topic', 'takt'),
    'update_item'       => __('Update Topic', 'takt'),
    'add_new_item'      => __('Add New Topic', 'takt'),
    'new_item_name'     => __('New Topic Name', 'takt'),
    'menu_name'         => __('Topics', 'takt'),
  ]);

  return $args;
}
add_filter('register_taxonomy_args', 'theme_rename_category_labels', 10, 2);

// --- Disable Tags ---

function theme_unregister_post_tag() {
  unregister_taxonomy_for_object_type('post_tag', 'post');
}
add_action('init', 'theme_unregister_post_tag');

// --- Category (Topic) Accent Color Meta ---

function theme_register_category_meta() {
  register_term_meta('category', 'accent_color', [
    'type' => 'string',
    'single' => true,
    'show_in_rest' => true,
    'default' => '',
  ]);
}
add_action('init', 'theme_register_category_meta');