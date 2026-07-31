<?php
/**
 * ContentWithMedia frontend template.
 *
 * Available variables (auto-extracted from $attributes):
 *
 * @var string|null $anchor
 * @var bool        $isReversed
 * @var bool        $reverseRows
 * @var bool        $darkMode
 * @var string      $eyebrow
 * @var string      $heading
 * @var string      $headingSize
 * @var string      $mediaType
 * @var string      $imageLayout
 * @var bool        $showAllImagesOnMobile
 * @var array       $images
 * @var string      $videoSource
 * @var int|null    $videoFile
 * @var string      $videoId
 * @var array       $posterImage
 *
 * Also available:
 * @var WP_Block $block Block instance
 * @var string   $children InnerBlocks content
 */

// Computed helper variables.
$headingLevel  = $headingSize === 'small' ? 3 : 2;
$hasImageMedia = ! empty( array_filter( $images, fn( $img ) => ! empty( $img['id'] ) ) );
$hasVideo      = ( $videoSource === 'file' && ! empty( $videoFile ) ) || ( in_array( $videoSource, [ 'youtube', 'vimeo' ] ) && ! empty( $videoId ) );
$hasMedia      = $mediaType === 'image' ? $hasImageMedia : ( $hasVideo || ! empty( $posterImage['id'] ) );
?>

