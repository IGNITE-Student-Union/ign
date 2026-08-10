<?php
/**
 * Footer block frontend template.
 *
 * @var string   $children Inner blocks content
 * @var WP_Block $block    Block instance
 */
?>

<div
	<?php
	theme_block_props( 'footer relative overflow-hidden py-4 md:py-8' );
	?>
>
	<div class="container">
	<div class="flex flex-col gap-4 items-stretch relative z-1">
		<?php
		foreach ( $block->inner_blocks as $inner_block ) {
			if ( 'takt/footer-info' === $inner_block->name ) :
				?>
				<div class="footer-section-animate" style="transition-delay: 300ms"><?php echo $inner_block->render(); ?></div>
				<?php
			endif;
		}
		?>

		<?php // `dark-surface`: white focus ring for the nav and credits links inside the charcoal card. ?>
		<div class="dark-surface footer-section-animate relative before:absolute before:bg-charcoal before:rounded-[32px] before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)" style="transition-delay: 450ms">
			<?php
			foreach ( $block->inner_blocks as $inner_block ) {
				if ( 'takt/footer-nav' === $inner_block->name || 'takt/footer-credits' === $inner_block->name ) {
					echo $inner_block->render();
				}
			}
			?>
		</div>
	</div>
	</div>
</div>
