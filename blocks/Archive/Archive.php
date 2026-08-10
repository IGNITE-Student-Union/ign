<?php
/**
 * Archive block template.
 *
 * Renders a filterable archive of posts from a configured post type
 * with taxonomy filtering, search, pagination, and per-page controls.
 *
 * @var string|null $anchor
 * @var string      $blockVariation
 * @var string      $eyebrow
 * @var string      $heading
 * @var string      $description
 * @var array       $buttons
 * @var bool        $displayFilters
 * @var bool        $displaySort
 * @var bool        $displaySearch
 * @var bool        $displayPerPage
 * @var int         $postsPerPage
 * @var int         $maxColumns
 * @var string      $paginationMode
 * @var bool        $darkMode
 * @var array       $presetFilters
 * @var bool        $showTags
 *
 * @var WP_Block $block    Block instance
 * @var string   $children InnerBlocks content
 */

// Always dark mode — override saved attribute for existing blocks
$darkMode = true;

$post_type_config = [
	'post' => [
		'post_type'  => 'post',
		'taxonomies' => [
			'category' => 'topic',
		],
		'cpt_filters'    => [],
		'orderby'        => 'date',
		'order'          => 'desc',
		'card'           => 'post',
		'has_search'     => true,
		'has_sort'       => true,
	],
	'team_member' => [
		'post_type'  => 'team_member',
		'taxonomies' => [
			'department' => 'department',
		],
		'cpt_filters' => [],
		'orderby'     => 'meta_value_num',
		'order'       => 'asc',
		'card'        => 'team-member',
		'has_search'  => false,
		'meta_key'    => '_team_member_sort_order',
	],
	'resource' => [
		'post_type'  => 'resource',
		'taxonomies' => [
			'audience'      => 'audience',
			'resource_type' => 'resource-type',
		],
		'cpt_filters' => [],
		'orderby'     => 'date',
		'order'       => 'desc',
		'card'        => 'resource',
		'has_search'  => false,
	],
	'policy' => [
		'post_type'  => 'policy',
		'taxonomies' => [
			'policy_topic' => 'policy-topic',
		],
		'cpt_filters' => [],
		'orderby'     => 'meta_value_num',
		'order'       => 'asc',
		'card'        => 'policy',
		'has_search'  => false,
		'meta_key'    => '_policy_sort_order',
	],
];

$active_config      = $post_type_config[ $blockVariation ] ?? reset( $post_type_config );
$post_type          = $active_config['post_type'];
$enabled_taxonomies = $active_config['taxonomies'];
$card_slug          = $active_config['card'];
$default_orderby    = $active_config['orderby'];
$default_order      = $active_config['order'];
$max_cols           = $maxColumns;
$pagination_mode    = $paginationMode;
$posts_per          = $postsPerPage;
$show_filters  = ! empty( $displayFilters ) && ! empty( $enabled_taxonomies );
$show_search   = ! empty( $displaySearch ) && $active_config['has_search'];
$show_per_page = ! empty( $displayPerPage );
$show_sort     = ! empty( $active_config['has_sort'] ) && ! empty( $displaySort );

// ── Compute per-page options based on the configured posts-per-page value ──
$per_page_options = [];
for ( $i = 1; $i <= 5; $i++ ) {
	$per_page_options[] = $posts_per * $i;
}

// ── Collect Selected Filter Values ──
$has_selected_option = false;
$selected_options    = [];

// Normalize presetFilters: WordPress delivers object attributes as arrays
$preset_filters = [];
if ( ! empty( $presetFilters ) && is_array( $presetFilters ) ) {
	foreach ( $presetFilters as $slug => $terms ) {
		if ( is_array( $terms ) ) {
			$preset_filters[ $slug ] = array_values( array_filter( $terms, 'is_string' ) );
		}
	}
}