<section 
<?php
theme_block_props(
	[
		'content-with-media' => true,
		'py-6 sm:py-16' => ! $darkMode,
		'dark bg-transparent!' => $darkMode,
	]
);
?>
>
<div class="
<?php
echo class_name(
	[
		'container grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-10 sm:gap-y-16 items-stretch' => true,
		'max-md:gap-y-0!' => $darkMode,
	]
);
?>
  ">

	<?php // Content slides in from its own side: fade-left when content is on
		// the left column (isReversed=true), fade-right when on the right.
		// The animation is applied to an inner wrapper so the dark-mode
		// background (on the outer's ::before) stays fixed.
		$contentFadeDirection = $isReversed ? 'fade-left' : 'fade-right';
	?>
	<div class="
	<?php
	echo class_name(
		[
			'flex flex-col' => true,
			'relative py-6 md:py-8 before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)' => $darkMode,
			'md:col-start-1' => $isReversed,
			'md:col-start-2' => ! $isReversed,
		]
	);
	?>
	">
	  <div class="flex flex-col justify-center gap-8 flex-1" data-animate="<?php echo esc_attr( $contentFadeDirection ); ?>">
		<?php
		get_template_part(
			'parts/ThemeHeading',
			null,
			[
				'eyebrow'           => $eyebrow,
				'heading'           => $heading,
				'headingSize'       => $headingLevel,
				'enableDescription' => false,
				'enableButtons'     => false,
			]
		);
		?>
		<?php if ( ! empty( $children ) ) : ?>
		  <div class="discourse content-with-media-inner"><?php echo $children; ?></div>
		<?php endif; ?>
	  </div>
	</div>

	<?php if ( $hasMedia ) : ?>
	  <?php // Media animation applied to the inner wrapper so the dark-mode
		// background (on the outer's ::before) stays fixed. ?>
	  <div class="
		<?php
		echo class_name(
			[
				'-order-1' => ! $reverseRows,
				'relative py-6 md:py-8 before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)' => $darkMode,
				'flex items-center md:order-none md:row-start-1' => true,
				'md:col-start-1' => ! $isReversed,
				'md:col-start-2' => $isReversed,
			]
		);
		?>
	  ">
		<div class="
		<?php
		$isGallery = $mediaType === 'image' && $imageLayout === 'gallery';
		echo class_name(
			[
				'w-full' => true,
				'md:min-h-[450px] md:overflow-hidden md:rounded-3xl' => ! $isGallery,
			]
		);
		?>
		" data-animate="fade-in" data-animate-delay="100">
		<?php if ( $mediaType === 'image' && $imageLayout === 'single' ) : ?>
			<?php // Single Image ?>
			<?php if ( ! empty( $images[0]['id'] ) ) : ?>
			<div class="
			<?php
			echo class_name(
				[
					'w-full aspect-[1.08] md:aspect-[624/600] overflow-hidden rounded-3xl' => true,
					'default-mask' => true,
				]
			);
			?>
			">
				<?php
				echo wp_get_attachment_image(
					$images[0]['id'],
					'full',
					false,
					[
						'class' => 'w-full h-full object-cover',
						'style' => 'object-position: ' . theme_image_position( $images[0]['focalPoint'] ?? null ) . ';',
					]
				);
				?>
			</div>
		  <?php endif; ?>

		<?php elseif ( $mediaType === 'image' && $imageLayout === 'gallery' ) : ?>
			<?php // Image Gallery ?>
		  <div class="flex flex-col gap-4">
			<?php if ( ! empty( $images[0]['id'] ) ) : ?>
			  <div class="
				<?php
				echo class_name(
					[
						'w-full aspect-[588/424] overflow-hidden rounded-xl md:rounded-3xl' => true,
					]
				);
				?>
			  ">
				<?php
				echo wp_get_attachment_image(
					$images[0]['id'],
					'full',
					false,
					[
						'class' => 'w-full h-full object-cover',
						'style' => 'object-position: ' . theme_image_position( $images[0]['focalPoint'] ?? null ) . ';',
					]
				);
				?>
			  </div>
			<?php endif; ?>

			<?php if ( ! empty( $images[1]['id'] ) || ! empty( $images[2]['id'] ) ) : ?>
				<?php // Secondary images row - left large, right always small ?>
			  <div class="
				<?php
				echo class_name(
					[
						'flex gap-4 items-start' => true,
						'max-md:hidden' => ! $showAllImagesOnMobile,
					]
				);
				?>
			  ">
				<?php if ( ! empty( $images[1]['id'] ) ) : ?>
				  <div class="flex-1 aspect-[366/203] overflow-hidden rounded-xl md:rounded-3xl">
					<?php
					echo wp_get_attachment_image(
						$images[1]['id'],
						'full',
						false,
						[
							'class' => 'w-full h-full object-cover',
							'style' => 'object-position: ' . theme_image_position( $images[1]['focalPoint'] ?? null ) . ';',
						]
					);
					?>
				  </div>
				<?php endif; ?>

				<?php if ( ! empty( $images[2]['id'] ) ) : ?>
				  <div class="w-[35%] flex-none aspect-[206/123] overflow-hidden rounded-xl md:rounded-3xl">
					<?php
					echo wp_get_attachment_image(
						$images[2]['id'],
						'full',
						false,
						[
							'class' => 'w-full h-full object-cover',
							'style' => 'object-position: ' . theme_image_position( $images[2]['focalPoint'] ?? null ) . ';',
						]
					);
					?>
				  </div>
				<?php endif; ?>
			  </div>
			<?php endif; ?>
		  </div>

		<?php elseif ( $mediaType === 'video' ) : ?>
			<?php // Video ?>
		  <div class="
			<?php
			echo class_name(
				[
					'content-with-media-video-container relative w-full aspect-[1.08] grid grid-cols-1 bg-black overflow-hidden rounded-3xl' => true,
					'default-mask' => ! $darkMode,
				]
			);
			?>
		  ">

			<?php if ( ! empty( $posterImage['id'] ) ) : ?>
			  <button type="button" aria-label="Play video" class="w-full h-full col-1 row-1 cursor-pointer z-2 content-with-media-play-button group relative">
				<?php
				echo wp_get_attachment_image(
					$posterImage['id'],
					'full',
					false,
					[
						'class' => 'absolute! inset-0 w-full! h-full! object-cover pointer-events-none!',
						'style' => 'object-position: ' . theme_image_position( $posterImage['focalPoint'] ?? null ) . ';',
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
			  <video class="w-full h-full object-cover col-1 row-1" playsinline controls preload="none">
				<source src="<?php echo esc_url( $video ); ?>" />
			  </video>
			<?php endif; ?>

			<?php if ( ! empty( $videoId ) && in_array( $videoSource, [ 'vimeo', 'youtube' ] ) ) : ?>
			  <div
				class="w-full h-full embed-video-container *:w-full *:h-full col-1 row-1 *:bg-black"
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
					title="<?php echo esc_attr( $videoSource === 'youtube' ? 'YouTube video player' : 'Vimeo video player' ); ?>"
					class="w-full h-full"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
					referrerpolicy="strict-origin-when-cross-origin"
					allowfullscreen></iframe>
				<?php endif; ?>
			  </div>
			<?php endif; ?>

		  </div>
		<?php endif; ?>

		</div>
	  </div>
	<?php endif; ?>

  </div>
</section>
