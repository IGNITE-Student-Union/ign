<?php
/**
 * Search block template.
 *
 * Renders a site-wide search that queries all public post types
 * with post-type filtering, keyword search, and pagination.
 *
 * Available variables (auto-extracted from $attributes):
 * @var string $anchor
 * @var string $heading
 * @var string $searchPlaceholder
 * @var bool   $displayFilters
 * @var bool   $displayPerPage
 * @var int    $postsPerPage
 * @var string $paginationMode
 */

// ── Get all public searchable post types ──
$searchable_types = get_post_types( [
	'public'              => true,
	'exclude_from_search' => false,
], 'objects' );

// Exclude attachment
unset( $searchable_types['attachment'] );

// ── Block attributes ──
$posts_per       = $postsPerPage;
$pagination_mode = $paginationMode;
$show_filters    = ! empty( $displayFilters );
$show_per_page   = ! empty( $displayPerPage );
$placeholder     = $searchPlaceholder;

// ── Compute per-page options (multiples of postsPerPage) ──
$per_page_options = [];
for ( $i = 1; $i <= 5; $i++ ) {
	$per_page_options[] = $posts_per * $i;
}

// ── Get query vars ──
$search_query        = get_query_var( 's' );
$post_type_filter    = get_query_var( 'post_type_filter' );
$selected_post_types = array_intersect(
	array_filter( explode( ',', $post_type_filter ) ),
	array_keys( $searchable_types )
);

// Per-page from URL
$per_page_var = 'show_per_page';
$url_per_page = intval( get_query_var( $per_page_var ) );
if ( $url_per_page > 0 ) {
	$posts_per = $url_per_page;
}

// ── Build WP_Query ──
$paged = get_query_var( 'paged' ) ? intval( get_query_var( 'paged' ) ) : 1;
$query = null;

if ( $search_query ) {
	$args = [
		'post_type'      => ! empty( $selected_post_types ) ? $selected_post_types : array_keys( $searchable_types ),
		'paged'          => $paged,
		'posts_per_page' => $posts_per,
		'orderby'        => 'relevance',
		'order'          => 'desc',
		'post_status'    => 'publish',
		's'              => sanitize_text_field( $search_query ),
	];

	$query = new WP_Query( $args );
}
?>

<section <?php theme_block_props( 'search py-12' ); ?>
	data-pagination-mode="<?php echo esc_attr( $pagination_mode ); ?>"
