<?php
/**
 * CardsGridItem frontend template.
 *
 * Available variables (auto-extracted from $attributes):
 * @var array  $image
 * @var string $title
 * @var string $description
 * @var array  $button
 * @var string $accentColor
 *
 * Also available:
 * @var string   $children Inner blocks content
 * @var WP_Block $block    Block instance
 */

// Derived variables only - attributes are auto-extracted
$columns   = $block->context['takt/cards-grid/columns'] ?? 3;
$hasImage  = ! empty( $image['id'] );
$hasButton = ! empty( $button['url'] ) && ! empty( $button['title'] );
?>

<div
data-animate="fade-up"
<?php
theme_block_props(
	[
		'h-full'      => true,
		$accentColor  => ! empty( $accentColor ),
	]
);
?>
>
	<div class="h-full flex flex-col">
		<?php // Image wrapper - colored background with padding around image ?>
		<?php if ( $hasImage ) : ?>
			<div class="bg-accent rounded-[25px] p-4">
				<div class="aspect-[4/3] w-full max-h-[350px] rounded-xl overflow-hidden">
					<?php
					echo wp_get_attachment_image(
						$image['id'],
						'large',
						false,
						[
							'class' => 'w-full h-full object-cover',
							'style' => 'object-position: ' . theme_image_position( $image['focalPoint'] ?? null ) . ';',
						]
					);
					?>
				</div>
			</div>
		<?php endif; ?>

		<?php // Content wrapper - colored background with padding ?>
		<div class="light-surface bg-accent rounded-[25px] p-6 flex flex-col flex-1">
			<?php // Title ?>
			<?php if ( ! empty( $title ) ) : ?>
				<h3 class="text-header-5 text-charcoal mb-4">
					<?php echo wp_kses_post( $title ); ?>
				</h3>
			<?php endif; ?>

			<?php // Description ?>
			<?php if ( ! empty( $description ) ) : ?>
				<p class="text-charcoal text-body mb-6 flex-1">
					<?php echo wp_kses_post( $description ); ?>
				</p>
			<?php endif; ?>

			<?php // Button with arrow ?>
			<?php if ( $hasButton ) : ?>
				<div class="cards-grid-item-button mt-auto">
					<?php
					get_template_part(
						'parts/ThemeButton',
						null,
						[
							'link'      => $button,
							'variation' => 'tertiary',
							'className' => 'text-charcoal! text-left! group inline-flex items-center gap-2',
						]
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
