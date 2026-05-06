<?php

defined( 'ABSPATH' ) || exit;

class FFE_Validator {
	public static function validate( $setting_key, $value, $field ) {
		$field_type = FFE_Config_Resolver::get_field_type_key( $field );

		if ( FFE_Config_Resolver::SETTING_CHOICES === $setting_key ) {
			return self::validate_choices( $value, $field );
		}

		if ( FFE_Config_Resolver::SETTING_NAME_FIELDS === $setting_key ) {
			return self::validate_name_fields( $value, $field );
		}

		if ( FFE_Config_Resolver::SETTING_TIME_SUB_LABELS === $setting_key ) {
			return self::validate_time_sub_labels( $value, $field );
		}

		if ( FFE_Config_Resolver::SETTING_ADDRESS_FIELDS === $setting_key ) {
			return self::validate_address_fields( $value, $field );
		}

		$value      = is_scalar( $value ) ? (string) $value : '';

		switch ( $setting_key ) {
			case FFE_Config_Resolver::SETTING_TITLE:
				return self::validate_length( $value, 500, 'ffe_invalid_title_length', __( 'Titles must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) );

			case FFE_Config_Resolver::SETTING_LABEL:
				return self::validate_length( $value, 500, 'ffe_invalid_label_length', __( 'Labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) );

			case FFE_Config_Resolver::SETTING_CONSENT_CHECKBOX_LABEL:
				if ( ! in_array( FFE_Config_Resolver::SETTING_CONSENT_CHECKBOX_LABEL, FFE_Config_Resolver::get_available_settings_for_field_type( $field_type ), true ) ) {
					return new WP_Error( 'ffe_invalid_consent_checkbox_label_field_type', __( 'This field type does not support consent checkbox label editing.', 'frontend-field-edit-for-gf' ) );
				}

				return self::validate_length( $value, 500, 'ffe_invalid_consent_checkbox_label_length', __( 'Consent checkbox labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) );

			case FFE_Config_Resolver::SETTING_REQUIRED:
				if ( ! in_array( FFE_Config_Resolver::SETTING_REQUIRED, FFE_Config_Resolver::get_available_settings_for_field_type( $field_type ), true ) ) {
					return new WP_Error( 'ffe_invalid_required_field_type', __( 'This field type does not support required editing.', 'frontend-field-edit-for-gf' ) );
				}

				if ( ! in_array( $value, array( '0', '1' ), true ) ) {
					return new WP_Error( 'ffe_invalid_required_value', __( 'Required must be toggled on or off.', 'frontend-field-edit-for-gf' ) );
				}

				return true;

			case FFE_Config_Resolver::SETTING_EMAIL_CONFIRMATION:
				if ( ! in_array( FFE_Config_Resolver::SETTING_EMAIL_CONFIRMATION, FFE_Config_Resolver::get_available_settings_for_field_type( $field_type ), true ) ) {
					return new WP_Error( 'ffe_invalid_email_confirmation_field_type', __( 'This field type does not support email confirmation editing.', 'frontend-field-edit-for-gf' ) );
				}

				if ( ! in_array( $value, array( '0', '1' ), true ) ) {
					return new WP_Error( 'ffe_invalid_email_confirmation_value', __( 'Email confirmation must be toggled on or off.', 'frontend-field-edit-for-gf' ) );
				}

				return true;

			case FFE_Config_Resolver::SETTING_TIME_FORMAT:
				if ( ! in_array( FFE_Config_Resolver::SETTING_TIME_FORMAT, FFE_Config_Resolver::get_available_settings_for_field_type( $field_type ), true ) ) {
					return new WP_Error( 'ffe_invalid_time_format_field_type', __( 'This field type does not support time format editing.', 'frontend-field-edit-for-gf' ) );
				}

				if ( ! in_array( $value, array( '12', '24' ), true ) ) {
					return new WP_Error( 'ffe_invalid_time_format_value', __( 'Time format must be either 12 hour or 24 hour.', 'frontend-field-edit-for-gf' ) );
				}

				return true;

			case FFE_Config_Resolver::SETTING_DESCRIPTION:
				return self::validate_length( $value, 2000, 'ffe_invalid_description_length', __( 'Descriptions must be 2000 characters or fewer.', 'frontend-field-edit-for-gf' ) );

			case FFE_Config_Resolver::SETTING_PLACEHOLDER:
				if ( ! in_array( FFE_Config_Resolver::SETTING_PLACEHOLDER, FFE_Config_Resolver::get_available_settings_for_field_type( $field_type ), true ) ) {
					return new WP_Error( 'ffe_invalid_placeholder_field_type', __( 'This field type does not support placeholder editing.', 'frontend-field-edit-for-gf' ) );
				}

				return self::validate_length( $value, 200, 'ffe_invalid_placeholder_length', __( 'Placeholders must be 200 characters or fewer.', 'frontend-field-edit-for-gf' ) );

			case FFE_Config_Resolver::SETTING_DEFAULT_VALUE:
				if ( ! in_array( FFE_Config_Resolver::SETTING_DEFAULT_VALUE, FFE_Config_Resolver::get_available_settings_for_field_type( $field_type ), true ) ) {
					return new WP_Error( 'ffe_invalid_default_field_type', __( 'This field type does not support default value editing in v1.', 'frontend-field-edit-for-gf' ) );
				}

				if ( preg_match( '/\{[^\}]+\}/', $value ) ) {
					return new WP_Error( 'ffe_dynamic_default_not_supported', __( 'Dynamic default values and merge tags are out of scope for v1.', 'frontend-field-edit-for-gf' ) );
				}

				if ( 'number' === $field_type && '' !== $value && ! is_numeric( $value ) ) {
					return new WP_Error( 'ffe_invalid_number_default', __( 'Number fields require a numeric default value.', 'frontend-field-edit-for-gf' ) );
				}

				if ( 'date' === $field_type && '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
					return new WP_Error( 'ffe_invalid_date_default', __( 'Date defaults must use the YYYY-MM-DD format or be empty.', 'frontend-field-edit-for-gf' ) );
				}

				return true;

			case FFE_Config_Resolver::SETTING_ADMIN_LABEL:
				return self::validate_length( $value, 500, 'ffe_invalid_admin_label_length', __( 'Admin labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) );

			default:
				return new WP_Error( 'ffe_unknown_setting', __( 'That setting is not supported.', 'frontend-field-edit-for-gf' ) );
		}
	}

	private static function validate_length( $value, $max_length, $error_code, $message ) {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );

		if ( $length > $max_length ) {
			return new WP_Error( $error_code, $message );
		}

		return true;
	}

	private static function validate_time_sub_labels( $time_fields, $field ) {
		$field_type  = FFE_Config_Resolver::get_field_type_key( $field );
		$field_id    = is_object( $field ) && isset( $field->id ) ? absint( $field->id ) : ( is_array( $field ) ? absint( rgar( $field, 'id' ) ) : 0 );
		$time_fields = self::normalize_time_sub_label_payload( $time_fields );
		$allowed_ids = array(
			$field_id . '.1',
			$field_id . '.2',
		);
		$seen_ids = array();

		if ( ! in_array( FFE_Config_Resolver::SETTING_TIME_SUB_LABELS, FFE_Config_Resolver::get_available_settings_for_field_type( $field_type ), true ) ) {
			return new WP_Error( 'ffe_invalid_time_sub_labels_field_type', __( 'This field type does not support Time field sub-label editing.', 'frontend-field-edit-for-gf' ) );
		}

		if ( empty( $time_fields ) ) {
			return new WP_Error( 'ffe_invalid_time_sub_labels_empty', __( 'Time field sub-labels could not be saved because the request payload was invalid.', 'frontend-field-edit-for-gf' ) );
		}

		foreach ( $time_fields as $time_field ) {
			$input_id     = isset( $time_field['id'] ) && is_scalar( $time_field['id'] ) ? (string) $time_field['id'] : '';
			$custom_label = isset( $time_field['customLabel'] ) && is_scalar( $time_field['customLabel'] ) ? (string) $time_field['customLabel'] : '';

			if ( '' === $input_id || ! in_array( $input_id, $allowed_ids, true ) ) {
				return new WP_Error( 'ffe_invalid_time_sub_label_input', __( 'One of the Time field inputs was not recognized.', 'frontend-field-edit-for-gf' ) );
			}

			if ( isset( $seen_ids[ $input_id ] ) ) {
				return new WP_Error( 'ffe_duplicate_time_sub_label_input', __( 'Duplicate Time field inputs were submitted.', 'frontend-field-edit-for-gf' ) );
			}

			$seen_ids[ $input_id ] = true;

			if ( is_wp_error( self::validate_length( $custom_label, 500, 'ffe_invalid_time_custom_label_length', __( 'Time field custom sub-labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) ) ) ) {
				return new WP_Error( 'ffe_invalid_time_custom_label_length', __( 'Time field custom sub-labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) );
			}
		}

		return true;
	}

	private static function validate_choices( $choices, $field ) {
		$field_type = FFE_Config_Resolver::get_field_type_key( $field );
		$choices    = self::normalize_choice_payload( $choices );

		if ( ! in_array( FFE_Config_Resolver::SETTING_CHOICES, FFE_Config_Resolver::get_available_settings_for_field_type( $field_type ), true ) ) {
			return new WP_Error( 'ffe_invalid_choices_field_type', __( 'This field type does not support choice editing.', 'frontend-field-edit-for-gf' ) );
		}

		if ( empty( $choices ) ) {
			return new WP_Error( 'ffe_invalid_choices_empty', __( 'Fields must include at least one choice.', 'frontend-field-edit-for-gf' ) );
		}

		$selected_count = 0;

		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				return new WP_Error( 'ffe_invalid_choice_payload', __( 'Choices could not be saved because the request payload was invalid.', 'frontend-field-edit-for-gf' ) );
			}

			$text = isset( $choice['text'] ) && is_scalar( $choice['text'] ) ? (string) $choice['text'] : '';
			$value = isset( $choice['value'] ) && is_scalar( $choice['value'] ) ? (string) $choice['value'] : '';
			$is_selected = ! empty( $choice['isSelected'] );

			if ( is_wp_error( self::validate_length( $text, 500, 'ffe_invalid_choice_label_length', __( 'Choice labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) ) ) ) {
				return new WP_Error( 'ffe_invalid_choice_label_length', __( 'Choice labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) );
			}

			if ( is_wp_error( self::validate_length( $value, 500, 'ffe_invalid_choice_value_length', __( 'Choice values must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) ) ) ) {
				return new WP_Error( 'ffe_invalid_choice_value_length', __( 'Choice values must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) );
			}

			if ( $is_selected ) {
				$selected_count++;
			}
		}

		if ( ! self::field_allows_multiple_selected_choices( $field ) && $selected_count > 1 ) {
			return new WP_Error( 'ffe_invalid_choice_selection', __( 'This field can only have one selected choice.', 'frontend-field-edit-for-gf' ) );
		}

		return true;
	}

	private static function validate_name_fields( $name_fields, $field ) {
		$field_type = FFE_Config_Resolver::get_field_type_key( $field );
		$field_id = is_object( $field ) && isset( $field->id ) ? absint( $field->id ) : ( is_array( $field ) ? absint( rgar( $field, 'id' ) ) : 0 );
		$name_fields = self::normalize_name_field_payload( $name_fields );
		$allowed_ids = array(
			$field_id . '.2',
			$field_id . '.3',
			$field_id . '.4',
			$field_id . '.6',
			$field_id . '.8',
		);
		$seen_ids = array();

		if ( ! in_array( FFE_Config_Resolver::SETTING_NAME_FIELDS, FFE_Config_Resolver::get_available_settings_for_field_type( $field_type ), true ) ) {
			return new WP_Error( 'ffe_invalid_name_fields_field_type', __( 'This field type does not support Name field input editing.', 'frontend-field-edit-for-gf' ) );
		}

		if ( empty( $name_fields ) ) {
			return new WP_Error( 'ffe_invalid_name_fields_empty', __( 'Name fields could not be saved because the request payload was invalid.', 'frontend-field-edit-for-gf' ) );
		}

		foreach ( $name_fields as $name_field ) {
			$input_id = isset( $name_field['id'] ) && is_scalar( $name_field['id'] ) ? (string) $name_field['id'] : '';
			$custom_label = isset( $name_field['customLabel'] ) && is_scalar( $name_field['customLabel'] ) ? (string) $name_field['customLabel'] : '';

			if ( '' === $input_id || ! in_array( $input_id, $allowed_ids, true ) ) {
				return new WP_Error( 'ffe_invalid_name_field_input', __( 'One of the Name field inputs was not recognized.', 'frontend-field-edit-for-gf' ) );
			}

			if ( isset( $seen_ids[ $input_id ] ) ) {
				return new WP_Error( 'ffe_duplicate_name_field_input', __( 'Duplicate Name field inputs were submitted.', 'frontend-field-edit-for-gf' ) );
			}

			$seen_ids[ $input_id ] = true;

			if ( is_wp_error( self::validate_length( $custom_label, 500, 'ffe_invalid_name_custom_label_length', __( 'Name field custom sub-labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) ) ) ) {
				return new WP_Error( 'ffe_invalid_name_custom_label_length', __( 'Name field custom sub-labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) );
			}
		}

		return true;
	}

	private static function validate_address_fields( $address_fields, $field ) {
		$field_type = FFE_Config_Resolver::get_field_type_key( $field );
		$field_id = is_object( $field ) && isset( $field->id ) ? absint( $field->id ) : ( is_array( $field ) ? absint( rgar( $field, 'id' ) ) : 0 );
		$address_fields = self::normalize_address_field_payload( $address_fields );
		$allowed_ids = array(
			$field_id . '.1',
			$field_id . '.2',
			$field_id . '.3',
			$field_id . '.4',
			$field_id . '.5',
			$field_id . '.6',
		);
		$seen_ids = array();

		if ( ! in_array( FFE_Config_Resolver::SETTING_ADDRESS_FIELDS, FFE_Config_Resolver::get_available_settings_for_field_type( $field_type ), true ) ) {
			return new WP_Error( 'ffe_invalid_address_fields_field_type', __( 'This field type does not support Address field input editing.', 'frontend-field-edit-for-gf' ) );
		}

		if ( empty( $address_fields ) ) {
			return new WP_Error( 'ffe_invalid_address_fields_empty', __( 'Address fields could not be saved because the request payload was invalid.', 'frontend-field-edit-for-gf' ) );
		}

		foreach ( $address_fields as $address_field ) {
			$input_id = isset( $address_field['id'] ) && is_scalar( $address_field['id'] ) ? (string) $address_field['id'] : '';
			$custom_label = isset( $address_field['customLabel'] ) && is_scalar( $address_field['customLabel'] ) ? (string) $address_field['customLabel'] : '';

			if ( '' === $input_id || ! in_array( $input_id, $allowed_ids, true ) ) {
				return new WP_Error( 'ffe_invalid_address_field_input', __( 'One of the Address field inputs was not recognized.', 'frontend-field-edit-for-gf' ) );
			}

			if ( isset( $seen_ids[ $input_id ] ) ) {
				return new WP_Error( 'ffe_duplicate_address_field_input', __( 'Duplicate Address field inputs were submitted.', 'frontend-field-edit-for-gf' ) );
			}

			$seen_ids[ $input_id ] = true;

			if ( is_wp_error( self::validate_length( $custom_label, 500, 'ffe_invalid_address_custom_label_length', __( 'Address field custom sub-labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) ) ) ) {
				return new WP_Error( 'ffe_invalid_address_custom_label_length', __( 'Address field custom sub-labels must be 500 characters or fewer.', 'frontend-field-edit-for-gf' ) );
			}
		}

		return true;
	}

	private static function normalize_choice_payload( $choices ) {
		return self::normalize_json_array_payload( $choices );
	}

	private static function normalize_name_field_payload( $name_fields ) {
		return self::normalize_json_array_payload( $name_fields );
	}

	private static function normalize_time_sub_label_payload( $time_fields ) {
		return self::normalize_json_array_payload( $time_fields );
	}

	private static function normalize_address_field_payload( $address_fields ) {
		return self::normalize_json_array_payload( $address_fields );
	}

	private static function normalize_json_array_payload( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );

			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return is_array( $value ) ? $value : array();
	}

	private static function field_allows_multiple_selected_choices( $field ) {
		$field_type = FFE_Config_Resolver::get_field_type_key( $field );
		$input_type = is_object( $field ) && isset( $field->inputType ) ? $field->inputType : ( is_array( $field ) ? rgar( $field, 'inputType' ) : '' );
		$choice_limit = is_object( $field ) && isset( $field->choiceLimit ) ? $field->choiceLimit : ( is_array( $field ) ? rgar( $field, 'choiceLimit' ) : '' );

		if ( 'checkbox' === $field_type ) {
			return true;
		}

		if ( 'multi_choice' === $field_type ) {
			return 'checkbox' === $input_type || in_array( $choice_limit, array( 'exactly', 'range', 'unlimited' ), true );
		}

		return false;
	}
}