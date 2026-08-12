<?php
/**
 * Featured (first) event card for the Featured Events block.
 *
 * Called within a WP_Query loop (the_post()/setup_postdata() already called).
 * Renders a single stacked card: image with date badge overlay on top,
 * event details (title, venue, CTA) below. Organizer and description
 * are intentionally omitted to match the compact list rows alongside it.
 *
 * When it is the only event (`isFullWidth`), it flips to a short horizontal
 * row from `sm` up — a small image on the left, details on the right — so a
 * lone event does not stretch a container-wide 4:3 image down the page.
 *
 * @var array  $args         Template args passed via get_template_part().
 * @var string $buttonLabel  CTA button text (passed from parent block via $args).
 * @var bool   $isFullWidth  Whether to span the full module width (no list alongside it).
 */

$button_label  = $args['buttonLabel'] ?? __( 'View Event', 'takt' );
$is_full_width = $args['isFullWidth'] ?? false;
$event_id      = get_the_ID();
// Links to the event's MyIGNITE page (where RSVPs actually happen) instead
// of the internal single-event page, falling back to the internal
// permalink only if no Event Website URL was set.
$website   = tribe_get_event_website_url( $event_id );
$permalink = $website ? $website : get_permalink( $event_id );

$start_date    = get_post_meta( $event_id, '_EventStartDate', true );
$venue_id      = get_post_meta( $event_id, '_EventVenueID', true );
// Falls back to the plain-text name captured at ICS import for events
// with no linked Venue post (see inc/helpers/myignite-image-sync.php).
$venue_name    = $venue_id ? get_the_title( $venue_id ) : get_post_meta( $event_id, '_myignite_venue_name', true );

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

// Row-layout overrides. The image keeps its 4:3 ratio but is sized from a fixed
// height instead of the container width, and the date badge drops to the
// compact-row sizing so it does not swamp the smaller image.
$row_card_class    = $is_full_width ? 'sm:flex-row sm:items-center sm:gap-8' : '';
$row_image_class   = $is_full_width ? 'sm:w-auto sm:h-[160px] md:h-[180px] sm:shrink-0' : '';
$row_details_class = $is_full_width ? 'sm:flex-1 sm:min-w-0' : '';
$row_badge_class   = $is_full_width ? 'sm:w-[72px] sm:py-2' : '';
$row_badge_meta    = $is_full_width ? 'sm:text-sm' : '';
$row_badge_day     = $is_full_width ? 'sm:text-[1.75rem]' : '';
?>

<div data-animate="fade-up" class="<?php echo class_name( [ 'md:col-span-2' => $is_full_width ] ); ?>">
	<?php // `dark-surface` switches the focus ring to white (the charcoal default is invisible
	// on this card); the negative outline offset keeps the ring inside the anchor box, since on
	// md+ the charcoal pseudo-element ends exactly at the anchor's top and bottom edges. ?>
	<a href="<?php echo esc_url( $permalink ); ?>" <?php echo $website ? 'target="_blank" rel="noopener noreferrer"' : ''; ?> class="dark-surface relative flex flex-col gap-6 p-4 md:p-8 text-white group no-underline! w-full focus-visible:-outline-offset-2 before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:-inset-y-4 md:before:inset-y-0 md:before:-inset-x-(--bg-extend) <?php echo esc_attr( $row_card_class ); ?>">
		<?php /* Image */ ?>
		<div class="relative flex flex-col items-end w-full overflow-hidden rounded-xl p-2 aspect-[4/3] <?php echo esc_attr( $row_image_class ); ?>">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php
				the_post_thumbnail( 'full', [
					'class' => 'absolute inset-0 w-full h-full object-cover rounded-lg',
					'alt'   => get_the_title(),
				] );
				?>
			<?php endif; ?>

			<?php if ( $start_date ) : ?>
				<div class="relative ml-auto w-[104px] bg-charcoal rounded-lg py-3 px-1 text-center text-white flex flex-col items-center <?php echo esc_attr( $row_badge_class ); ?>">
					<span class="sr-only"><?php echo esc_html( $accessible_date ); ?></span>
					<span class="font-sans font-medium text-base leading-[1.5] <?php echo esc_attr( $row_badge_meta ); ?>" aria-hidden="true"><?php echo esc_html( $day_of_week ); ?></span>
					<span class="font-sans font-bold text-[2.5rem] leading-[1.1] <?php echo esc_attr( $row_badge_day ); ?>" aria-hidden="true"><?php echo esc_html( $day_number ); ?></span>
					<span class="font-sans font-medium text-base leading-[1.5] <?php echo esc_attr( $row_badge_meta ); ?>" aria-hidden="true"><?php echo esc_html( $month_year ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<?php // Content. Wrapped so the row layout can move title, venue and CTA
		// beside the image as one column; the gap matches the anchor's own, so
		// the stacked layout renders identically. ?>
		<div class="flex flex-col gap-6 <?php echo esc_attr( $row_details_class ); ?>">
			<div class="flex flex-col gap-2">
				<h3 class="font-heading text-[3rem] leading-[1.1]"><?php the_title(); ?></h3>

				<?php if ( $venue_name ) : ?>
					<p class="font-sans font-medium text-base leading-[1.5]"><?php echo esc_html( $venue_name ); ?></p>
				<?php endif; ?>
			</div>

			<span class="btn-tertiary text-white group-hover:text-[var(--accent-color)]!">
				<?php echo esc_html( $button_label ); ?>
				<span class="sr-only">
					<?php
					echo esc_html( ': ' . get_the_title() );
					echo $website ? esc_html( ' (' . __( 'opens in a new tab', 'takt' ) . ')' ) : '';
					?>
				</span>
				<span class="btn-tertiary-arrow w-5 h-4 *:w-full *:h-full"><?php theme_asset( 'images/tertiary-arrow.svg' ); ?></span>
			</span>
		</div>
	</a>
</div>
