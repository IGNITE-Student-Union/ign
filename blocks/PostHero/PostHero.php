<?php
/**
 * Post Hero Block
 *
 * Available variables (auto-extracted from $attributes):
 * @var string|null $anchor
 * @var bool $showExcerpt
 * @var bool $useTopicColor
 * @var bool $showAuthorDate
 *
 * @var string $content Block content.
 * @var WP_Block $block Block instance.
 */

$postTitle       = get_the_title();
$postExcerpt     = get_the_excerpt();
$featuredImageId = get_post_thumbnail_id();
$focalPoint      = get_post_meta( get_the_ID(), 'focal_point', true );
$categories      = get_the_category();
$postAuthor      = get_the_author();
$postDate        = get_the_date();

$firstCategory       = ! empty( $categories ) ? $categories[0] : null;
$firstAccentColor    = $firstCategory ? get_term_meta( $firstCategory->term_id, 'accent_color', true ) : '';
?>

<section <?php
	theme_block_props(
		[
			'post-hero grid overflow-visible min-h-[min(800px,100vh)] -mt-(--header-height)' => true,
			$firstAccentColor                                         => $useTopicColor && ! empty( $firstAccentColor ),
		]
	);
?>>

	<?php // Layer 1: Background image — col-1/row-1, stretches to fill grid ?>
	<div class="col-start-1 row-start-1 overflow-hidden bg-accent h-[calc(var(--header-height)+450px)] mask-b-from-40% mask-b-to-100%">
		<?php if ( $featuredImageId ) : ?>
			<?php
			echo wp_get_attachment_image(
				$featuredImageId,
				'full',
				false,
				[
					'class' => 'w-full h-full object-cover',
					'style' => 'object-position: ' . theme_image_position( $focalPoint ) . ';',
					'alt'   => '',
					'role'  => 'presentation',
				]
			);
			?>
		<?php endif; ?>
	</div>

	<?php // Layer 2: Gradient — col-1/row-1, extends below for subsequent sections ?>
	<div class="col-start-1 row-start-1 self-start relative -z-1">
		<div class="top-gradient"></div>
	</div>

	<?php // Layer 3: Content — col-1/row-1, aligned to bottom ?>
	<?php // Layer 3: Content — col-1/row-1, aligned to bottom ?>
	<div class="col-start-1 row-start-1 self-end relative z-10 pb-8 sm:pb-16 pt-[calc(var(--header-height)+150px)] md:pt-[var(--header-height)]">
		<div class="container">
			<div class="max-w-[700px] mr-auto">
				<?php // Upper Group: Topic Pills + Title ?>
				<div class="flex flex-col gap-6">
					<?php // Topic Pills ?>
					<?php if ( ! empty( $categories ) ) : ?>
						<div class="flex flex-wrap gap-2" data-animate="fade-up" data-animate-delay="300">
							<?php foreach ( $categories as $category ) :
								$accentColor = get_term_meta( $category->term_id, 'accent_color', true );
							?>
								<span class="<?php
								echo class_name(
									[
										'inline-flex items-center justify-center px-2 py-2 rounded-full bg-accent-lighter border border-accent text-sm font-medium uppercase leading-[1.1] whitespace-nowrap shrink-0 text-charcoal' => true,
										$accentColor => ! empty( $accentColor ),
									]
								);
								?>">
									<?php echo esc_html( $category->name ); ?>
								</span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php // Post Title ?>
					<?php if ( $postTitle ) : ?>
						<h1 class="text-header-3 md:text-header-2 text-charcoal" data-animate="fade-up" data-animate-delay="450">
							<?php echo wp_kses_post( $postTitle ); ?>
						</h1>
					<?php endif; ?>
				</div>

				<?php // Excerpt (Optional) ?>
				<?php if ( $showExcerpt && ! empty( $postExcerpt ) ) : ?>
					<p class="text-body-large text-charcoal mt-6" data-animate="fade-up" data-animate-delay="600">
						<?php echo wp_kses_post( $postExcerpt ); ?>
					</p>
				<?php endif; ?>

				<?php // Lower Group: Author + Date ?>
				<?php if ( $showAuthorDate ?? true ) : ?>
				<div class="mt-12 flex flex-col gap-1" data-animate="fade-up" data-animate-delay="750">
					<?php // Author Line ?>
					<p class="text-lg font-medium leading-[1.2] text-charcoal">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: author name */
								__( 'By: %s', 'takt' ),
								$postAuthor
							)
						);
						?>
					</p>

					<?php // Date Line ?>
					<p class="text-lg font-medium leading-[1.2] text-charcoal">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: publish date */
								__( 'Published on: %s', 'takt' ),
								$postDate
							)
						);
						?>
					</p>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
