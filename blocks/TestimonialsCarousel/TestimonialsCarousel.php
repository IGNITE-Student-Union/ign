<?php
/**
 * Testimonials Carousel block template.
 *
 * Renders a carousel of dynamically queried testimonial posts with accent-colored
 * cards, video modal support, and Swiper carousel.
 */

$query     = null;
$has_posts = false;

if ( $postsSource === 'manual' && ! empty( $selectedPosts ) ) {
	$query = new WP_Query( [
		'post_type'      => 'testimonial',
		'post__in'       => $selectedPosts,
		'orderby'        => 'post__in',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	] );
	$has_posts = $query->have_posts();
} elseif ( $postsSource !== 'manual' ) {
	$args = [
		'post_type'      => 'testimonial',
		'posts_per_page' => $postsLimit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'tax_query'      => [],
	];

	if ( ! empty( $selectedPrograms ) ) {
		$args['tax_query'][] = [
			'taxonomy' => 'program',
			'field'    => 'term_id',
			'terms'    => $selectedPrograms,
		];
	}

	$query     = new WP_Query( $args );
	$has_posts = $query->have_posts();
}
?>

<?php if ( empty( $hideIfEmpty ) || $has_posts ) : ?>
	<section <?php theme_block_props( 'testimonials-carousel dark bg-transparent!', empty( $heading ) ? 'aria-label="' . esc_attr__( 'Testimonials Carousel', 'takt' ) . '"' : '' ); ?>>
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

			<?php if ( $has_posts ) : ?>
				<div class="swiper-parent overflow-hidden grid grid-cols-1 grid-rows-1" aria-roledescription="<?php esc_attr_e( 'carousel', 'takt' ); ?>" data-animate="fade-up" data-animate-delay="150">
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
						class="swiper testimonials-carousel-swiper relative overflow-visible! w-full"
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
								$card_args = [];
								if ( $colorSource === 'testimonial' ) {
									$testimonial_accent = get_post_meta( get_the_ID(), 'accent_color', true );
									if ( ! empty( $testimonial_accent ) ) {
										$card_args['accent_color'] = $testimonial_accent;
									}
								}
								?>
								<div
									class="swiper-slide h-auto!"
									role="group"
									aria-roledescription="<?php esc_attr_e( 'slide', 'takt' ); ?>"
									aria-label="<?php echo esc_attr( sprintf( __( 'Slide %d of %d', 'takt' ), $slide_index, $total_slides ) ); ?>"
								>
									<?php get_template_part( 'parts/card', 'testimonial', $card_args ); ?>
								</div>
							<?php endwhile; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php // Video modal (per-section instance) ?>
		<div
			class="testimonial-video-modal fixed inset-0 pt-(--admin-bar-height) z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300"
			role="dialog"
			aria-modal="true"
			aria-hidden="true"
			aria-labelledby="testimonial-video-heading"
			inert
		>
			<div class="testimonial-modal-content w-full max-w-4xl mx-4 overflow-y-auto max-h-full py-8 scale-95 transition-transform duration-300">
				<h2 id="testimonial-video-heading" class="sr-only"><?php esc_html_e( 'Video', 'takt' ); ?></h2>
				<div class="flex justify-end mb-3">
					<button
						class="testimonial-video-close modal-close-btn"
						aria-label="<?php esc_attr_e( 'Close video', 'takt' ); ?>"
					>
						<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</button>
				</div>
				<div class="aspect-video rounded-2xl overflow-hidden bg-charcoal shadow-2xl">
					<div class="testimonial-video-container w-full h-full"></div>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>
<?php wp_reset_postdata(); ?>
