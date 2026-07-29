<?php
/**
 * List View — Event Description
 *
 * Overrides the default excerpt in the archive/list view: no "[…]"
 * read-more suffix, and cut off after the first sentence rather than an
 * arbitrary word count. The 55-word `excerpt_length` filter (see
 * functions.php) still applies underneath as a safety net for an
 * unusually long opening sentence, or one with no punctuation at all.
 *
 * Deliberately NOT split on any line-break/blank-line convention.
 * MyIGNITE's paragraph formatting has proven inconsistent across real
 * event content — sometimes a blank line separates paragraphs, sometimes
 * a single line break does, sometimes breaks don't survive the import at
 * all — so no whitespace-based rule can reliably tell "end of intended
 * preview" from "just a line wrap." Splitting on the first sentence-
 * ending punctuation instead only requires the author's opening sentence
 * to end in a real period/!/? — independent of formatting entirely.
 * (Known limitation: an abbreviation like "Mr." at the very start of the
 * description would be mistaken for a sentence end. Not handled — true
 * sentence-boundary detection is a much bigger problem than this needs.)
 *
 * Override of: [plugin]/src/views/v2/list/event/description.php
 *
 * @link http://evnt.is/1aiy
 */

$full  = get_the_content( null, false, get_the_ID() );
$plain = trim( wp_strip_all_tags( $full ) );

// Cut after the first sentence-ending punctuation.
if ( preg_match( '/^.*?[.!?](?=\s|$)/s', $plain, $matches ) ) {
	$first_sentence = trim( $matches[0] );
} else {
	$first_sentence = $plain;
}

// Safety net: never exceed 55 words even if the opening "sentence" is
// unusually long or has no punctuation at all. Empty $more so no "[…]"
// gets appended.
$excerpt = wp_trim_words( $first_sentence, 55, '' );
?>

<?php if ( $excerpt ) : ?>
	<div class="tribe-events-calendar-list__event-description tribe-common-b2">
		<?php echo esc_html( $excerpt ); ?>
	</div>
<?php endif; ?>
