<?php
/**
 * Card template for Team Member.
 *
 * Expected to be called within a WP_Query loop (the_post() already called).
 * Uses the post featured image (thumbnail) for the photo.
 * Name constructed from first_name + last_name meta.
 */

$first_name   = get_post_meta( get_the_ID(), 'first_name', true );
$last_name    = get_post_meta( get_the_ID(), 'last_name', true );
$bio          = get_post_meta( get_the_ID(), 'bio', true );
$collapse_bio = get_post_meta( get_the_ID(), 'collapse_bio', true );
$contact_link = get_post_meta( get_the_ID(), 'contact_link', true );
$photo_id     = get_post_thumbnail_id( get_the_ID() );
$focal_point  = get_post_meta( get_the_ID(), 'focal_point', true );
$full_name    = trim( $first_name . ' ' . $last_name );
$role         = get_the_excerpt();
$departments  = get_the_terms( get_the_ID(), 'department' );

$show_tags = $args['showTags'] ?? true;
$has_link  = ! empty( $contact_link ) && ! empty( $contact_link['url'] );
$has_bio   = ! empty( $bio );
?>
<div class="card-team-member">
	<div class="flex flex-col h-full text-charcoal">
		<?php if ( $photo_id ) : ?>
			<div class="p-4 rounded-3xl overflow-hidden bg-accent">
				<div class="overflow-hidden rounded-xl">
					<?php
					echo wp_get_attachment_image(
						$photo_id,
						'full',
						false,
						[
							'class' => 'w-full aspect-[395/317] object-cover',
							'style' => 'object-position: ' . theme_image_position( $focal_point ) . ';',
						]
					);
					?>
				</div>
			</div>
		<?php endif; ?>

		<div class="light-surface flex flex-1 flex-col gap-3 p-4 md:gap-6 md:p-6 rounded-3xl overflow-hidden bg-accent">
			<?php if ( $show_tags && ! empty( $departments ) && ! is_wp_error( $departments ) ) : ?>
				<span class="inline-block bg-white/80 border border-accent text-charcoal text-body-small uppercase font-medium tracking-wider px-2 py-1.5 rounded-full w-fit">
					<?php echo esc_html( $departments[0]->name ); ?>
				</span>
			<?php endif; ?>

			<?php if ( ! empty( $full_name ) ) : ?>
				<h3 class="text-header-5 md:text-header-3">
					<?php echo esc_html( $full_name ); ?>
				</h3>
			<?php endif; ?>

			<?php if ( ! empty( $role ) ) : ?>
				<div class="text-header-6 md:text-header-5">
					<?php echo esc_html( $role ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $collapse_bio && $has_bio ) : ?>
				<div class="team-member-bio-collapsible mt-auto" data-collapsed="true">
					<div class="team-member-bio-content overflow-hidden transition-[max-height] duration-300 max-h-[4.5em]">
						<p class="text-body font-sans"><?php echo esc_html( $bio ); ?></p>

						<?php if ( $has_link ) : ?>
							<div class="mt-4">
								<?php
								get_template_part(
									'parts/ThemeButton',
									null,
									[
										'link'      => $contact_link,
										'variation' => 'tertiary',
										'className' => 'hover:text-charcoal!',
									]
								);
								?>
							</div>
						<?php endif; ?>
					</div>
					<button type="button" class="team-member-bio-toggle hidden mt-4 cursor-pointer bg-transparent border-0 p-0 font-sans font-medium text-sm uppercase tracking-wider transition-colors hover:underline">
						<span class="team-member-bio-toggle-more"><?php esc_html_e( 'Read More', 'takt' ); ?></span>
						<span class="team-member-bio-toggle-less hidden"><?php esc_html_e( 'Show Less', 'takt' ); ?></span>
					</button>
				</div>
			<?php else : ?>
				<?php if ( $has_bio ) : ?>
					<p class="text-body font-sans"><?php echo esc_html( $bio ); ?></p>
				<?php endif; ?>

				<?php if ( $has_link ) : ?>
					<div class="mt-auto">
						<?php
						get_template_part(
							'parts/ThemeButton',
							null,
							[
								'link'      => $contact_link,
								'variation' => 'tertiary',
								'className' => 'hover:text-charcoal!',
							]
						);
						?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
