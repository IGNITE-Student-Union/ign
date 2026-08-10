<?php
/**
 * Card template for Testimonial.
 *
 * Expected to be called within a WP_Query loop (the_post() already called).
 * Testimonial is a private CPT - cards do not link anywhere.
 * Uses meta fields: name, message, videoSource, videoFile, videoId.
 * Uses taxonomy: program.
 */

$testimonial_name    = get_post_meta( get_the_ID(), 'name', true );
$testimonial_message = get_post_meta( get_the_ID(), 'message', true );
$video_source        = get_post_meta( get_the_ID(), 'videoSource', true );
$video_file          = get_post_meta( get_the_ID(), 'videoFile', true );
$video_id            = get_post_meta( get_the_ID(), 'videoId', true );

$programs      = get_the_terms( get_the_ID(), 'program' );
$program_name  = ( ! is_wp_error( $programs ) && ! empty( $programs ) ) ? $programs[0]->name : '';

$accent_color  = ! empty( $args['accent_color'] ) ? $args['accent_color'] : '';

$has_video = false;
$video_url = '';

if ( $video_source === 'file' && ! empty( $video_file ) ) {
	$video_url = wp_get_attachment_url( $video_file );
	$has_video = ! empty( $video_url );
} elseif ( $video_source === 'youtube' && ! empty( $video_id ) ) {
	$video_url = 'https://www.youtube.com/embed/' . esc_attr( $video_id ) . '?autoplay=1&rel=0';
	$has_video = true;
} elseif ( $video_source === 'vimeo' && ! empty( $video_id ) ) {
	$video_url = 'https://player.vimeo.com/video/' . esc_attr( $video_id ) . '?autoplay=1';
	$has_video = true;
}
?>
<?php if ( ! empty( $accent_color ) ) : ?>
<div class="<?php echo esc_attr( $accent_color ); ?> h-full">
<?php else : ?>
<div class="h-full">
<?php endif; ?>
	<div class="light-surface flex flex-col h-full bg-accent rounded-3xl p-6">
		<div class="flex flex-col gap-6 pb-8 flex-1">
			<?php if ( ! empty( $testimonial_name ) ) : ?>
				<h3 class="font-heading text-[1.5rem] md:text-[2.5rem] leading-[1.1] text-charcoal"><?php echo esc_html( $testimonial_name ); ?></h3>
			<?php endif; ?>

			<?php if ( ! empty( $program_name ) ) : ?>
				<p class="text-header-5 text-charcoal leading-[1.15]"><?php echo esc_html( $program_name ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $testimonial_message ) ) : ?>
				<blockquote class="text-body font-medium italic text-charcoal leading-normal">
					<?php echo wp_kses_post( $testimonial_message ); ?>
				</blockquote>
			<?php endif; ?>
		</div>

		<?php if ( $has_video ) : ?>
			<button
				class="testimonial-video-btn flex items-center gap-2 text-charcoal font-medium text-body uppercase mt-auto cursor-pointer group"
				data-video-source="<?php echo esc_attr( $video_source ); ?>"
				data-video-url="<?php echo esc_attr( $video_url ); ?>"
				data-testimonial-name="<?php echo esc_attr( $testimonial_name ); ?>"
				aria-label="<?php echo esc_attr( sprintf( __( 'Watch the video - %s', 'takt' ), $testimonial_name ) ); ?>"
			>
				<?php esc_html_e( 'Watch the Video', 'takt' ); ?>
				<span class="btn-tertiary-arrow w-5 h-4 *:w-full *:h-full">
					<?php theme_asset( 'images/tertiary-arrow.svg' ); ?>
				</span>
			</button>
		<?php endif; ?>
	</div>
</div>
