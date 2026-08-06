<?php
/**
 * Hero block template.
 *
 * @var string|null $anchor
 * @var string      $blockVariation
 * @var string|null $eyebrow
 * @var string|null $heading
 * @var string|null $description
 * @var array       $buttons
 * @var array       $image
 * @var bool        $enableQuickNav
 * @var bool        $showBreadcrumbs
 *
 * @var WP_Block $block    Block instance
 * @var string   $children InnerBlocks content
 */
$isPrimary       = $blockVariation === 'primary';
$isSecondary     = $blockVariation === 'secondary';
$hasBreadcrumbs  = ! empty( $showBreadcrumbs );

$resolvedImage = theme_resolve_responsive_image( $image ?? [] );
$hasImage      = ! empty( $resolvedImage['desktop']['id'] );

// The Events Calendar renders its own <h1> for events views. Demote this
// block's heading to h2 there so the page doesn't ship two h1s.
$heroHeadingTag = is_post_type_archive( 'tribe_events' ) ? 'h2' : 'h1';
?>

<section <?php
	theme_block_props(
		[
			'hero grid overflow-visible -mt-(--header-height)' => true,
			'hero--primary'                  => $isPrimary,
			'hero--secondary'                => $isSecondary,
		]
	);
?>>

	<?php // Layer 1: Background image — col-1/row-1, stretches to fill grid ?>
	<div class="hero-image-reveal col-start-1 row-start-1 overflow-hidden bg-accent h-[calc(var(--header-height)+300px)] md:h-[calc(var(--header-height)+450px)]">
		<?php if ( $hasImage ) : ?>
			<?php
			$desktopId = $resolvedImage['desktop']['id'];
			$tabletId  = $resolvedImage['tablet']['id'];
			$mobileId  = $resolvedImage['mobile']['id'];
			$positionStyle = esc_attr(
				'--hero-image-position-mobile: ' . theme_image_position( $resolvedImage['mobile']['focalPoint'] ?? null ) . ';' .
				'--hero-image-position-tablet: ' . theme_image_position( $resolvedImage['tablet']['focalPoint'] ?? null ) . ';' .
				'--hero-image-position-desktop: ' . theme_image_position( $resolvedImage['desktop']['focalPoint'] ?? null ) . ';'
			);
			?>
			<picture style="<?php echo $positionStyle; ?>">
				<?php if ( ! empty( $mobileId ) && $mobileId !== $desktopId ) : ?>
					<source media="(max-width: 767px)" srcset="<?php echo esc_url( wp_get_attachment_image_url( $mobileId, 'full' ) ); ?>">
				<?php endif; ?>
				<?php if ( ! empty( $tabletId ) && $tabletId !== $desktopId ) : ?>
					<source media="(min-width: 768px) and (max-width: 1167px)" srcset="<?php echo esc_url( wp_get_attachment_image_url( $tabletId, 'full' ) ); ?>">
				<?php endif; ?>
				<?php
				echo wp_get_attachment_image(
					$desktopId,
					'full',
					false,
					[
						'class' => 'w-full h-full object-cover',
						'alt'   => '',
					]
				);
				?>
			</picture>
		<?php endif; ?>
	</div>

	<?php // Layer 2: Gradient — col-1/row-1, margin-top aligns with content text position ?>
	<div class="col-start-1 row-start-1 self-start relative -z-1 mt-[calc(var(--header-height)+180px)] md:mt-[calc(var(--header-height)+300px)]">
		<div class="top-gradient"></div>
	</div>

	<?php // Layer 3: Content — col-1/row-1, padding-top for minimum top spacing ?>
	<div class="col-start-1 row-start-1 self-end relative z-10 pt-[calc(var(--header-height)+180px)] md:pt-[calc(var(--header-height)+300px)] pb-8 sm:pb-16">
		<div class="container">
			<?php if ( $hasBreadcrumbs ) : ?>
				<div data-animate="fade-up" data-animate-delay="600">
					<?php get_template_part( 'parts/breadcrumbs' ); ?>
				</div>
			<?php endif; ?>

			<?php
			get_template_part(
				'parts/ThemeHeading',
				null,
				[
					'className'        => class_name(
						[
							'max-w-[912px]' => $isPrimary,
							'max-w-[700px]' => $isSecondary,
							'mx-auto'       => $isPrimary,
							'mr-auto'       => $isSecondary,
						]
					),
					'alignment'        => $isPrimary ? 'center' : 'left',
					'enableEyebrow'    => ! $hasBreadcrumbs,
					'eyebrow'          => $eyebrow ?? '',
					'heading'          => $heading ?? '',
					'headingTag'       => $heroHeadingTag,
					'headingSize'      => 0,
					'headingClassName' => class_name(
						[
							'text-[2.5rem] md:text-[length:var(--text-header-0)]' => $isPrimary,
						]
					),
					'description'      => $description ?? '',
					'buttons'          => $isSecondary ? ( $buttons ?? [] ) : [],
					'animateFields'    => true,
					'animateBaseDelay' => 600,
					'animateStagger'   => 150,
				]
			);
			?>

			<?php // Quick Navigation (Primary only) ?>
			<?php if ( $isPrimary && ! empty( $enableQuickNav ) && ! empty( $children ) ) : ?>
				<div class="<?php
					echo class_name(
						[
							'mt-8 sm:mt-12'  => true,
							'max-w-[912px]'  => true,
							'mx-auto'        => $isPrimary,
						]
					);
				?>" data-animate="fade-up" data-animate-delay="1050">
					<?php echo $children; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
