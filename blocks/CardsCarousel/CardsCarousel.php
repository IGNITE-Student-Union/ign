<?php
$hasInnerBlocks = ! empty( $children );
?>

<section
<?php
theme_block_props(
	[
		'cards-carousel' => true,
		'dark bg-transparent!' => $darkMode,
	],
	empty( $heading ) ? 'aria-label="' . esc_attr__( 'Cards Carousel', 'takt' ) . '"' : ''
);
?>
>
	<div class="<?php echo class_name( [ 'container py-6 sm:py-16' => true, 'relative dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)' => $darkMode ] ); ?>">
		<?php // Header - two columns using ThemeHeading ?>
		<div data-animate="fade-up">
		<?php
		get_template_part(
			'parts/ThemeHeading',
			null,
			[
				'className'     => 'mb-16',
				'eyebrow'       => $eyebrow,
				'heading'       => $heading,
				'headingSize'   => 2,
				'description'   => $description,
				'buttons'       => $buttons,
				'columns'       => 2,
			]
		);
		?>
		</div>

		<?php // Carousel ?>
		<?php if ( $hasInnerBlocks ) : ?>
			<div class="swiper-parent overflow-hidden grid grid-cols-1 grid-rows-1" aria-roledescription="<?php esc_attr_e( 'carousel', 'takt' ); ?>" data-animate="fade-up" data-animate-delay="150">
				<?php // Pagination + Navigation row ?>
				<?php if ( $showPagination || $showNavigation || $autoplayCarousel ) : ?>
					<div class="flex items-center justify-end gap-4 mb-6">
						<?php // Autoplay toggle button ?>
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

						<?php // Pagination progress bar with gradient ?>
						<?php if ( $showPagination ) : ?>
							<div class="swiper-pagination flex-1 relative! h-1 bg-charcoal/20 dark:bg-white/20 rounded-full overflow-hidden" aria-hidden="true"></div>
						<?php endif; ?>

						<?php // Navigation arrows ?>
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
					class="swiper cards-carousel-swiper relative overflow-visible! w-full"
					data-navigation="<?php echo (int) $showNavigation; ?>"
					data-pagination="<?php echo (int) $showPagination; ?>"
					data-loop="<?php echo (int) $loopCarousel; ?>"
					data-autoplay="<?php echo (int) $autoplayCarousel; ?>"
					data-autoplay-delay="<?php echo esc_attr( $autoplayDelay ); ?>"
					data-columns="<?php echo esc_attr( $columns ); ?>"
				>
					<div class="swiper-wrapper">
						<?php echo $children; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
