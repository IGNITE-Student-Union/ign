<?php
$hasInnerBlocks = ! empty( $children );
?>

<section <?php theme_block_props( 'gallery-carousel dark bg-transparent!', empty( $heading ) ? 'aria-label="' . esc_attr__( 'Gallery Carousel', 'takt' ) . '"' : '' ); ?>>
	<div class="container relative py-6 sm:py-16 dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)">
		<?php // Noise texture overlay (decorative SVG; alt="" + no accessible name = AT ignores) ?>
		<div class="absolute inset-x-(--side-gutter) md:inset-x-(--bg-extend) inset-y-0 pointer-events-none rounded-3xl overflow-hidden">
			<svg class="hidden">
				<filter id="gallery-carousel-noise-<?php echo esc_attr( theme_block_region_id() ); ?>">
					<feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch" />
				</filter>
			</svg>
			<div class="absolute inset-0 opacity-[0.08] mix-blend-color-dodge" style="filter: url(#gallery-carousel-noise-<?php echo esc_attr( theme_block_region_id() ); ?>);"></div>
		</div>

		<?php // Header - two columns using ThemeHeading ?>
		<div data-animate="fade-up">
		<?php
		get_template_part(
			'parts/ThemeHeading',
			null,
			[
				'eyebrow'          => $eyebrow,
				'heading'          => $heading,
				'headingSize'      => 2,
				'description'      => $description,
				'buttons'          => $buttons,
				'columns'          => 2,
				'enableButtons'    => true,
				'className'        => 'mb-16',
			]
		);
		?>
		</div>

		<?php // Carousel ?>
		<?php if ( $hasInnerBlocks ) : ?>
			<?php // Inner carousel uses role="group" (not "region") to avoid duplicating
			// the outer <section>'s region landmark — both were ending up with the
			// same aria-labelledby heading id, triggering axe landmark-unique on
			// /work-at-ignite/. Matches the role="group" pattern used by the other
			// 3 carousel blocks (DCC, Testimonials, Cards). aria-roledescription
			// preserves the ARIA carousel pattern semantics. ?>
			<div class="swiper-parent relative overflow-hidden grid grid-cols-1 grid-rows-1" role="group" aria-roledescription="<?php esc_attr_e( 'carousel', 'takt' ); ?>" data-animate="fade-up" data-animate-delay="150">
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

							<?php // Pagination progress bar ?>
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
						class="swiper gallery-carousel-swiper relative overflow-hidden default-mask w-full"
						data-navigation="<?php echo (int) $showNavigation; ?>"
						data-pagination="<?php echo (int) $showPagination; ?>"
						data-loop="<?php echo (int) $loopCarousel; ?>"
						data-autoplay="<?php echo (int) $autoplayCarousel; ?>"
						data-autoplay-delay="<?php echo esc_attr( $autoplayDelay ); ?>"
					>
						<div class="swiper-wrapper">
							<?php echo $children; ?>
						</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
