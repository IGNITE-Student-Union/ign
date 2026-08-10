<?php
$hasImage = ! empty( $image['id'] );
$isDark = $backgroundType === 'image' && $hasImage;
?>

<section <?php theme_block_props( [ 'call-to-action py-16' => true, $accentColor => ! empty( $accentColor ) ] ); ?>>
<div data-animate="fade-up" class="
<?php
echo class_name(
	[
		'call-to-action-inner container relative py-16' => true,
		'before:absolute before:bg-accent before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)' => ! $isDark,
		'dark-surface dark before:absolute before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend) before:bg-[linear-gradient(90deg,rgba(31,31,29,0.5)_17.715%,rgba(31,31,29,0)_85.991%),linear-gradient(90deg,rgba(31,31,29,0.2)_0%,rgba(31,31,29,0.2)_100%)] before:z-1' => $isDark,
	]
);
?>
  ">

	<?php if ( $isDark && $hasImage ) : ?>
		<div class="absolute inset-y-0 -inset-x-[calc(var(--side-gutter)/2)] md:-inset-x-(--bg-extend) overflow-hidden rounded-3xl">
			<?php
			echo wp_get_attachment_image(
				$image['id'],
				'full',
				false,
				[
					'class' => 'w-full h-full object-cover',
					'style' => 'object-position: ' . theme_image_position( $image['focalPoint'] ?? null ) . ';',
					'alt'   => '',
				]
			);
			?>
		</div>
	<?php endif; ?>

	<div class="relative z-2 max-w-2xl">
	  <?php
		get_template_part(
			'parts/ThemeHeading',
			null,
			[
				'eyebrow'          => $eyebrow,
				'heading'          => $heading,
				'headingSize'      => 2,
				'description'      => $description,
				'buttons'          => $buttons,
				'buttonsClassName' => 'max-sm:flex-col max-sm:items-start',
			]
		);
		?>
	</div>

  </div>
</section>
