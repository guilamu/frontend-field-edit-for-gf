<?php

defined( 'ABSPATH' ) || exit;

class FFE_Sanitizer {
	public static function sanitize( $setting_key, $value ) {
		if ( FFE_Config_Resolver::SETTING_CHOICES === $setting_key ) {
			return self::sanitize_choices( $value );
		}

		if ( FFE_Config_Resolver::SETTING_NAME_FIELDS === $setting_key ) {
			return self::sanitize_name_fields( $value );
		}

		if ( FFE_Config_Resolver::SETTING_TIME_SUB_LABELS === $setting_key ) {
			return self::sanitize_time_sub_labels( $value );
		}

		if ( FFE_Config_Resolver::SETTING_ADDRESS_FIELDS === $setting_key ) {
			return self::sanitize_address_fields( $value );
		}

		$value = is_scalar( $value ) ? (string) $value : '';

		switch ( $setting_key ) {
			case FFE_Config_Resolver::SETTING_TITLE:
			case FFE_Config_Resolver::SETTING_LABEL:
			case FFE_Config_Resolver::SETTING_CONSENT_CHECKBOX_LABEL:
				return self::sanitize_label( $value );

				case FFE_Config_Resolver::SETTING_TIME_FORMAT:
					return self::sanitize_time_format( $value );

			case FFE_Config_Resolver::SETTING_REQUIRED:
			case FFE_Config_Resolver::SETTING_EMAIL_CONFIRMATION:
				return self::sanitize_checkbox_value( $value );

			case FFE_Config_Resolver::SETTING_DESCRIPTION:
				return self::sanitize_description( $value );

			case FFE_Config_Resolver::SETTING_PLACEHOLDER:
			case FFE_Config_Resolver::SETTING_DEFAULT_VALUE:
			case FFE_Config_Resolver::SETTING_ADMIN_LABEL:
				return self::sanitize_plain_text( $value );

			default:
				return self::sanitize_plain_text( $value );
		}
	}

	private static function sanitize_label( $value ) {
		$allowed_html = array(
			'strong' => array(),
			'em'     => array(),
			'span'   => array(
				'class' => true,
			),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		);

		return trim( wp_kses( $value, $allowed_html ) );
	}

	private static function sanitize_description( $value ) {
		return trim( wp_kses_post( $value ) );
	}

	private static function sanitize_plain_text( $value ) {
		return trim( wp_strip_all_tags( $value ) );
	}

	private static function sanitize_checkbox_value( $value ) {
		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, array( '1', 'true', 'on', 'yes' ), true ) ? '1' : '0';
	}

	private static function sanitize_choices( $value ) {
		$choices = self::decode_choice_payload( $value );
		$sanitized = array();

		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				continue;
			}

			$sanitized[] = array(
				'text'        => self::sanitize_plain_text( rgar( $choice, 'text' ) ),
				'value'       => self::sanitize_plain_text( rgar( $choice, 'value' ) ),
				'isSelected'  => '1' === self::sanitize_checkbox_value( rgar( $choice, 'isSelected' ) ),
				'key'         => sanitize_text_field( (string) rgar( $choice, 'key' ) ),
				'sourceIndex' => isset( $choice['sourceIndex'] ) ? absint( $choice['sourceIndex'] ) : null,
			);
		}

		return $sanitized;
	}

	private static function sanitize_time_sub_labels( $value ) {
		$time_fields = self::decode_json_array_payload( $value );
		$sanitized   = array();

		foreach ( $time_fields as $time_field ) {
			if ( ! is_array( $time_field ) ) {
				continue;
			}

			$sanitized[] = array(
				'id'           => sanitize_text_field( (string) rgar( $time_field, 'id' ) ),
				'defaultLabel' => self::sanitize_plain_text( rgar( $time_field, 'defaultLabel' ) ),
				'customLabel'  => self::sanitize_plain_text( rgar( $time_field, 'customLabel' ) ),
			);
		}

		return $sanitized;
	}

	private static function sanitize_time_format( $value ) {
		return '24' === trim( (string) $value ) ? '24' : '12';
	}

	private static function sanitize_name_fields( $value ) {
		$name_fields = self::decode_json_array_payload( $value );
		$sanitized = array();

		foreach ( $name_fields as $name_field ) {
			if ( ! is_array( $name_field ) ) {
				continue;
			}

			$sanitized[] = array(
				'id'           => sanitize_text_field( (string) rgar( $name_field, 'id' ) ),
				'defaultLabel' => self::sanitize_plain_text( rgar( $name_field, 'defaultLabel' ) ),
				'customLabel'  => self::sanitize_plain_text( rgar( $name_field, 'customLabel' ) ),
				'isHidden'     => '1' === self::sanitize_checkbox_value( rgar( $name_field, 'isHidden' ) ),
			);
		}

		return $sanitized;
	}

	private static function sanitize_address_fields( $value ) {
		$address_fields = self::decode_json_array_payload( $value );
		$sanitized = array();

		foreach ( $address_fields as $address_field ) {
			if ( ! is_array( $address_field ) ) {
				continue;
			}

			$sanitized[] = array(
				'id'           => sanitize_text_field( (string) rgar( $address_field, 'id' ) ),
				'defaultLabel' => self::sanitize_plain_text( rgar( $address_field, 'defaultLabel' ) ),
				'customLabel'  => self::sanitize_plain_text( rgar( $address_field, 'customLabel' ) ),
				'isHidden'     => '1' === self::sanitize_checkbox_value( rgar( $address_field, 'isHidden' ) ),
				'isLocked'     => '1' === self::sanitize_checkbox_value( rgar( $address_field, 'isLocked' ) ),
			);
		}

		return $sanitized;
	}

	private static function decode_choice_payload( $value ) {
		return self::decode_json_array_payload( $value );
	}

	private static function decode_json_array_payload( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );

			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return is_array( $value ) ? $value : array();
	}
}