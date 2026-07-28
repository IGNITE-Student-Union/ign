<?php

function takt_register_blocks() {
	$directory = new RecursiveDirectoryIterator( __DIR__ . '/../../blocks' );
	$iterator = new RecursiveIteratorIterator( $directory );

	foreach ( $iterator as $file ) {
		if ( $file->getFilename() !== 'block.json' ) {
			continue;
		}

		$args = [];
		$block_folder = $file->getPath();
		$folder_name = basename( $block_folder );
		$template_file = "$block_folder/$folder_name.php";

		if ( file_exists( $template_file ) ) {
			$args['render_callback'] = function ( $attributes, $children, $block ) use ( $template_file, $block_folder ) {
				$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block->name );
				if ( $block_type && is_array( $block_type->attributes ) ) {
					foreach ( $block_type->attributes as $key => $schema ) {
						if ( ! array_key_exists( $key, $attributes ) ) {
							$attributes[ $key ] = $schema['default'] ?? match ( $schema['type'] ?? '' ) {
								'string'            => '',
								'boolean'           => false,
								'number', 'integer' => 0,
								'array'             => [],
								'object'            => [],
								default             => null,
							};
						}
					}
				}

				if ( is_array( $attributes ) ) {
					extract( $attributes, EXTR_SKIP );
				}

				$context = $block->context ?? [];

				if ( ! empty( $context ) ) {
					extract( $context, EXTR_SKIP );
				}

				if ( empty( $anchor ) ) {
					$anchor = 'auto-' . preg_replace( '/[^A-Za-z0-9]/', '', strtolower( md5( json_encode( $block ) ) ) );

					// Two block instances with identical attributes (e.g. an empty Button
					// Row) hash to the same anchor. Disambiguate so ids stay unique on the
					// page, which aria-labelledby/aria-controls references depend on.
					global $takt_used_block_anchors;
					$takt_used_block_anchors = $takt_used_block_anchors ?? [];

					$base_anchor = $anchor;
					$suffix      = 2;
					while ( isset( $takt_used_block_anchors[ $anchor ] ) ) {
						$anchor = $base_anchor . '-' . $suffix;
						++$suffix;
					}
					$takt_used_block_anchors[ $anchor ] = true;
				}

				global $takt_current_block_folder;
				global $takt_current_block_id;
				global $takt_current_block_attributes;
				$takt_current_block_folder = realpath( $block_folder );
				$takt_current_block_id = $anchor;
				$takt_current_block_attributes = $attributes;

				ob_start();
				include $template_file;
				$output = ob_get_clean();

				unset( $takt_current_block_folder );
				unset( $takt_current_block_id );
				return $output;
			};
		}

		register_block_type( $block_folder, $args );
	}
}
add_action( 'init', 'takt_register_blocks' );

function theme_block_props( $classes = [], $props = [] ) {
	global $takt_current_block_id;
	global $takt_current_block_attributes;
	$args = [];

	// Append ID
	$args['id'] = esc_attr( $takt_current_block_id );

	// Append Aria Labelledby
	if ( ! empty( $takt_current_block_attributes['heading'] ) && ! isset( $props['aria-labelledby'] ) ) {
		$args['aria-labelledby'] = theme_block_region_id();
	}

	// Normalize $classes to array with keys
	if ( ! is_array( $classes ) ) {
		$classes = [ $classes => true ];
	}

  // Remove discourse style
  if (!isset($classes['not-discourse'])){
    $classes['not-discourse'] = true;
  }

	// Append class list
	$classList = class_name( $classes );
	if ( ! empty( $classList ) ) {
		$args['class'] = esc_attr( $classList );
	}

	// Normalize $props if it's a string
	if ( is_string( $props ) ) {
		$attrs = [];
		preg_match_all( '/([a-zA-Z0-9_\-:]+)\s*=\s*"([^"]*)"/', $props, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$attrs[ $match[1] ] = $match[2];
		}
		$props = $attrs;
	}

	// Append the props
	if ( is_array( $props ) && ! empty( $props ) ) {
		$args = array_merge( $props, $args );
		$args = array_filter(
			$args,
			function ( $value ) {
				return $value !== false && $value !== null;
			}
		);
	}

	// Output
	echo get_block_wrapper_attributes( $args );
}

function theme_block_region_id() {
	global $takt_current_block_id;
	$region_id = $takt_current_block_id . '-heading';
	return $region_id;
}


/**
 * Loads the contents of a block asset file from the public build directory and optionally echoes it.
 *
 * @param string $relative_file_path The relative path to the asset file within the block's folder.
 * @param bool   $echo               Whether to echo the asset contents (default: true). If false, returns the contents.
 * @param string $block_folder_path  Optional. The absolute path to the block folder. If not provided, uses the current block folder.
 * @return string The asset contents if $echo is false, otherwise an empty string.
 */