>
	<div class="container grid grid-cols-1 gap-gap-7">
		<h1 class="text-header-1">
			<?php echo wp_kses_post( $heading ); ?>
		</h1>

		<div class="search-inner grid grid-cols-1 gap-gap-5">
			<div class="search-filters grid grid-cols-1 gap-4">
				<form method="get" role="search" aria-label="<?php esc_attr_e( 'Site search', 'takt' ); ?>" class="grid grid-cols-1 gap-4">
					<input type="hidden" name="paged" value="1" />

					<?php /* Search Input Row */ ?>
					<div class="flex flex-wrap items-center gap-4">
						<div class="grow min-w-58 grid">
							<span class="col-1 row-1 order-2 self-center ml-3 *:w-5 *:h-auto pointer-events-none">
								<?php theme_block_asset( 'resources/IconSearch.svg' ); ?>
							</span>
							<input
								type="text"
								data-type="s"
								name="s"
								placeholder="<?php echo esc_attr( $placeholder ); ?>"
								value="<?php echo esc_attr( $search_query ); ?>"
								class="col-1 row-1 pl-12 bg-white"
								aria-label="<?php esc_attr_e( 'Search query', 'takt' ); ?>"
							>
						</div>
						<button type="submit" class="btn-primary shrink-0" aria-label="<?php esc_attr_e( 'Search', 'takt' ); ?>">
							<?php esc_html_e( 'Search', 'takt' ); ?>
						</button>
					</div>

					<?php /* Filter Row */ ?>
					<?php if ( $show_filters || $show_per_page ) : ?>
						<div class="flex flex-wrap items-center gap-4">
							<?php if ( $show_filters && ! empty( $searchable_types ) ) : ?>
								<div class="search-filter-select max-sm:w-full">
									<input
										data-type="post_type_filter"
										type="hidden"
										name="post_type_filter"
										value="<?php echo esc_attr( implode( ',', $selected_post_types ) ); ?>"
									>
									<select
										data-type="post_type_filter"
										class="min-w-58"
										aria-label="<?php esc_attr_e( 'Filter by post type', 'takt' ); ?>"
										<?php if ( count( $searchable_types ) === count( $selected_post_types ) ) { echo 'disabled'; } ?>
									>
										<option value="" disabled selected>
											<?php esc_html_e( 'Filter by Post Type', 'takt' ); ?>
										</option>
										<?php foreach ( $searchable_types as $type ) : ?>
											<option
												value="<?php echo esc_attr( $type->name ); ?>"
												<?php if ( in_array( $type->name, $selected_post_types, true ) ) { echo 'disabled'; } ?>
											>
												<?php echo esc_html( $type->labels->singular_name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endif; ?>

							<?php if ( $show_per_page ) : ?>
								<div class="ml-auto flex items-center gap-2 whitespace-nowrap shrink-0 max-sm:hidden">
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

				<?php /* Active Filter Pills */ ?>
				<?php if ( $show_filters ) : ?>
					<div class="search-active-filters flex flex-wrap items-center gap-3">
						<?php foreach ( $selected_post_types as $type_slug ) : ?>
							<?php if ( isset( $searchable_types[ $type_slug ] ) ) : ?>
								<button type="button" class="inline-flex items-center gap-1 border border-current rounded-full px-3 py-1 text-body-small uppercase font-medium tracking-wider cursor-pointer" data-field="post_type_filter" data-value="<?php echo esc_attr( $type_slug ); ?>">
									<span><?php echo esc_html( $searchable_types[ $type_slug ]->labels->singular_name ); ?></span>
									<div class="search-remove-filter"></div>
								</button>
							<?php endif; ?>
						<?php endforeach; ?>
						<button type="button" data-clear-all="1" class="text-body-small uppercase font-medium tracking-wider cursor-pointer hover:underline focus:underline" aria-label="<?php esc_attr_e( 'Clear all filters', 'takt' ); ?>">
							<?php esc_html_e( 'Clear All', 'takt' ); ?>
						</button>
					</div>
				<?php endif; ?>

				</div>

			<?php /* Results-summary heading — sits between the page <h1> and the result cards
			(<h3>) so the cascade is h1 → h2 → h3, and gives users a visible
			"N results for query" / "no results" message. Only renders when the user
			has actually searched. Outside the live region so AJAX load-more doesn't
			re-announce it. */ ?>
			<?php if ( $search_query ) : ?>
				<h2 class="text-header-3">
					<?php
					if ( $query && $query->found_posts > 0 ) {
						$count = (int) $query->found_posts;
						printf(
							esc_html( _n( '%d result for "%s"', '%d results for "%s"', $count, 'takt' ) ),
							$count,
							esc_html( $search_query )
						);
					} else {
						printf(
							esc_html__( 'No results found for "%s"', 'takt' ),
							esc_html( $search_query )
						);
					}
					?>
				</h2>
			<?php endif; ?>

			<?php /* Loading Spinner */ ?>
			<div class="search-loading hidden flex justify-center py-12" role="status" aria-label="<?php esc_attr_e( 'Loading results', 'takt' ); ?>">
				<?php theme_block_asset( 'resources/loading.svg' ); ?>
			</div>

			<?php /* Results */ ?>
			<div class="search-results" role="region" aria-live="polite" aria-label="<?php esc_attr_e( 'Search results', 'takt' ); ?>" tabindex="-1">
				<?php if ( $query && $query->have_posts() ) : ?>
					<div class="grid grid-cols-1 gap-8">
						<?php while ( $query->have_posts() ) : ?>
							<?php
							$query->the_post();
							get_template_part( 'parts/card', 'search' );
							?>
						<?php endwhile; ?>
					</div>

					<?php if ( $pagination_mode === 'load-more' ) : ?>
						<?php if ( $query->max_num_pages > $paged ) : ?>
							<div class="search-load-more flex justify-center mt-8">
								<button
									type="button"
									class="btn-secondary"
									data-next-page="<?php echo esc_attr( $paged + 1 ); ?>"
									data-max-pages="<?php echo esc_attr( $query->max_num_pages ); ?>"
									aria-disabled="false"
									aria-busy="false"
								>
									<?php esc_html_e( 'Load More', 'takt' ); ?>
								</button>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<?php if ( $query->max_num_pages > 1 ) : ?>
							<div class="flex justify-center mt-8">
								<nav class="navigation pagination" aria-label="<?php esc_attr_e( 'Pagination', 'takt' ); ?>">
									<div class="nav-links">
										<?php
										$current_page = max( 1, $paged );
										$total_pages  = $query->max_num_pages;

										echo wp_kses_post( paginate_links( [
											'base'      => str_replace( PHP_INT_MAX, '%#%', get_pagenum_link( PHP_INT_MAX, false ) ),
											'current'   => $current_page,
											'total'     => $total_pages,
											'prev_text' => '<span>' . __( 'Prev', 'takt' ) . '</span>',
											'next_text' => '<span>' . __( 'Next', 'takt' ) . '</span>',
											'end_size'  => 1,
											'mid_size'  => 2,
										] ) );
										?>
									</div>
								</nav>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
<?php if ( $query ) { wp_reset_postdata(); } ?>
