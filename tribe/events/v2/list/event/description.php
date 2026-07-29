<?php
/**
 * List View — Event Description
 *
 * Shows the full content verbatim, no word cap or sentence cutoff.
 *
 * MyIGNITE's "detailed description" box never reaches WordPress through
 * the ICS feed at all (confirmed directly from raw import data — only
 * the short "description" box's content ever shows up in $item), and
 * every event link on this page now goes straight to MyIGNITE instead of
 * an internal single-event page (see inc/functions/tribe-events.php).
 * With nothing to protect the preview from and no full page for it to
 * link to, there's no reason to cut this short anymore — whatever's in
 * the short description box is exactly what should show here, in full.
 *
 * A manual excerpt (Gutenberg's own Excerpt panel) still wins when one
 * is set — editors can override the content-derived text entirely.
 *
 * Override of: [plugin]/src/views/v2/list/event/description.php
 *
 * @link http://evnt.is/1aiy
 */

if ( has_excerpt() ) {
	$excerpt = get_the_excerpt();
} else {
	$full    = get_the_content( null, false, get_the_ID() );
	$excerpt = trim( wp_strip_all_tags( $full ) );
}
?>

<?php if ( $excerpt ) : ?>
	<div class="tribe-events-calendar-list__event-description tribe-common-b2">
		<?php echo esc_html( $excerpt ); ?>
	</div>
<?php endif; ?>
