<?php
/**
 * Card template for The Events Calendar events (compact carousel variant).
 *
 * Mirrors `parts/card-post.php` so events render with the same vertical card
 * pattern the Dynamic Content Carousel uses for posts. Distinct from
 * `parts/card-tribe_events.php` — that is the larger two-panel card used by
 * the Featured Events block.
 *
 * Expected to be called within a WP_Query loop (the_post() already called).
 */

$event_id          = get_the_ID();
$event_terms       = get_the_terms( $event_id, 'tribe_events_cat' );
$primary_term      = ( is_array( $event_terms ) && ! empty( $event_terms ) ) ? $event_terms[0] : null;
$primary_term_name = $primary_term ? $primary_term->name : '';
$accent_class      = $primary_term ? get_term_meta( $primary_term->term_id, 'accent_color', true ) : '';
$schedule          = function_exists( 'tribe_events_event_schedule_details' )
	? tribe_events_event_schedule_details( $event_id )
	: '';
$show_tags         = $args['showTags'] ?? true;

// Links to the event's MyIGNITE page (where RSVPs actually happen) instead
// of the internal single-event page, falling back to the internal
// permalink only if no Event Website URL was set.
$website   = tribe_get_event_website_url( $event_id );
$permalink = $website ? $website : get_permalink( $event_id );
?>
<div class="card-post <?php echo esc_attr( $accent_class ); ?>">
	<a href="<?php echo esc_url( $permalink ); ?>" <?php echo $website ? 'target="_blank" rel="noopener noreferrer"' : 'rel="bookmark"'; ?> class="block group no-underline! h-full">
		<div class="flex flex-col gap-6 h-full dark:text-white">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="mask-small relative overflow-hidden">
					<?php
					the_post_thumbnail( 'full', [
						'class' => 'w-full aspect-[281/333] object-cover group-hover:scale-105 transition-transform duration-300',
					] );
					?>
				</div>
			<?php endif; ?>

			<div class="card-post-content flex flex-col gap-6">
				<?php if ( $show_tags && $primary_term_name ) : ?>
					<span class="inline-block bg-accent-light text-charcoal text-body-small uppercase font-medium tracking-wider px-2 py-1.5 rounded-full w-fit">
						<?php echo esc_html( $primary_term_name ); ?>
					</span>
				<?php endif; ?>

				<h3 class="text-header-3">
					<?php the_title(); ?>
				</h3>

				<?php if ( $schedule ) : ?>
					<div class="text-body-small uppercase font-medium tracking-wider">
						<?php echo wp_kses_post( $schedule ); ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="mt-auto">
				<span class="card-post-cta btn-tertiary group-hover:text-[var(--accent-color)]! transition-colors">
					<?php esc_html_e( 'View Event', 'takt' ); ?>
					<span class="sr-only">
						<?php
						echo esc_html( ': ' . get_the_title() );
						echo $website ? esc_html( ' (' . __( 'opens in a new tab', 'takt' ) . ')' ) : '';
						?>
					</span>
					<span class="btn-tertiary-arrow w-5 h-4 *:w-full *:h-full"><?php theme_asset( 'images/tertiary-arrow.svg' ); ?></span>
				</span>
			</div>
		</div>
	</a>
</div>
