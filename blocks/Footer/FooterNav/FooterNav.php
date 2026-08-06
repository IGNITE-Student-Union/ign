<?php
/**
 * Footer Nav block frontend template.
 *
 * @var string   $children Inner blocks content
 * @var WP_Block $block    Block instance
 */
?>

<div <?php theme_block_props( 'py-8 text-white' ); ?> role="navigation" aria-label="<?php esc_attr_e( 'Footer Navigation', 'takt' ); ?>">
	<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-8">
		<?php echo $children; ?>
	</div>
</div>
