<?php
/**
 * Footer Nav Column block frontend template.
 *
 * Available variables (auto-extracted from $attributes):
 * @var string $heading
 *
 * Also available:
 * @var string   $children Inner blocks content
 * @var WP_Block $block    Block instance
 */

// Match the id that theme_block_props auto-injects via aria-labelledby
// on the wrapper, so the <h3>, the <ul>, and the wrapper all reference
// the same element.
$heading_id = theme_block_region_id();
?>

<?php // aria-labelledby is suppressed on this wrapper div: a generic <div> with no
// role can't legally carry aria-labelledby (axe aria-prohibited-attr). The inner
// <ul> below carries it instead, which is valid since UL has implicit list role. ?>
<div <?php theme_block_props( 'flex flex-col gap-4 min-w-0 break-words', [ 'aria-labelledby' => false ] ); ?>>
	<?php if ( ! empty( $heading ) ) : ?>
		<h3 id="<?php echo esc_attr( $heading_id ); ?>" class="font-heading text-base leading-[1.1] tracking-[0.02em]"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<?php if ( ! empty( trim( $children ) ) ) : ?>
		<ul class="flex flex-col gap-2 list-none" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<?php echo $children; ?>
		</ul>
	<?php endif; ?>
</div>
