<?php
/**
 * CardsCarouselItem frontend template.
 *
 * Available variables (auto-extracted from $attributes):
 * @var string $title
 * @var string $description
 * @var array  $button
 * @var string $accentColor
 *
 * Also available:
 * @var string   $children Inner blocks content
 * @var WP_Block $block    Block instance
 */

$columns   = $block->context['takt/cards-carousel/columns'] ?? 3;
$hasButton = ! empty( $button['url'] ) && ! empty( $button['title'] );
?>

<div
<?php
theme_block_props(
	[
		'swiper-slide' => true,
		$accentColor   => ! empty( $accentColor ),
	]
);
?>
>
	<div class="light-surface w-full h-full bg-accent rounded-2xl p-6 md:p-8 flex flex-col">
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
			<div class="cards-carousel-item-button mt-auto">
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
