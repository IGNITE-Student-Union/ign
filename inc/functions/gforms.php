<?php

function theme_maybe_dequeue_gravityforms_editor_assets() {
	if ( is_admin() && function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();

		if ( $screen && $screen->is_block_editor() ) {
			wp_dequeue_script( 'gform_gravityforms' );
			wp_dequeue_script( 'gform_gravityforms_admin' );
			wp_dequeue_script( 'gform_placeholder' );
			wp_dequeue_script( 'gform_datepicker_init' );
			wp_dequeue_style( 'gform_formsmain_css' );
			wp_dequeue_style( 'gform_theme_css' );
			wp_dequeue_style( 'gform_ready_class_css' );
			wp_dequeue_style( 'gform_font_awesome' );
		}
	}
}
add_action( 'admin_enqueue_scripts', 'theme_maybe_dequeue_gravityforms_editor_assets', 100 );


/**
 * Add custom "Dynamic" size option to Gravity Forms textareas.
 */
function theme_add_dynamic_textarea_size( $size_options ) {
	array_unshift(
		$size_options,
		array(
			'value' => 'dynamic',
			'text'  => __( 'Dynamic', 'takt' ),
		)
	);
	return $size_options;
}
add_filter( 'gform_field_size_choices', 'theme_add_dynamic_textarea_size', 10, 1 );

function theme_add_dynamic_class( $classes, $field, $form ) {
	if ( $field->type === 'textarea' && $field->size === 'dynamic' ) {
		$classes .= ' dynamic';
	}
	return $classes;
}
add_filter( 'gform_field_css_class', 'theme_add_dynamic_class', 10, 3 );

function theme_set_textarea_rows_for_dynamic_size( $content, $field, $value, $lead_id, $form_id ) {
	if ( $field->type === 'textarea' && $field->size === 'dynamic' ) {
		$content = preg_replace( '/(<textarea[^>]*?)rows=["\']\d+["\']/', '$1rows="1"', $content );
	}
	return $content;
}
add_filter( 'gform_field_content', 'theme_set_textarea_rows_for_dynamic_size', 10, 5 );


/**
 * Filters the next, previous and submit buttons.
 * Replaces the form's <input> buttons with <button> while maintaining attributes from original <input>.
 *
 * @param string $button Contains the <input> tag to be filtered.
 * @param array  $form    Contains all the properties of the current form.
 *
 * @return string The filtered button.
 */
function theme_gform_input_to_button( $button, $form ) {
	$fragment = \WP_HTML_Processor::create_fragment( $button );
	$fragment->next_token();

	if ( ! $fragment->has_class( 'gform-theme-button--secondary' ) ) {
		$button_class = 'btn-primary';
	} else {
		$button_class = 'btn-secondary';
	}

	$custom_classes = apply_filters( 'theme_gform_input_to_button_classes', array( $button_class, 'cursor-pointer!', 'gform-theme-no-framework' ) );
	if ( ! empty( $custom_classes ) ) {
		foreach ( $custom_classes as $custom_class ) {
			$fragment->add_class( $custom_class );
		}
	}

	$attributes = array( 'id', 'type', 'class', 'onclick' );
	$new_attributes = array();
	foreach ( $attributes as $attribute ) {
		$value = $fragment->get_attribute( $attribute );
		if ( ! empty( $value ) ) {
			$new_attributes[] = sprintf( '%s="%s"', $attribute, esc_attr( $value ) );
		}
	}

	$label = esc_html( $fragment->get_attribute( 'value' ) );

	// Some forms are configured with no button text, which renders a blank,
	// unlabeled button. Fall back to an aria-label so it's still announced
	// to assistive tech without changing the (already blank) visible output.
	if ( empty( $label ) ) {
		switch ( current_filter() ) {
			case 'gform_next_button':
				$fallback_label = __( 'Next', 'takt' );
				break;
			case 'gform_previous_button':
				$fallback_label = __( 'Previous', 'takt' );
				break;
			default:
				$fallback_label = __( 'Submit', 'takt' );
		}
		$new_attributes[] = sprintf( 'aria-label="%s"', esc_attr( $fallback_label ) );
	}

	return sprintf( '<button %s>%s</button>', implode( ' ', $new_attributes ), $label );
}
add_filter( 'gform_next_button', 'theme_gform_input_to_button', 10, 2 );
add_filter( 'gform_previous_button', 'theme_gform_input_to_button', 10, 2 );
add_filter( 'gform_submit_button', 'theme_gform_input_to_button', 10, 2 );


/**
 * Remove fields with "[remove]" in their name before Gravity Forms submission.
 */
function theme_gform_remove_fields_with_remove( $form ) {
	foreach ( $form['fields'] as $i => $field ) {
		if ( strpos( $field->label ?? '', '[remove]' ) !== false || strpos( $field->adminLabel ?? '', '[remove]' ) !== false || strpos( $field->name ?? '', '[remove]' ) !== false ) {
			unset( $form['fields'][ $i ] );
		}
	}
	// Reindex fields array
	$form['fields'] = array_values( $form['fields'] );
	return $form;
}
add_filter( 'gform_pre_submission_filter', 'theme_gform_remove_fields_with_remove', 10, 1 );


/**
 * Validate min/max amount for fields with form-amount-min-### or form-amount-max-### classes.
 * To be used on fields that doesn't support this type of validation.
 */
function theme_gform_validate_min_max( $result, $value, $form, $field ) {
	if ( isset( $field->cssClass ) && preg_match_all( '/form-amount-(min|max)-(\d+)/', $field->cssClass, $matches, PREG_SET_ORDER ) ) {
		$filtered_value = preg_replace( '/[^\d,\.]/', '', $value );

		foreach ( $matches as $match ) {
			$type = $match[1]; // 'min' or 'max'
			$limit = floatval( $match[2] );
			$val = floatval( $filtered_value );

			// Format the limit as currency using Gravity Forms' GFCommon::to_money
			if ( class_exists( 'GFCommon' ) ) {
				$currency = \GFCommon::get_currency();
				$formatted_limit = \GFCommon::to_money( $limit, $currency );
			} else {
				$formatted_limit = number_format( $limit, 2 );
			}

			if ( $type === 'min' && $val < $limit ) {
				$result['is_valid'] = false;
				$result['message'] = sprintf( __( 'Please enter a value of at least %s.', 'takt' ), $formatted_limit );
				break;
			}
			if ( $type === 'max' && $val > $limit ) {
				$result['is_valid'] = false;
				$result['message'] = sprintf( __( 'Please enter a value no greater than %s.', 'takt' ), $formatted_limit );
				break;
			}
		}
	}
	return $result;
}
add_filter( 'gform_field_validation', 'theme_gform_validate_min_max', 10, 4 );
