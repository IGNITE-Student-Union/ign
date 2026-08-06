<?php
/**
 * Card template for Blog Post.
 *
 * Expected to be called within a WP_Query loop (the_post() already called).
 * Uses alternating layout via CSS :nth-child(even) on parent grid.
 */

$show_tags    = $args['showTags'] ?? true;
$focal_point  = get_post_meta( get_the_ID(), 'focal_point', true );
$categories   = get_the_category();
$accent_class = '';
if ( ! empty( $categories ) ) {
	$primary_cat  = $categories[0];
	$accent_color = get_term_meta( $primary_cat->term_id, 'accent_color', true );
	if ( ! empty( $accent_color ) ) {
		$accent_class = $accent_color;
	}
}
?>
<div class="card-post <?php echo esc_attr( $accent_class ); ?>">
	<a href="<?php the_permalink(); ?>" class="block group no-underline! h-full">
		<div class="flex flex-col gap-6 h-full dark:text-white">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="mask-small relative overflow-hidden aspect-[281/333]">
					<?php
					the_post_thumbnail( 'full', [
						'class' => 'absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300',
						'style' => 'object-position: ' . theme_image_position( $focal_point ) . ';',
					] );
					?>
				</div>
			<?php endif; ?>

			<div class="card-post-content flex flex-col gap-6">
				<?php if ( $show_tags && ! empty( $categories ) ) : ?>
					<span class="inline-block bg-accent-light text-charcoal text-body-small uppercase font-medium tracking-wider px-2 py-1.5 rounded-full w-fit">
						<?php echo esc_html( $categories[0]->name ); ?>
					</span>
				<?php endif; ?>

				<h3 class="text-header-3">
					<?php the_title(); ?>
				</h3>

				<div class="text-body-small uppercase font-medium tracking-wider">
					<?php echo esc_html( get_the_date( 'F j, Y' ) ); ?>
				</div>
			</div>

			<div class="mt-auto">
				<span class="card-post-cta btn-tertiary group-hover:text-[var(--accent-color)]! transition-colors">
					<?php esc_html_e( 'Learn More', 'takt' ); ?>
					<span class="btn-tertiary-arrow w-5 h-4 *:w-full *:h-full"><?php theme_asset( 'images/tertiary-arrow.svg' ); ?></span>
				</span>
			</div>
		</div>
	</a>
</div>