foreach ( $enabled_taxonomies as $taxonomy_name => $taxonomy_slug ) {
	$url_values = array_filter( explode( ',', get_query_var( $taxonomy_slug ) ) );
	if ( ! empty( $url_values ) ) {
		// URL params override presets
		$selected_options[ $taxonomy_slug ] = $url_values;
	} elseif ( ! empty( $preset_filters[ $taxonomy_slug ] ) ) {
		// Fall back to preset values when no URL params exist
		$selected_options[ $taxonomy_slug ] = $preset_filters[ $taxonomy_slug ];
	} else {
		$selected_options[ $taxonomy_slug ] = [];
	}
	if ( ! empty( $selected_options[ $taxonomy_slug ] ) ) {
		$has_selected_option = true;
	}
}

// ── Load Taxonomy Details and Terms ──
$taxonomy_details      = [];
$taxonomy_terms        = [];
$taxonomy_terms_labels = [];

foreach ( $enabled_taxonomies as $taxonomy_name => $taxonomy_slug ) {
	$taxonomy_details[ $taxonomy_slug ] = get_taxonomy( $taxonomy_name );
	$taxonomy_terms[ $taxonomy_slug ]   = get_terms( [ 'taxonomy' => $taxonomy_name, 'hide_empty' => true ] );
	if ( ! is_wp_error( $taxonomy_terms[ $taxonomy_slug ] ) ) {
		$taxonomy_terms_labels[ $taxonomy_slug ] = wp_list_pluck( $taxonomy_terms[ $taxonomy_slug ], 'name', 'slug' );
	} else {
		$taxonomy_terms[ $taxonomy_slug ]        = [];
		$taxonomy_terms_labels[ $taxonomy_slug ] = [];
	}
}

$current_page_taxonomy = null;
if ( is_tax() ) {
	$queried_object        = get_queried_object();
	$current_page_taxonomy = $queried_object->taxonomy ?? null;
}

// ── Per-page from URL (unified query var) ──
$per_page_var = 'show_per_page';
$url_per_page = intval( get_query_var( $per_page_var ) );
if ( $url_per_page > 0 && in_array( $url_per_page, $per_page_options ) ) {
	$posts_per = $url_per_page;
}

// ── Sort order ──
$sort_order_var = sanitize_text_field( get_query_var( 'sort_order' ) );
$current_order  = in_array( $sort_order_var, [ 'asc', 'desc' ], true ) ? $sort_order_var : $default_order;

// ── Build WP_Query ──
$paged = get_query_var( 'paged' ) ? intval( get_query_var( 'paged' ) ) : 1;

$args = [
	'post_type'      => $post_type,
	'paged'          => $paged,
	'posts_per_page' => $posts_per,
	'orderby'        => $default_orderby,
	'order'          => $default_order,
	'tax_query'      => [ 'relation' => 'AND' ],
	'post_status'    => 'publish',
];

if ( ! empty( $active_config['meta_key'] ) ) {
    $args['meta_key']   = $active_config['meta_key'];
    $args['meta_query'] = [
        'relation' => 'OR',
        [ 'key' => $active_config['meta_key'], 'compare' => 'EXISTS' ],
        [ 'key' => $active_config['meta_key'], 'compare' => 'NOT EXISTS' ],
    ];
}

// Apply current taxonomy page filter
if ( is_tax() && ! empty( $queried_object ) ) {
	$args['tax_query'][] = [
		'taxonomy' => $queried_object->taxonomy,
		'field'    => 'term_id',
		'terms'    => $queried_object->term_id,
	];
}

// Apply search
$search_query = get_query_var( 'search' );
if ( $search_query ) {
	$args['s'] = sanitize_text_field( $search_query );
}

// Apply sort order
if ( $show_sort && in_array( $sort_order_var, [ 'asc', 'desc' ], true ) ) {
	$args['order'] = strtoupper( $sort_order_var );
}

// Apply taxonomy filters
foreach ( $enabled_taxonomies as $taxonomy_name => $taxonomy_slug ) {
	if ( ! empty( $selected_options[ $taxonomy_slug ] ) ) {
		$tax = [ 'relation' => 'OR' ];
		foreach ( $selected_options[ $taxonomy_slug ] as $term ) {
			$tax[] = [
				'taxonomy' => $taxonomy_name,
				'field'    => 'slug',
				'terms'    => sanitize_text_field( $term ),
			];
		}
		$args['tax_query'][] = $tax;
	}
}

