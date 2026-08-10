<?php
/**
 * Quick Navigation Block Template.
 *
 * @var string $title The navigation title.
 * @var array  $links The navigation links.
 * @var string $buttonText The button text.
 * @var string $dropdownBehavior The dropdown behavior (first, placeholder, random).
 * @var string $placeholder The placeholder text when behavior is 'placeholder'.
 * @var string $takt_current_block_id The unique block ID.
 *
 * @package theme
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName -- Attributes from block.json.

$select_id         = esc_attr( $takt_current_block_id ) . '-select';
$dropdown_behavior = $dropdownBehavior;
$placeholder_text  = $placeholder;
?>
<div <?php theme_block_props( 'quick-navigation dark-surface dark bg-charcoal rounded-[32px] p-8 flex flex-col sm:flex-row sm:items-center gap-x-12 gap-y-6 relative overflow-hidden' ); ?>>

	<?php // Title. ?>
	<?php if ( ! empty( $title ) ) : ?>
		<span class="font-heading text-[32px] sm:text-4xl text-white shrink-0 relative z-1 text-center sm:text-left">
			<?php echo wp_kses_post( $title ); ?>
		</span>
	<?php endif; ?>

	<?php // Dropdown. ?>
	<div class="flex-1 relative z-1">
		<select
			id="<?php echo esc_attr( $select_id ); ?>"
			class="quick-navigation-select w-full text-white text-xl"
			aria-label="<?php echo esc_attr( ! empty( $title ) ? $title : 'Navigation' ); ?>"
			<?php if ( 'random' === $dropdown_behavior ) : ?>
				data-random-select="true"
			<?php endif; ?>
		>
			<button>
				<selectedcontent></selectedcontent>
				<span class="quick-navigation-chevron">
					<?php theme_block_asset( 'IconChevron.svg' ); ?>
				</span>
			</button>
			<?php if ( 'placeholder' === $dropdown_behavior ) : ?>
				<option value="" disabled selected>
					<?php echo esc_html( $placeholder_text ); ?>
				</option>
			<?php endif; ?>
			<?php foreach ( $links as $nav_link ) : ?>
				<option value="<?php echo esc_url( $nav_link['url'] ?? '' ); ?>">
					<?php echo esc_html( $nav_link['label'] ?? '' ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<?php // Button. ?>
	<button
		type="button"
		class="quick-navigation-submit btn-secondary whitespace-nowrap relative z-1"
		data-select-id="<?php echo esc_attr( $select_id ); ?>"
	>
		<?php echo esc_html( $buttonText ); ?>
	</button>

</div>
