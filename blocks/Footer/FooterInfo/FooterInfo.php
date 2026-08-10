<?php
/**
 * Footer Info block frontend template.
 *
 * Available variables (auto-extracted from $attributes):
 * @var int|null $logoId
 * @var string   $addressLabel
 * @var array    $addresses
 * @var string   $hoursLabel
 * @var string   $hoursContent
 * @var string   $contactLabel
 * @var string   $contactContent
 * @var string   $socialLabel
 *
 * Also available:
 * @var string   $children Inner blocks content
 * @var WP_Block $block    Block instance
 */

$hasAddresses = false;
foreach ( $addresses as $addr ) {
	if ( ! empty( $addr['name'] ) || ! empty( $addr['address'] ) ) {
		$hasAddresses = true;
		break;
	}
}
?>

<section <?php theme_block_props( 'dark-surface relative py-8 text-white [&_a]:underline [&_a]:text-white [&_a]:hover:no-underline! [&_a]:hover:text-neon-green [&_a]:transition-colors before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)' ); ?>>
	<?php // Sr-only h2 wrapper so the FooterInfo column h3s (Address / Hours / Contact /
	// Follow Us) sit at h3 under an h2, not directly under the page h1. Pages where
	// the main content has no <h2> (eg /event/ detail, /404) were tripping axe's
	// heading-order rule because the cascade jumped h1 → h3. ?>
	<h2 class="screen-reader-text"><?php esc_html_e( 'Footer information', 'takt' ); ?></h2>

	<?php // Logo area ?>
	<?php $logo_output = ! empty( $logoId ) ? theme_output_svg_or_img( $logoId, false ) : ''; ?>
	<?php if ( ! empty( $logo_output ) ) : ?>
		<div class="mb-8">
			<div class="flex items-end gap-2.5">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="max-h-[58px] max-w-[230px] *:max-h-[58px] *:max-w-[230px] *:w-auto *:h-auto" aria-label="<?php esc_attr_e( 'Go to homepage', 'takt' ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>
					<?php echo $logo_output; ?>
				</a>
			</div>
		</div>
	<?php endif; ?>

	<?php // Info row ?>
	<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-x-4 sm:gap-x-8 gap-y-8">
		<?php // Address section ?>
		<div class="flex flex-col gap-3 col-span-2 sm:col-span-3 lg:col-span-3">
			<?php if ( ! empty( $addressLabel ) ) : ?>
				<h3 class="font-heading text-base leading-[1.1] tracking-[0.02em]"><?php echo esc_html( $addressLabel ); ?></h3>
			<?php endif; ?>

			<?php if ( $hasAddresses ) : ?>
				<div class="flex flex-col sm:flex-row sm:flex-wrap gap-8">
					<?php foreach ( $addresses as $addr ) : ?>
						<?php if ( ! empty( $addr['name'] ) || ! empty( $addr['address'] ) ) : ?>
							<div class="text-xs font-medium leading-normal font-sans sm:min-w-48">
								<?php if ( ! empty( $addr['name'] ) ) : ?>
									<p class="font-bold"><?php echo esc_html( $addr['name'] ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $addr['address'] ) ) : ?>
									<p><?php echo wp_kses_post( takt_decorate_mailto_links( $addr['address'] ) ); ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php // Hours ?>
		<div class="flex flex-col gap-3 break-words">
				<?php if ( ! empty( $hoursLabel ) ) : ?>
					<h3 class="font-heading text-base leading-[1.1] tracking-[0.02em]"><?php echo esc_html( $hoursLabel ); ?></h3>
				<?php endif; ?>
				<?php if ( ! empty( $hoursContent ) ) : ?>
					<div class="text-xs font-medium leading-normal font-sans"><?php echo wp_kses_post( takt_decorate_mailto_links( $hoursContent ) ); ?></div>
				<?php endif; ?>
			</div>

		<?php // Contact ?>
		<div class="flex flex-col gap-3 break-words">
				<?php if ( ! empty( $contactLabel ) ) : ?>
					<h3 class="font-heading text-base leading-[1.1] tracking-[0.02em]"><?php echo esc_html( $contactLabel ); ?></h3>
				<?php endif; ?>
				<?php if ( ! empty( $contactContent ) ) : ?>
					<div class="text-xs font-medium leading-normal font-sans"><?php echo wp_kses_post( takt_decorate_mailto_links( $contactContent ) ); ?></div>
				<?php endif; ?>
			</div>

		<?php // Follow Us + Social Icons ?>
		<div class="flex flex-col gap-3">
			<?php if ( ! empty( $socialLabel ) ) : ?>
				<h3 class="font-heading text-base leading-[1.1] tracking-[0.02em]"><?php echo esc_html( $socialLabel ); ?></h3>
			<?php endif; ?>
			<?php echo $children; ?>
		</div>
	</div>
</section>
