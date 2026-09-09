<?php
/**
 * Footer Info Social Item block frontend template.
 *
 * Available variables (auto-extracted from $attributes):
 * @var array       $link
 * @var int|null    $logoId
 * @var string|null $iconSlug
 *
 * Also available:
 * @var string   $children Inner blocks content
 * @var WP_Block $block    Block instance
 */

$linkUrl       = $link['url'] ?? '';
$linkTitle     = $link['title'] ?? '';
$opensInNewTab = $link['opensInNewTab'] ?? true;
$linkLabel     = $link['label'] ?? '';

if ( empty( $linkUrl ) ) {
	return;
}

// Ensure link has an accessible name - use title as fallback if label is empty
if ( empty( $linkTitle ) && empty( $linkLabel ) ) {
	$linkTitle = 'Social media link';
}

// Resolve icon: suggested slug takes priority over media library
$iconHtml = '';
if ( ! empty( $iconSlug ) ) {
	$iconFile = __DIR__ . '/resources/icon-' . sanitize_file_name( $iconSlug ) . '.svg';
	if ( file_exists( $iconFile ) ) {
		$iconHtml = file_get_contents( $iconFile );
		$iconHtml = preg_replace( '/<\?xml.*?\?>\s*/', '', $iconHtml );
	}
} elseif ( ! empty( $logoId ) ) {
	$iconHtml = theme_output_svg_or_img( $logoId, false );
}

if ( ! empty( $iconHtml ) && function_exists( 'theme_mark_svg_decorative' ) ) {
	$iconHtml = theme_mark_svg_decorative( $iconHtml );
}
?>

<li <?php theme_block_props( 'block list-none' ); ?>>
	<a
		href="<?php echo esc_url( $linkUrl ); ?>"
		class="block transition-colors w-6 h-6 no-underline! *:w-6 *:h-6 *:object-contain *:object-center text-white hover:text-neon-green"
		<?php if ( $opensInNewTab ) : ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>
		<?php if ( ! empty( $linkLabel ) ) : ?> aria-label="<?php echo esc_attr( $linkLabel ); ?>"<?php endif; ?>
	>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG markup from trusted local files or theme_output_svg_or_img
		echo $iconHtml;
		?>
		<?php if ( empty( $linkLabel ) ) : ?>
			<span class="sr-only"><?php echo esc_html( $linkTitle ); ?></span>
		<?php endif; ?>
	</a>
</li>
