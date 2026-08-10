<?php
/**
 * Dynamic Content Carousel block template.
 *
 * Renders a carousel of dynamically queried posts from configurable post types
 * with taxonomy filtering, automatic/manual/related selection modes, and Swiper carousel.
 *
 * @var string|null $anchor
 * @var string      $eyebrow
 * @var string      $heading
 * @var string      $description
 * @var array       $buttons
 * @var string      $postsType
 * @var string      $postsSource
 * @var array       $selectedPosts
 * @var int         $postsLimit
 * @var bool        $hideIfEmpty
 * @var bool        $fallbackToLatest
 * @var array       $selectedCategories
 * @var array       $selectedDepartments
 * @var array       $selectedAudiences
 * @var array       $selectedResourceTypes
 * @var array       $selectedPolicyTopics
 * @var bool        $showPagination
 * @var bool        $showNavigation
 * @var bool        $loopCarousel
 * @var bool        $autoplayCarousel
 * @var int         $autoplayDelay
 * @var bool        $showTags
 *
 * @var WP_Block $block    Block instance
 * @var string   $children InnerBlocks content
 */

$post_type_config = [
	'post' => [
		'post_type'        => 'post',
		'orderby'          => 'date',
		'order'            => 'DESC',
		'card'             => 'post',
		'taxonomy_filters' => [
			'selectedCategories' => [ 'taxonomy' => 'category', 'field' => 'term_id' ],
		],
	],
	'team_member' => [
		'post_type'        => 'team_member',
		'orderby'          => 'title',
		'order'            => 'ASC',
		'card'             => 'team-member',
		'taxonomy_filters' => [
			'selectedDepartments' => [ 'taxonomy' => 'department', 'field' => 'term_id' ],
		],
	],
	'resource' => [
		'post_type'        => 'resource',
		'orderby'          => 'date',
		'order'            => 'DESC',
		'card'             => 'resource',
		'taxonomy_filters' => [
			'selectedAudiences'     => [ 'taxonomy' => 'audience', 'field' => 'term_id' ],
			'selectedResourceTypes' => [ 'taxonomy' => 'resource_type', 'field' => 'term_id' ],
		],
	],
	'policy' => [
		'post_type'        => 'policy',
		'orderby'          => 'title',
		'order'            => 'ASC',
		'card'             => 'policy',
		'taxonomy_filters' => [
			'selectedPolicyTopics' => [ 'taxonomy' => 'policy_topic', 'field' => 'term_id' ],
		],
	],
	'tribe_events' => [
		'post_type'        => 'tribe_events',
		'orderby'          => 'date',
		'order'            => 'DESC',
		'card'             => 'event',
		'taxonomy_filters' => [
			'selectedEventCategories' => [ 'taxonomy' => 'tribe_events_cat', 'field' => 'term_id' ],
		],
	],
];

$active_config = $post_type_config[ $postsType ] ?? reset( $post_type_config );
$post_type     = $active_config['post_type'];
$card_slug     = $active_config['card'];

// Build WP_Query args
$query     = null;
$has_posts = false;

if ( $postsSource === 'manual' && ! empty( $selectedPosts ) ) {
	$query = new WP_Query( [
		'post_type'      => $post_type,
		'post__in'       => $selectedPosts,
		'orderby'        => 'post__in',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	] );
	$has_posts = $query->have_posts();
} elseif ( $postsSource === 'related' ) {
	$current_post_id = get_the_ID();
	$is_singular     = is_singular();

	$fallback_args = [
		'post_type'      => $post_type,
		'posts_per_page' => $postsLimit,
		'orderby'        => $active_config['orderby'],
		'order'          => $active_config['order'],
		'post_status'    => 'publish',
		'post__not_in'   => $current_post_id ? [ $current_post_id ] : [],
	];

	if ( $is_singular && $current_post_id ) {
		// Build taxonomy query from current post's terms
		$args        = array_merge( $fallback_args, [ 'tax_query' => [] ] );
		$tax_queries = [];

		foreach ( $active_config['taxonomy_filters'] as $attr_name => $filter ) {
			$taxonomy = $filter['taxonomy'];
			$terms    = wp_get_object_terms( $current_post_id, $taxonomy, [ 'fields' => 'ids' ] );

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$tax_queries[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $terms,
				];
			}
		}

		if ( ! empty( $tax_queries ) ) {
			if ( count( $tax_queries ) > 1 ) {
				$args['tax_query']['relation'] = 'OR';
			}
			$args['tax_query'] = array_merge( $args['tax_query'], $tax_queries );
		}

		$query     = new WP_Query( $args );
		$has_posts = $query->have_posts();

		// Fallback to latest posts if no related posts found
		if ( ! $has_posts && $fallbackToLatest ) {
			$query     = new WP_Query( $fallback_args );
			$has_posts = $query->have_posts();
		}
	} elseif ( $fallbackToLatest ) {
		// Non-singular page: no current post to relate to, fall back to latest
		$query     = new WP_Query( $fallback_args );
		$has_posts = $query->have_posts();
	}
} elseif ( $postsSource === 'automatic' ) {
	$args = [
		'post_type'      => $post_type,
		'posts_per_page' => $postsLimit,
		'orderby'        => $active_config['orderby'],
		'order'          => $active_config['order'],
		'post_status'    => 'publish',
		'tax_query'      => [],
	];

	/* Apply per-post-type taxonomy filters */
	foreach ( $active_config['taxonomy_filters'] as $attr_name => $filter ) {
		$value = $$attr_name ?? [];
		if ( ! empty( $value ) ) {
			$args['tax_query'][] = [
				'taxonomy' => $filter['taxonomy'],
				'field'    => $filter['field'],
				'terms'    => $value,
			];
		}
	}

	$query     = new WP_Query( $args );
	$has_posts = $query->have_posts();

	// Fallback to latest posts if taxonomy-filtered query returned no results
	if ( ! $has_posts && $fallbackToLatest && ! empty( $args['tax_query'] ) ) {
		$args = [
			'post_type'      => $post_type,
			'posts_per_page' => $postsLimit,
			'orderby'        => $active_config['orderby'],
			'order'          => $active_config['order'],
			'post_status'    => 'publish',
		];
		$query     = new WP_Query( $args );
		$has_posts = $query->have_posts();
	}
}
?>

