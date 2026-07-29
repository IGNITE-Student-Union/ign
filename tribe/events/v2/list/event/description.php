<?php
/**
 * List View — Event Description
 *
 * Overrides the default excerpt in the archive/list view: no "[…]"
 * read-more suffix, and cut off at a real sentence boundary rather than
 * an arbitrary word count. As many complete sentences as fit within 55
 * words are shown — matching WordPress core's own default excerpt
 * length (the `excerpt_length` filter's default, also re-asserted for
 * tribe_events specifically in functions.php) — so the excerpt can span
 * more than one sentence, but never cuts one off mid-way.
 *
 * Deliberately NOT split on any line-break/blank-line convention.
 * MyIGNITE's paragraph formatting has proven inconsistent across real
 * event content — sometimes a blank line separates paragraphs, sometimes
 * a single line break does, sometimes breaks don't survive the import at
 * all — so no whitespace-based rule can reliably tell "end of intended
 * preview" from "just a line wrap." Sentence-ending punctuation instead
 * only requires each sentence to end in a real period/!/? — independent
 * of formatting entirely.
 * (Known limitation: an abbreviation like "Mr." would be mistaken for a
 * sentence end. Not handled — true sentence-boundary detection is a much
 * bigger problem than this needs.)
 *
 * Override of: [plugin]/src/views/v2/list/event/description.php
 *
 * @link http://evnt.is/1aiy
 */

$full  = get_the_content( null, false, get_the_ID() );
$plain = trim( wp_strip_all_tags( $full ) );

// Split into sentences, each one keeping its own ending punctuation.
// The second alternative catches any leftover text with no ending
// punctuation at all (either a trailing fragment, or the whole string
// if it has no . ! ? anywhere).
preg_match_all( '/[^.!?]*[.!?](?=\s|$)|[^.!?]+$/', $plain, $sentence_matches );
$sentences = array_filter( array_map( 'trim', $sentence_matches[0] ) );

// Greedily keep whole sentences until the next one would push the total
// past 55 words, so the excerpt can span multiple sentences but always
// ends at a real sentence boundary.
$excerpt    = '';
$word_count = 0;

foreach ( $sentences as $sentence ) {
	$sentence_word_count = count( preg_split( '/\s+/', $sentence ) );

	// First sentence alone already exceeds the cap — hard-cut it, same
	// safety-net behavior an unusually long single sentence always had.
	// Empty $more so no "[…]" gets appended.
	if ( '' === $excerpt && $sentence_word_count > 55 ) {
		$excerpt = wp_trim_words( $sentence, 55, '' );
		break;
	}

	if ( $word_count + $sentence_word_count > 55 ) {
		break;
	}

	$excerpt    .= ( '' === $excerpt ? '' : ' ' ) . $sentence;
	$word_count += $sentence_word_count;
}
?>

<?php if ( $excerpt ) : ?>
	<div class="tribe-events-calendar-list__event-description tribe-common-b2">
		<?php echo esc_html( $excerpt ); ?>
	</div>
<?php endif; ?>