function theme_block_asset( $relative_file_path, $echo = true, $block_folder_path = '' ) {
	global $takt_current_block_folder;

	$folder_path = $block_folder_path ?: $takt_current_block_folder;

	if ( empty( $folder_path ) ) {
		return '';
	}

	$public_folder = get_template_directory() . '/public/blocks';
	$blocks_root = realpath( __DIR__ . '/../../blocks' ) . '/';
	$block_path = str_replace( $blocks_root, '', $folder_path );
	$block_path = str_replace( '\\', '/', $block_path );
	$full_path = "$public_folder/$block_path/$relative_file_path";

	if ( ! file_exists( $full_path ) ) {
		return '';
	}

	$asset = file_get_contents( $full_path );

	if ( $echo ) {
		$asset = preg_replace( '/<\?xml.*?\?>\s*/', '', $asset );
		if ( function_exists( 'theme_mark_svg_decorative' ) ) {
			$asset = theme_mark_svg_decorative( $asset );
		}
		echo $asset;
	} else {
		return $asset;
	}
}


/**
 * Returns the public URL for a block asset file in the public build directory.
 *
 * @param string $relative_file_path The relative path to the asset file within the block's folder.
 * @param string $block_folder_path  Optional. The absolute path to the block folder. If not provided, uses the current block folder.
 * @return string The public URL to the asset file.
 */
function theme_block_asset_url( $relative_file_path, $block_folder_path = '' ) {
	global $takt_current_block_folder;

	$folder_path = $block_folder_path ?: $takt_current_block_folder;

	if ( empty( $folder_path ) ) {
		return '';
	}

	$public_folder_url = get_template_directory_uri() . '/public/blocks';
	$blocks_root = realpath( __DIR__ . '/../../blocks' ) . '/';
	$block_path = str_replace( $blocks_root, '', $folder_path );
	$block_path = str_replace( '\\', '/', $block_path );
	$url = "$public_folder_url/$block_path/$relative_file_path";

	return $url;
}


/**
 * Adds a category for the theme's blocks
 *
 * @param array $categories array with the block categories.
 */
function theme_block_categories( $categories, $editor_context ) {
	$theme_categories = array(
		array(
			'slug'  => 'takt-theme',
			'title' => __( 'Modules', 'takt' ),
		),
		array(
			'slug'  => 'takt-theme-template',
			'title' => __( 'Template Components', 'takt' ),
		),
	);
	$theme_selected = array(
		array(
			'slug'  => 'takt-selected',
			'title' => __( 'Content', 'takt' ),
		),
	);

	$custom_categories = array_merge( $theme_categories, $theme_selected );

	return array_merge(
		$custom_categories,
		$categories
	);
}
add_action( 'block_categories_all', 'theme_block_categories', 10, 2 );


/**
 * Limit allowed blocks for headers
 *
 * @param array  $allowed_blocks .
 * @param object $editor_context .
 */
function theme_allow_blocks( $allowed_blocks, $editor_context ) {
	$post_type = isset( $editor_context->post ) ? $editor_context->post->post_type : '';

	// Hero blocks that only make sense inside their matching single-post template.
	// Restrict them in the per-post editor so authors can't drop them onto a page.
	// In the Site Editor (no `post` on the context, or wp_template post type) these
	// blocks stay available so they can be placed inside the right template.
	$hero_restrictions = array(
		'takt/event-hero' => array( 'tribe_events' ),
		'takt/post-hero'  => array( 'post' ),
	);

	$all_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
	$filtered_blocks = array();

	foreach ( $all_blocks as $block ) {
		if ( ! str_starts_with( $block->name, 'takt/' ) ) {
			continue;
		}

		if ( isset( $hero_restrictions[ $block->name ] )
			&& ! empty( $post_type )
			&& $post_type !== 'wp_template'
			&& ! in_array( $post_type, $hero_restrictions[ $block->name ], true ) ) {
			continue;
		}

		array_push( $filtered_blocks, $block->name );
	}

	return array_merge(
		$filtered_blocks,
		array(
			'core/block',
			'core/template-part',
			'core/post-content',
			'core/paragraph',
			'core/heading',
			'core/quote',
			'core/list',
			'core/list-item',
			'core/image',
			'core/html',
			'core/freeform',
			'core/table',
			'core/gallery',
			'core/audio',
			'core/columns',
			'core/spacer',
			'core/group',
			'tribe/event-datetime',
			'tribe/featured-image',
			'tribe/event-links',
			'tribe/classic-event-details',
			'tribe/event-venue',
		)
	);
}
add_filter( 'allowed_block_types_all', 'theme_allow_blocks', 20, 2 );