<?php if ( empty( $hideIfEmpty ) || $has_posts ) : ?>
	<section <?php theme_block_props( 'dynamic-content-carousel dark bg-transparent!', 'data-post-type="' . esc_attr( $postsType ) . '"' . ( empty( $heading ) ? ' aria-label="' . esc_attr__( 'Content Carousel', 'takt' ) . '"' : '' ) ); ?>>
		<div class="container relative py-6 sm:py-16 dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)">
			<div data-animate="fade-up">
			<?php
			get_template_part( 'parts/ThemeHeading', null, [
				'className'        => 'mb-16',
				'eyebrow'          => $eyebrow,
				'heading'          => $heading,
				'headingSize'      => 2,
				'description'      => $description,
				'buttons'          => $buttons,
				'columns'          => 2,
				'enableButtons'    => true,
			] );
			?>
			</div>

			<?php // role="group" (not "region") so this carousel container isn't a duplicate
			// landmark of the parent <section> when both share the same heading
			// (axe landmark-unique). APG carousel pattern accepts either role. ?>
			<?php if ( $has_posts ) : ?>
				<div class="swiper-parent overflow-hidden grid grid-cols-1 grid-rows-1" role="group" aria-roledescription="<?php esc_attr_e( 'carousel', 'takt' ); ?>" aria-label="<?php echo esc_attr( ! empty( $heading ) ? sprintf( __( '%s carousel', 'takt' ), wp_strip_all_tags( $heading ) ) : __( 'Content Carousel', 'takt' ) ); ?>" data-animate="fade-up" data-animate-delay="150">
					<?php if ( $showPagination || $showNavigation || $autoplayCarousel ) : ?>
						<div class="flex items-center justify-end gap-4 mb-6">
							<?php if ( $autoplayCarousel ) : ?>
								<button
									class="carousel-autoplay-toggle carousel-nav-btn"
									aria-label="<?php esc_attr_e( 'Stop slide rotation', 'takt' ); ?>"
									data-label-pause="<?php esc_attr_e( 'Stop slide rotation', 'takt' ); ?>"
									data-label-play="<?php esc_attr_e( 'Start slide rotation', 'takt' ); ?>"
								>
									<?php theme_block_asset( 'Pause.svg' ); ?>
								</button>
							<?php endif; ?>

							<?php if ( $showPagination ) : ?>
								<div class="swiper-pagination flex-1 relative! h-1 bg-charcoal/20 dark:bg-white/20 rounded-full overflow-hidden" aria-hidden="true"></div>
							<?php endif; ?>

							<?php if ( $showNavigation ) : ?>
								<div class="swiper-arrows flex items-center gap-2 md:gap-4">
									<button
										class="carousel-nav-btn prev"
										aria-label="<?php esc_attr_e( 'Previous Item', 'takt' ); ?>"
										title="<?php esc_attr_e( 'Previous Item', 'takt' ); ?>"
									>
										<?php theme_block_asset( 'ArrowLeft.svg' ); ?>
									</button>
									<button
										class="carousel-nav-btn next"
										aria-label="<?php esc_attr_e( 'Next Item', 'takt' ); ?>"
										title="<?php esc_attr_e( 'Next Item', 'takt' ); ?>"
									>
										<?php theme_block_asset( 'ArrowRight.svg' ); ?>
									</button>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<div
						class="swiper dynamic-content-carousel-swiper relative overflow-visible! w-full"
						data-post-type="<?php echo esc_attr( $postsType ); ?>"
						data-navigation="<?php echo (int) $showNavigation; ?>"
						data-pagination="<?php echo (int) $showPagination; ?>"
						data-loop="<?php echo (int) $loopCarousel; ?>"
						data-autoplay="<?php echo (int) $autoplayCarousel; ?>"
						data-autoplay-delay="<?php echo esc_attr( $autoplayDelay ); ?>"
					>
						<div class="swiper-wrapper">
							<?php
							$slide_index = 0;
							$total_slides = $query->post_count;
							?>
							<?php while ( $query->have_posts() ) : ?>
								<?php
								$query->the_post();
								$slide_index++;
								$is_first_slide = ( 1 === $slide_index );
								?>
								<div
									class="swiper-slide h-auto!"
									role="group"
									aria-roledescription="<?php esc_attr_e( 'slide', 'takt' ); ?>"
									aria-label="<?php echo esc_attr( sprintf( __( 'Slide %d of %d', 'takt' ), $slide_index, $total_slides ) ); ?>"
									<?php if ( ! $is_first_slide ) : ?>aria-hidden="true" inert<?php endif; ?>
								>
									<?php get_template_part( 'parts/card', $card_slug, [ 'showTags' => $showTags ] ); ?>
								</div>
							<?php endwhile; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>
<?php wp_reset_postdata(); ?>
