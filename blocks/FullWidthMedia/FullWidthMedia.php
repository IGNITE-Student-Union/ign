<?php
/**
 * FullWidthMedia frontend template.
 *
 * Available variables (auto-extracted from $attributes):
 *
 * @var string|null $anchor
 * @var string      $eyebrow
 * @var string      $heading
 * @var string      $description
 * @var array       $buttons
 * @var string      $mediaType
 * @var array       $image
 * @var string      $videoSource
 * @var int|null    $videoFile
 * @var string      $videoId
 * @var array       $posterImage
 *
 * Also available:
 * @var WP_Block $block    Block instance
 * @var string   $children Inner blocks HTML
 */

$hasImageMedia = ! empty( $image['id'] );
$hasVideo = ( $videoSource === 'file' && ! empty( $videoFile ) ) || ( in_array( $videoSource, [ 'youtube', 'vimeo' ] ) && ! empty( $videoId ) );
$hasMedia = $mediaType === 'image' ? $hasImageMedia : ( $hasVideo || ! empty( $posterImage['id'] ) );
?>

<section <?php theme_block_props( 'full-width-media py-6 sm:py-16', empty( $heading ) ? 'aria-label="' . esc_attr__( 'Full Width Media', 'takt' ) . '"' : '' ); ?>>
  <div class="container flex flex-col gap-16">

	<div data-animate="fade-up">
	<?php
	get_template_part(
		'parts/ThemeHeading',
		null,
		[
			'eyebrow'     => $eyebrow,
			'heading'     => $heading,
			'headingSize' => 2,
			'description' => $description,
			'buttons'     => $buttons,
			'columns'     => 2,
		]
	);
	?>
	</div>

	<?php if ( $hasMedia ) : ?>
	  <div class="w-full" data-animate="scale-in" data-animate-delay="150">
		<?php if ( $mediaType === 'image' ) : ?>
			<?php if ( ! empty( $image['id'] ) ) : ?>
			<div class="dark-surface relative before:absolute before:-inset-[18px] sm:before:-inset-(--bg-extend) before:bg-charcoal before:rounded-3xl before:pointer-events-none">
				<div class="w-full h-[294px] sm:h-[600px] default-mask overflow-hidden relative z-1">
					<?php
					echo wp_get_attachment_image(
						$image['id'],
						'full',
						false,
						[
							'class' => 'w-full h-full object-cover',
							'style' => 'object-position: ' . theme_image_position( $image['focalPoint'] ?? null ) . ';',
						]
					);
					?>
				</div>
			</div>
		  <?php endif; ?>

		<?php elseif ( $mediaType === 'video' ) : ?>
		  <div class="dark-surface relative before:absolute before:-inset-[18px] sm:before:-inset-(--bg-extend) before:bg-charcoal before:rounded-3xl before:pointer-events-none">
		  <div class="full-width-media-video-container relative w-full aspect-video grid grid-cols-1 default-mask overflow-hidden z-1">

			<?php if ( ! empty( $posterImage['id'] ) ) : ?>
			  <button type="button" class="w-full h-full min-h-0 col-1 row-1 cursor-pointer z-2 full-width-media-play-button group relative" aria-label="<?php esc_attr_e( 'Play video', 'takt' ); ?>">
				<?php
				echo wp_get_attachment_image(
					$posterImage['id'],
					'full',
					false,
					[
						'class' => 'absolute! inset-0 w-full! h-full! object-cover pointer-events-none!',
						'style' => 'object-position: ' . theme_image_position( $posterImage['focalPoint'] ?? null ) . ';',
						'alt'   => '',
					]
				);
				?>

				<?php if ( $hasVideo ) : ?>
				  <span class="absolute inset-0 flex items-center justify-center z-2" aria-hidden="true">
					<span class="play-icon">
					  <?php theme_block_asset( 'IconPlay.svg' ); ?>
					</span>
				  </span>
				<?php endif; ?>
			  </button>
			<?php endif; ?>

			<?php if ( ! empty( $videoFile ) && $videoSource === 'file' ) : ?>
				<?php $video = wp_get_attachment_url( $videoFile ); ?>
			  <video class="w-full h-full min-h-0 object-cover col-1 row-1" playsinline controls preload="none">
				<source src="<?php echo esc_url( $video ); ?>" />
			  </video>
			<?php endif; ?>

			<?php if ( ! empty( $videoId ) && in_array( $videoSource, [ 'vimeo', 'youtube' ] ) ) : ?>
			  <div
				class="w-full h-full min-h-0 embed-video-container *:w-full *:h-full col-1 row-1 *:bg-black"
				data-source="<?php echo esc_attr( $videoSource ); ?>"
				data-video-id="<?php echo esc_attr( $videoId ); ?>"
			  >
				<?php if ( empty( $posterImage['id'] ) ) : ?>
				  <iframe
					src="
					<?php
					echo esc_url(
						$videoSource === 'youtube'
						? 'https://www.youtube.com/embed/' . $videoId . '?autoplay=0&loop=0&mute=0&controls=1&playsinline=1'
						: 'https://player.vimeo.com/video/' . $videoId . '?autoplay=0&byline=0&portrait=0&badge=0&loop=0&background=0&autopause=false&dnt=true&vimeo_logo=false&title=false'
					);
					?>
					"
					class="w-full h-full"
					title="<?php esc_attr_e( 'Video player', 'takt' ); ?>"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
					referrerpolicy="strict-origin-when-cross-origin"
					allowfullscreen></iframe>
				<?php endif; ?>
			  </div>
			<?php endif; ?>

		  </div>
		  </div>
		<?php endif; ?>
	  </div>
	<?php endif; ?>

  </div>
</section>
