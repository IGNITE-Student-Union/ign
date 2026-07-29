<?php
/**
 * List View — Event Venue
 *
 * Overrides the default list-view venue display, which only shows a
 * venue name when the event has a properly linked Venue post
 * (tribe_get_venue_id()). Our ICS import deliberately never creates
 * linked Venue posts (see myignite_strip_venue_organizer_for_ics() in
 * inc/helpers/myignite-image-sync.php, added to stop CampusGroups
 * imports from accumulating orphan Venue posts nobody asked for), so
 * the stock template silently showed nothing for every imported event.
 *
 * Falls back to the plain-text venue name captured at ICS import
 * (_myignite_venue_name) when there's no linked Venue post — same
 * fallback already used on the single-event page (see
 * tribe-events/modules/meta/venue.php) and the Featured Events cards
 * (see parts/card-tribe_events.php).
 *
 * Override of: [plugin]/src/views/v2/list/event/venue.php
 *
 * @link http://evnt.is/1aiy
 */

$venue_id = tribe_get_venue_id();
$venue_name = $venue_id ? tribe_get_venue() : get_post_meta( get_the_ID(), '_myignite_venue_name', true );
?>

<?php if ( $venue_name ) : ?>
	<div class="tribe-events-calendar-list__event-venue tribe-common-b2">
		<?php echo esc_html( $venue_name ); ?>
	</div>
<?php endif; ?>
