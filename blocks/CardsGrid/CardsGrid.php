<?php
/**
 * CardsGrid frontend template.
 *
 * Available variables (auto-extracted from $attributes):
 * @var string $eyebrow
 * @var string $heading
 * @var string $description
 * @var int    $columns
 * @var bool   $darkMode
 *
 * Also available:
 * @var string   $children Inner blocks content
 * @var WP_Block $block    Block instance
 */

// Derived variables only - attributes are auto-extracted
$hasInnerBlocks = ! empty( $children );
$hasHeader      = ! empty( $eyebrow ) || ! empty( $heading ) || ! empty( $description );

// Grid column classes based on columns attribute (auto-extracted, default 3)
$gridClasses = [
	'grid gap-6'                                => true,
	'grid-cols-1'                               => $columns === 1,
	'grid-cols-1 md:grid-cols-2'                => $columns === 2,
	'grid-cols-1 md:grid-cols-2 lg:grid-cols-3' => $columns === 3,
];
?>

<section
<?php
theme_block_props(
	[
		'cards-grid' => true,
		'py-6 sm:py-16' => ! $darkMode,
		'dark bg-transparent!' => $darkMode,
	],
	! empty( $heading ) ? null : [ 'aria-label' => 'Cards Grid' ]
);
?>
>
	<div class="<?php echo class_name( [ 'container' => true, 'relative py-6 sm:py-16 dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)' => $darkMode ] ); ?>">
		<?php // Header - two columns using ThemeHeading ?>
		<?php if ( $hasHeader ) : ?>
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
		<?php endif; ?>

		<?php // Grid ?>
		<?php if ( $hasInnerBlocks ) : ?>
			<div class="relative">
				<div class="<?php echo class_name( $gridClasses ); ?>" data-animate-stagger>
					<?php echo $children; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
