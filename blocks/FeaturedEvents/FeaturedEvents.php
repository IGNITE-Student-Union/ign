<?php
/**
 * Featured Events block template.
 *
 * Renders the first upcoming/past event as a featured card (image + full
 * details) alongside a compact, text-only list of the remaining events,
 * supporting automatic and manual selection modes.
 *
 * @var string|null $anchor
 * @var string      $eyebrow
 * @var string      $heading
 * @var string      $description
 * @var string      $postsSource
 * @var array       $selectedPosts
 * @var int         $postsLimit
 * @var string      $eventList
 * @var array       $selectedEventCategories
 * @var array       $selectedTags
 * @var bool        $hideIfEmpty
 * @var array       $buttons
 * @var string      $buttonLabel
 *
 * @var WP_Block $block    Block instance
 * @var string   $children InnerBlocks content
 */

$query     = null;
$has_posts = false;

if ( $postsSource === 'manual' && ! empty( $selectedPosts ) ) {
	$query = new WP_Query( [
		'post_type'      => 'tribe_events',
		'post__in'       => $selectedPosts,
		'orderby'        => 'post__in',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	] );
	$has_posts = $query->have_posts();
} elseif ( $postsSource === 'automatic' ) {
	$now = current_time( 'Y-m-d H:i:s' );

	$args = [
		'post_type'      => 'tribe_events',
		'posts_per_page' => $postsLimit,
		'post_status'    => 'publish',
		'meta_key'       => '_EventStartDate',
		'orderby'        => 'meta_value',
		'meta_query'     => [],
		'tax_query'      => [],
	];

	if ( $eventList === 'past' ) {
		$args['order']         = 'DESC';
		$args['meta_query'][]  = [
			'key'     => '_EventStartDate',
			'value'   => $now,
			'compare' => '<',
			'type'    => 'DATETIME',
		];
	} else {
		$args['order']         = 'ASC';
		$args['meta_query'][]  = [
			'key'     => '_EventStartDate',
			'value'   => $now,
			'compare' => '>=',
			'type'    => 'DATETIME',
		];
	}

	if ( ! empty( $selectedEventCategories ) ) {
		$args['tax_query'][] = [
			'taxonomy' => 'tribe_events_cat',
			'field'    => 'term_id',
			'terms'    => $selectedEventCategories,
		];
	}

	if ( ! empty( $selectedTags ) ) {
		$args['tax_query'][] = [
			'taxonomy' => 'post_tag',
			'field'    => 'term_id',
			'terms'    => $selectedTags,
		];
	}

	$query     = new WP_Query( $args );
	$has_posts = $query->have_posts();
}
?>

<?php if ( empty( $hideIfEmpty ) || $has_posts ) : ?>
	<section <?php theme_block_props( 'featured-events' ); ?>>
		<div class="container">
			<div data-animate="fade-up">
			<?php
			get_template_part( 'parts/ThemeHeading', null, [
				'className'         => 'mb-16',
				'eyebrow'           => $eyebrow,
				'heading'           => $heading,
				'headingSize'       => 2,
				'description'       => $description,
				'columns'           => 2,
				'enableButtons'     => true,
				'buttons'           => $buttons ?? [],
				'buttonVariations'  => [ 'primary' ],
				'enableDescription' => true,
			] );
			?>
			</div>

			<?php if ( $has_posts ) : ?>
				<?php
				$events           = $query->posts;
				$featured_event   = array_shift( $events );
				$remaining_events = $events;
				?>
				<div class="featured-events-list grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-8 items-start" data-animate-stagger>
					<?php
					global $post;
					$post = $featured_event;
					setup_postdata( $post );
					get_template_part( 'parts/card', 'tribe_events', [
						'buttonLabel' => $buttonLabel,
						'isFullWidth' => empty( $remaining_events ),
					] );
					?>

					<?php if ( ! empty( $remaining_events ) ) : ?>
						<?php // `dark-surface`: white focus ring for the rows, since the charcoal default is invisible here. ?>
						<div class="dark-surface relative p-4 md:p-8 flex flex-col divide-y divide-white/20 before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:-inset-y-4 md:before:inset-y-0 md:before:-inset-x-(--bg-extend)">
							<?php foreach ( $remaining_events as $event ) : ?>
								<?php
								$post = $event;
								setup_postdata( $post );
								get_template_part( 'parts/card', 'tribe_events-compact' );
								?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>
<?php wp_reset_postdata(); ?>
