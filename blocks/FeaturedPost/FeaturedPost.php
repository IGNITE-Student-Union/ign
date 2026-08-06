<?php
/**
 * Featured Post Block
 *
 * Available variables (auto-extracted from $attributes):
 *
 * @var int|null $selectedPost
 * @var bool     $isReversed
 * @var bool     $reverseRows
 * @var bool     $darkMode
 * @var string   $buttonLabel
 * @var string   $headingSize
 * @var string   $eyebrow
 *
 * Also available:
 * @var WP_Block $block Block instance
 * @var string   $content Block content
 */

if ( empty( $selectedPost ) ) {
	return;
}

$post = get_post( $selectedPost );

if ( ! $post || $post->post_status !== 'publish' ) {
	return;
}

$postTitle      = get_the_title( $selectedPost );
$postExcerpt    = get_the_excerpt( $selectedPost );
$featuredImageId = get_post_thumbnail_id( $selectedPost );
$focalPoint          = get_post_meta( $selectedPost, 'focal_point', true );
$categories          = get_the_category( $selectedPost );
$firstCategory       = ! empty( $categories ) ? $categories[0] : null;
$categoryAccentColor = $firstCategory ? get_term_meta( $firstCategory->term_id, 'accent_color', true ) : '';
$permalink           = get_permalink( $selectedPost );
$headingTag     = $headingSize === 'small' ? 'h3' : 'h2';
$headingClass   = $headingSize === 'small' ? 'text-header-3' : 'text-header-2';
?>

<section
<?php
theme_block_props(
	[
		'featured-post' => true,
		'py-6 sm:py-16' => ! $darkMode,
		'dark bg-transparent!' => $darkMode,
	]
);
?>
>
	<div class="<?php
	echo class_name(
		[
			'container grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-10 sm:gap-y-16 items-stretch' => true,
			'relative py-6 sm:py-16 gap-y-8! before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)' => $darkMode,
		]
	);
	?>">

		<?php // Content Column ?>
		<div data-animate="fade-up" class="<?php
		echo class_name(
			[
				'flex items-center' => true,
				'md:col-start-1' => $isReversed,
				'md:col-start-2' => ! $isReversed,
			]
		);
		?>">
			<div class="grid grid-cols-1 h-fit">
				<?php if ( $firstCategory || ! empty( $eyebrow ) ) : ?>
					<?php // Topic Tag Row ?>
					<div class="flex gap-4 items-center not-last:mb-6">
						<?php if ( $firstCategory ) : ?>
							<span class="<?php
							echo class_name(
								[
									'inline-flex items-center justify-center px-2 py-1 rounded-full bg-accent-lighter border border-accent text-sm font-medium uppercase leading-[1.1] whitespace-nowrap shrink-0 text-charcoal' => true,
									$categoryAccentColor => ! empty( $categoryAccentColor ),
								]
							);
							?>">
								<?php echo esc_html( $firstCategory->name ); ?>
							</span>
						<?php endif; ?>

						<?php if ( ! empty( $eyebrow ) ) : ?>
							<span class="uppercase text-sm font-medium leading-[1.1]">
								<?php echo esc_html( $eyebrow ); ?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $postTitle ) : ?>
					<?php // Heading (Post Title) ?>
					<div class="not-last:mb-8 not-last:md:mb-12 <?php echo esc_attr( $headingClass ); ?>">
						<<?php echo $headingTag; ?>><?php echo wp_kses_post( $postTitle ); ?></<?php echo $headingTag; ?>>
					</div>
				<?php endif; ?>

				<?php if ( $postExcerpt ) : ?>
					<?php // Description (Post Excerpt) ?>
					<div class="group not-last:mb-8 not-last:md:mb-12">
						<p><?php echo wp_kses_post( $postExcerpt ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $buttonLabel ) ) : ?>
					<?php // Button ?>
					<div class="inline-flex flex-wrap gap-4 items-center">
						<?php
						get_template_part( 'parts/ThemeLink', null, [
							'link' => [
								'url'   => $permalink,
								'title' => $buttonLabel,
								'label' => $buttonLabel . ': ' . $postTitle,
							],
							'className' => 'btn-primary',
						] );
						?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $featuredImageId ) : ?>
			<?php // Media Column ?>
			<div data-animate="fade-in" data-animate-delay="100" class="<?php
			echo class_name(
				[
					'-order-1' => ! $reverseRows,
					'md:order-none md:row-start-1' => true,
					'md:col-start-1' => ! $isReversed,
					'md:col-start-2' => $isReversed,
				]
			);
			?>">
				<div class="<?php
				echo class_name(
					[
						'w-full aspect-[4/3] overflow-hidden default-mask' => true,
					]
				);
				?>">
					<?php
					echo wp_get_attachment_image(
						$featuredImageId,
						'full',
						false,
						[
							'class' => 'w-full h-full object-cover',
							'style' => 'object-position: ' . theme_image_position( $focalPoint ) . ';',
						]
					);
					?>
				</div>
			</div>
		<?php endif; ?>

	</div>
</section>