$query = new WP_Query( $args );
?>

<section <?php theme_block_props( [ 'archive' => true, 'py-gap-8' => ! $darkMode, 'dark bg-transparent!' => $darkMode ] ); ?>
	data-pagination-mode="<?php echo esc_attr( $pagination_mode ); ?>"
	data-post-type="<?php echo esc_attr( $post_type ); ?>"
	data-dark-mode="<?php echo esc_attr( $darkMode ? '1' : '0' ); ?>"
	data-preset-filters="<?php echo esc_attr( wp_json_encode( (object) $preset_filters ) ); ?>"
>
	<div class="<?php echo class_name( [ 'container grid grid-cols-1 gap-16' => true, 'relative py-6 sm:py-16 dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)' => $darkMode ] ); ?>">
			<?php
			get_template_part( 'parts/ThemeHeading', null, [
				'eyebrow'          => $eyebrow ?? '',
				'heading'          => $heading ?? '',
				'headingSize'      => 2,
				'description'      => $description ?? '',
				'buttons'          => $buttons,
				'columns'          => 2,
				'enableButtons'    => true,
			] );
			?>

			<div class="archive-inner grid grid-cols-1 gap-gap-4">
				<?php if ( $show_filters || $show_search || $show_per_page || $show_sort ) : ?>
					<div class="archive-filters grid grid-cols-1 gap-4">
						<form method="get" class="flex flex-wrap items-center gap-4" role="<?php echo $show_search ? 'search' : 'form'; ?>" aria-label="<?php esc_attr_e( 'Archive filters', 'takt' ); ?>">
							<input type="hidden" name="paged" value="1" />

							<?php if ( $show_filters ) : ?>
								<?php foreach ( $enabled_taxonomies as $taxonomy_name => $taxonomy_slug ) : ?>
									<?php
									if ( $taxonomy_name === $current_page_taxonomy ) {
										continue;
									}

									$current_taxonomy       = $taxonomy_details[ $taxonomy_slug ] ?? null;
									$current_taxonomy_terms = $taxonomy_terms[ $taxonomy_slug ] ?? [];
									$selected_terms         = $selected_options[ $taxonomy_slug ] ?? [];
									?>
									<?php if ( ! empty( $current_taxonomy ) && ! empty( $current_taxonomy_terms ) ) : ?>
										<div class="archive-filter-select max-sm:w-full">
											<input
												data-type="<?php echo esc_attr( $taxonomy_slug ); ?>"
												type="hidden"
												name="<?php echo esc_attr( $taxonomy_slug ); ?>"
												value="<?php echo esc_attr( implode( ',', $selected_terms ) ); ?>"
											>
											<select
												data-type="<?php echo esc_attr( $taxonomy_slug ); ?>"
												class="min-w-58"
												aria-label="<?php
												/* translators: %s: taxonomy label */
												echo esc_attr( sprintf( __( 'Filter by %s', 'takt' ), $current_taxonomy->labels->singular_name ) );
												?>"
												<?php if ( count( $current_taxonomy_terms ) === count( $selected_terms ) ) { echo 'disabled'; } ?>
											>
												<option value="" disabled selected>
													<?php
													printf(
														/* translators: %s: taxonomy label */
														esc_html__( 'Filter by %s', 'takt' ),
														esc_html( $current_taxonomy->labels->singular_name )
													);
													?>
												</option>
												<?php foreach ( $current_taxonomy_terms as $term ) : ?>
													<option
														value="<?php echo esc_attr( $term->slug ); ?>"
														<?php if ( in_array( $term->slug, $selected_terms, true ) ) { echo 'disabled'; } ?>
													>
														<?php echo esc_html( $term->name ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
									<?php endif; ?>
								<?php endforeach; ?>
							<?php endif; ?>

							<?php if ( $show_search ) : ?>
								<div class="max-sm:w-full min-w-58 grid">
									<input
										type="text"
										data-type="search"
										name="search"
										placeholder="<?php esc_attr_e( 'Keyword', 'takt' ); ?>"
										value="<?php echo esc_attr( $search_query ); ?>"
										class="col-1 row-1 pr-12"
									>
									<button type="submit" class="col-1 row-1 justify-self-end self-center mr-3 *:w-6 *:h-auto pointer-events-auto bg-transparent border-0 cursor-pointer p-0" aria-label="<?php esc_attr_e( 'Search', 'takt' ); ?>">
										<?php theme_block_asset( 'IconSearch.svg' ); ?>
									</button>
								</div>
							<?php endif; ?>

							<?php if ( $show_sort || $show_per_page ) : ?>
								<div class="ml-auto flex items-center gap-4 shrink-0 max-sm:hidden">
									<?php if ( $show_sort ) : ?>
										<select data-type="sort_order" name="sort_order" aria-label="<?php esc_attr_e( 'Sort order', 'takt' ); ?>">
											<option value="desc" <?php selected( $current_order, 'desc' ); ?>><?php esc_html_e( 'Newest to Oldest', 'takt' ); ?></option>
											<option value="asc" <?php selected( $current_order, 'asc' ); ?>><?php esc_html_e( 'Oldest to Newest', 'takt' ); ?></option>
										</select>
									<?php endif; ?>
									<?php if ( $show_per_page ) : ?>
										<div class="flex items-center gap-2 whitespace-nowrap">
											<span class="text-body-small uppercase font-medium tracking-wider"><?php esc_html_e( 'Show', 'takt' ); ?></span>
											<select data-type="per_page" name="<?php echo esc_attr( $per_page_var ); ?>" class="w-28 text-center" aria-label="<?php esc_attr_e( 'Items per page', 'takt' ); ?>">
												<?php foreach ( $per_page_options as $option ) : ?>
													<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $posts_per, $option ); ?>>
														<?php echo esc_html( $option ); ?>
													</option>
												<?php endforeach; ?>
											</select>
											<span class="text-body-small uppercase font-medium tracking-wider"><?php esc_html_e( 'Per Page', 'takt' ); ?></span>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</form>

						<?php if ( $show_filters && $displayFilters ) : ?>
							<div class="archive-active-filters flex flex-wrap items-center gap-3">
								<?php if ( $show_filters && $displayFilters ) : ?>
									<?php foreach ( $enabled_taxonomies as $taxonomy_name => $taxonomy_slug ) : ?>
										<?php if ( $taxonomy_name === $current_page_taxonomy ) { continue; } ?>
										<?php if ( ! empty( $selected_options[ $taxonomy_slug ] ) ) : ?>
											<?php foreach ( $selected_options[ $taxonomy_slug ] as $term ) : ?>
												<?php $label = $taxonomy_terms_labels[ $taxonomy_slug ][ $term ] ?? ''; ?>
												<?php if ( ! empty( $label ) ) : ?>
													<?php if ( ! empty( $preset_filters[ $taxonomy_slug ] ) && in_array( $term, $preset_filters[ $taxonomy_slug ], true ) ) : ?>
														<span class="archive-preset-pill inline-flex items-center border border-current rounded-full px-3 py-1 text-body-small uppercase font-medium tracking-wider">
															<?php echo esc_html( $label ); ?>
														</span>
													<?php else : ?>
														<button type="button" class="inline-flex items-center gap-1 border border-current rounded-full px-3 py-1 text-body-small uppercase font-medium tracking-wider cursor-pointer" data-field="<?php echo esc_attr( $taxonomy_slug ); ?>" data-value="<?php echo esc_attr( $term ); ?>">
															<span><?php echo esc_html( $label ); ?></span>
															<div class="archive-remove-filter"></div>
														</button>
													<?php endif; ?>
												<?php endif; ?>
											<?php endforeach; ?>
										<?php endif; ?>
									<?php endforeach; ?>
								<?php endif; ?>
								<button type="button" data-clear-all="1" class="text-body-small uppercase font-medium tracking-wider cursor-pointer hover:underline focus:underline" aria-label="<?php esc_attr_e( 'Clear all filters', 'takt' ); ?>">
									<?php esc_html_e( 'Clear All', 'takt' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php /* Loading Spinner */ ?>
				<div class="archive-loading hidden flex justify-center py-12" role="status" aria-label="<?php esc_attr_e( 'Loading results', 'takt' ); ?>">
					<?php theme_block_asset( 'loading.svg' ); ?>
				</div>

				<?php /* Results */ ?>
				<div class="archive-results" role="region" aria-live="polite" aria-label="<?php esc_attr_e( 'Archive results', 'takt' ); ?>" tabindex="-1">
					<?php if ( $query->have_posts() ) : ?>
						<?php
						$grid_classes = 'grid gap-x-6 gap-y-8';
						$grid_classes .= $post_type === 'team_member' ? ' grid-cols-2' : ' grid-cols-1';
						if ( $post_type === 'post' ) { $grid_classes .= ' max-md:gap-y-16'; }
						if ( $max_cols >= 2 ) { $grid_classes .= ' sm:grid-cols-2'; }
						if ( $max_cols >= 3 ) { $grid_classes .= ' md:grid-cols-3'; }
						if ( $max_cols >= 4 ) { $grid_classes .= ' lg:grid-cols-4'; }
						?>
						<div class="<?php echo esc_attr( $grid_classes ); ?>">
							<?php while ( $query->have_posts() ) : ?>
								<?php
								$query->the_post();
								get_template_part( 'parts/card', $card_slug, [ 'showTags' => $showTags ] );
								?>
							<?php endwhile; ?>
						</div>

						<?php if ( $pagination_mode === 'load-more' ) : ?>
							<?php if ( $query->max_num_pages > $paged ) : ?>
								<div class="archive-load-more flex justify-center mt-12">
									<button
										type="button"
										class="btn-secondary"
										data-next-page="<?php echo esc_attr( $paged + 1 ); ?>"
										data-max-pages="<?php echo esc_attr( $query->max_num_pages ); ?>"
									>
										<?php esc_html_e( 'Load More', 'takt' ); ?>
									</button>
								</div>
							<?php endif; ?>
						<?php else : ?>
							<div class="archive-pagination flex flex-col sm:flex-row items-center sm:justify-between gap-4 mt-6">
								<div class="text-body-small">
									<?php
									$per_page = $query->query_vars['posts_per_page'];
									$total    = $query->found_posts;
									$from     = ( ( $paged - 1 ) * $per_page ) + 1;
									$to       = min( $from + $per_page - 1, $total );

									printf(
										/* translators: %1$d: start number, %2$d: end number, %3$d: total */
										esc_html__( 'Showing %1$d to %2$d of %3$d entries', 'takt' ),
										$from,
										$to,
										$total
									);
									?>
								</div>

								<?php if ( $query->max_num_pages > 1 ) : ?>
									<nav class="navigation pagination" aria-label="<?php esc_attr_e( 'Pagination', 'takt' ); ?>">
										<div class="nav-links">
											<?php
											echo wp_kses_post( paginate_links( [
												'base'      => str_replace( PHP_INT_MAX, '%#%', get_pagenum_link( PHP_INT_MAX, false ) ),
												'current'   => $paged,
												'total'     => $query->max_num_pages,
												'prev_text' => '<span>' . __( 'Prev', 'takt' ) . '</span>',
												'next_text' => '<span>' . __( 'Next', 'takt' ) . '</span>',
												'end_size'  => 0,
												'mid_size'  => 0,
											] ) );
											?>
										</div>
									</nav>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="text-header-5 text-center py-12">
							<?php esc_html_e( 'No results found', 'takt' ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
	</div>
</section>
<?php wp_reset_postdata(); ?>
