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
 * Gravity Forms renders these buttons in two different shapes, and the label
 * lives in a different place in each:
 *
 *   - text buttons  => <input type="submit" value="Submit">   (label in @value)
 *   - link buttons  => <button ...>{icon} Submit</button>     (label in text)
 *
 * The link shape is also what GF falls back to when a form has no saved button
 * config, because both call sites default to `array( 'type' => 'link' )` --
 * see GFFormDisplay::gform_footer() and GF_Field_Submit::get_field_input().
 * Reading only @value therefore produced a completely blank button on the
 * link shape, on the front end *and* inside the GF form editor (the editor
 * runs this same filter via GF_Field_Submit::get_field_input()).
 *
 * @param string $button Contains the <input> tag to be filtered.
 * @param array  $form    Contains all the properties of the current form.
 *
 * @return string The filtered button.
 */
function theme_gform_input_to_button( $button, $form ) {
	$fragment = \WP_HTML_Processor::create_fragment( $button );

	if ( ! $fragment || ! $fragment->next_token() ) {
		return $button;
	}

	// Image buttons carry their label as an image (src/alt), not as text.
	// Rewriting them to a text <button> would drop the image entirely, so
	// leave them exactly as Gravity Forms rendered them.
	if ( 'image' === $fragment->get_attribute( 'type' ) ) {
		return $button;
	}

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

	// `data-submission-type` is how Gravity Forms' submission JS tells submit,
	// next and previous apart. Dropping it left GF on its class-name fallback
	// and is the kind of customisation its "unsupported submission flow"
	// warning is aimed at, so it is carried across.
	$attributes     = array( 'id', 'type', 'class', 'onclick', 'data-submission-type' );
	$new_attributes = array();
	foreach ( $attributes as $attribute ) {
		$attribute_value = $fragment->get_attribute( $attribute );
		if ( ! empty( $attribute_value ) ) {
			$new_attributes[] = sprintf( '%s="%s"', $attribute, esc_attr( $attribute_value ) );
		}
	}

	// Take the label from wherever GF put it: the @value attribute first, then
	// the element's own text (which also covers markup another filter already
	// converted to a <button>). wp_strip_all_tags() drops the leading icon SVG
	// that GF adds to link-type buttons.
	$label = trim( (string) $fragment->get_attribute( 'value' ) );
	if ( '' === $label ) {
		$label = trim( wp_strip_all_tags( $button ) );
	}

	// Only if the label is genuinely absent, render GF's own default text.
	// This is a visible label, not an aria-label: a button that reads as
	// blank on screen is a usability bug, not just an assistive-tech one.
	if ( '' === $label ) {
		switch ( current_filter() ) {
			case 'gform_next_button':
				$label = __( 'Next', 'takt' );
				break;
			case 'gform_previous_button':
				$label = __( 'Previous', 'takt' );
				break;
			default:
				$label = __( 'Submit', 'takt' );
		}
	}

	return sprintf( '<button %s>%s</button>', implode( ' ', $new_attributes ), esc_html( $label ) );
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
