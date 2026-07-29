<?php
/**
 * Compact event list row for the Featured Events block.
 *
 * Called within a WP_Query loop (setup_postdata() already called).
 * Renders a text-only row for events after the featured (first) one:
 * a small date badge, title, and venue. No featured image, no
 * organizer, no description excerpt, no CTA button.
 */

$event_id = get_the_ID();
// Links to the event's MyIGNITE page (where RSVPs actually happen) instead
// of the internal single-event page, falling back to the internal
// permalink only if no Event Website URL was set.
$website   = tribe_get_event_website_url( $event_id );
$permalink = $website ? $website : get_permalink( $event_id );

$start_date = get_post_meta( $event_id, '_EventStartDate', true );
$venue_id   = get_post_meta( $event_id, '_EventVenueID', true );
// Falls back to the plain-text name captured at ICS import for events
// with no linked Venue post (see inc/helpers/myignite-image-sync.php).
$venue_name = $venue_id ? get_the_title( $venue_id ) : get_post_meta( $event_id, '_myignite_venue_name', true );

$day_of_week = '';
$day_number  = '';
$month_year  = '';

$accessible_date = '';
if ( $start_date ) {
	$timestamp       = strtotime( $start_date );
	$day_of_week     = date_i18n( 'D', $timestamp );
	$day_number      = date_i18n( 'j', $timestamp );
	$month_year      = date_i18n( 'M Y', $timestamp );
	$accessible_date = date_i18n( get_option( 'date_format' ), $timestamp );
}
?>

<div data-animate="fade-up" class="py-4 first:pt-0 last:pb-0">
	<a href="<?php echo esc_url( $permalink ); ?>" <?php echo $website ? 'target="_blank" rel="noopener noreferrer"' : ''; ?> class="flex gap-4 group no-underline! text-white">
		<?php if ( $start_date ) : ?>
			<div class="shrink-0 w-[72px] bg-charcoal rounded-lg py-2 px-1 text-center text-white flex flex-col items-center">
				<span class="sr-only"><?php echo esc_html( $accessible_date ); ?></span>
				<span class="font-sans font-medium text-sm leading-[1.5]" aria-hidden="true"><?php echo esc_html( $day_of_week ); ?></span>
				<span class="font-sans font-bold text-[1.75rem] leading-[1.1]" aria-hidden="true"><?php echo esc_html( $day_number ); ?></span>
				<span class="font-sans font-medium text-sm leading-[1.5]" aria-hidden="true"><?php echo esc_html( $month_year ); ?></span>
			</div>
		<?php endif; ?>

		<div class="flex flex-col gap-1">
			<h4 class="font-heading text-[1.5rem] leading-[1.1] group-hover:text-[var(--accent-color)]! transition-colors">
				<?php the_title(); ?>
				<?php if ( $website ) : ?>
					<span class="sr-only"> (<?php esc_html_e( 'opens in a new tab', 'takt' ); ?>)</span>
				<?php endif; ?>
			</h4>

			<?php if ( $venue_name ) : ?>
				<p class="font-sans font-medium text-sm leading-[1.5]"><?php echo esc_html( $venue_name ); ?></p>
			<?php endif; ?>
		</div>
	</a>
</div>
