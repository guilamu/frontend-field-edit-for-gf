<?php

defined( 'ABSPATH' ) || exit;

require_once FFE_GF_PLUGIN_DIR . 'includes/class-ffe-sanitizer.php';
require_once FFE_GF_PLUGIN_DIR . 'includes/class-ffe-validator.php';

class FFE_Save_Controller {
	/**
	 * @var FFE_AddOn
	 */
	private $addon;

	public function __construct( FFE_AddOn $addon ) {
		$this->addon = $addon;

		add_action( 'wp_ajax_ffe_save_field_setting', array( $this, 'handle_request' ) );
	}

	public function handle_request() {
		$form_id      = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$field_id     = isset( $_POST['field_id'] ) ? absint( wp_unslash( $_POST['field_id'] ) ) : 0;
		$target_type  = isset( $_POST['target_type'] ) ? sanitize_key( wp_unslash( $_POST['target_type'] ) ) : 'field';
		$setting      = isset( $_POST['setting_key'] ) ? sanitize_key( wp_unslash( $_POST['setting_key'] ) ) : '';
		$value        = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		$form_hash    = isset( $_POST['form_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['form_hash'] ) ) : '';
		$current_page = isset( $_POST['current_page'] ) ? max( 1, absint( wp_unslash( $_POST['current_page'] ) ) ) : 1;

		if ( ! is_user_logged_in() ) {
			$this->send_error( 'unauthorized', __( 'You must be logged in to edit form fields.', 'frontend-field-edit-for-gf' ), 403 );
		}

		if ( ! $form_id || empty( $setting ) ) {
			$this->send_error( 'invalid_request', __( 'Missing form or setting information.', 'frontend-field-edit-for-gf' ), 400 );
		}

		if ( 'form' !== $target_type && ( ! $field_id || 'field' !== $target_type ) ) {
			$this->send_error( 'invalid_request', __( 'Missing field information for this edit request.', 'frontend-field-edit-for-gf' ), 400 );
		}

		if ( ! check_ajax_referer( 'ffe_save_field_setting_' . $form_id, 'nonce', false ) ) {
			$this->addon->log_error( __METHOD__ . '(): Nonce validation failed.' );
			$this->send_error( 'invalid_nonce', __( 'Your edit session expired. Refresh the page and try again.', 'frontend-field-edit-for-gf' ), 403 );
		}

		$plugin_settings = $this->addon->get_plugin_settings();
		$required_cap    = rgar( $plugin_settings, 'required_capability', FFE_AddOn::CAPABILITY );

		if ( empty( $plugin_settings['enable_frontend_field_edit'] ) || ! current_user_can( $required_cap ) ) {
			$this->addon->log_error( __METHOD__ . '(): Permission denied for user ' . get_current_user_id() . '.' );
			$this->send_error( 'permission_denied', __( 'You do not have permission to edit this form from the frontend.', 'frontend-field-edit-for-gf' ), 403 );
		}

		if ( ! $this->check_rate_limit( $form_id ) ) {
			$this->addon->log_error( __METHOD__ . '(): Rate limit exceeded for user ' . get_current_user_id() . ' on form ' . $form_id . '.' );
			$this->send_error( 'rate_limited', __( 'Too many frontend field edits were attempted. Please wait a minute and try again.', 'frontend-field-edit-for-gf' ), 429 );
		}

		$form = GFAPI::get_form( $form_id );
		if ( empty( $form ) || is_wp_error( $form ) ) {
			$this->send_error( 'missing_form', __( 'The requested form could not be loaded.', 'frontend-field-edit-for-gf' ), 404 );
		}

		$form_settings = $this->addon->get_form_settings_with_defaults( $form );
		$context       = FFE_Config_Resolver::resolve_editor_context( $form, $plugin_settings, $form_settings );

		if ( ! $context['allowed'] ) {
			$this->send_error( 'form_not_enabled', __( 'Frontend editing is not enabled for this form.', 'frontend-field-edit-for-gf' ), 403 );
		}

		if ( ! hash_equals( $context['form_hash'], $form_hash ) ) {
			$this->addon->log_error( __METHOD__ . '(): Form hash conflict for form ' . $form_id . '.' );
			$this->send_error( 'form_conflict', __( 'The form changed since you opened the editor.', 'frontend-field-edit-for-gf' ), 409 );
		}

		if ( 'form' === $target_type ) {
			$this->handle_form_setting_update( $form, $form_id, $setting, $value, $plugin_settings, $context );
		}

		$field_index = $this->find_field_index( $form, $field_id );
		if ( false === $field_index ) {
			$this->send_error( 'missing_field', __( 'The requested field could not be found.', 'frontend-field-edit-for-gf' ), 404 );
		}

		$field            = $form['fields'][ $field_index ];
		$field_key        = (string) $field_id;
		$allowed_settings = isset( $context['settings'][ $field_key ]['settings'] ) ? $context['settings'][ $field_key ]['settings'] : array();

		if ( ! in_array( $setting, $allowed_settings, true ) ) {
			$this->addon->log_error( __METHOD__ . '(): Blocked setting ' . $setting . ' requested for field ' . $field_id . '.' );
			$this->send_error( 'blocked_setting', __( 'That setting is not enabled for this field.', 'frontend-field-edit-for-gf' ), 403 );
		}

		$sanitized_value = FFE_Sanitizer::sanitize( $setting, $value );
		$validation      = FFE_Validator::validate( $setting, $sanitized_value, $field );

		if ( is_wp_error( $validation ) ) {
			$this->addon->log_error( __METHOD__ . '(): Validation failed for ' . $setting . ' on field ' . $field_id . ': ' . $validation->get_error_message() );
			$this->send_error( $validation->get_error_code(), $validation->get_error_message(), 422 );
		}

		$old_value = $this->get_field_setting_value( $field, $setting );
		$this->set_field_setting_value( $field, $setting, $sanitized_value );
		$form['fields'][ $field_index ] = $field;

		$update_result = GFAPI::update_form( $form );
		if ( is_wp_error( $update_result ) ) {
			$this->addon->log_error( __METHOD__ . '(): GFAPI::update_form failed for form ' . $form_id . ': ' . $update_result->get_error_message() );
			$this->send_error( 'update_failed', __( 'The form update failed.', 'frontend-field-edit-for-gf' ), 500 );
		}

		$updated_form = GFAPI::get_form( $form_id );
		$updated_hash = is_array( $updated_form ) ? FFE_Config_Resolver::get_form_hash( $updated_form ) : $context['form_hash'];
		$updated_field = is_array( $updated_form ) ? $updated_form['fields'][ $field_index ] : $field;
		$field_payload = $this->build_field_item_payload( $updated_field, $allowed_settings );

		if ( ! empty( $plugin_settings['enable_audit_log'] ) ) {
			FFE_Audit_Log::log_change(
				array(
					'form_id'     => $form_id,
					'field_id'    => $field_id,
					'setting_key' => $setting,
					'old_value'   => $old_value,
					'new_value'   => $sanitized_value,
					'status'      => 'success',
					'page_url'    => wp_get_referer(),
				)
			);
		}

		wp_send_json_success(
			array(
				'formHash'          => $updated_hash,
				'item'              => $field_payload,
				'field'             => $field_payload,
				'setting'           => $setting,
				'value'             => $this->get_field_setting_value( $updated_field, $setting ),
				'renderedFieldHtml' => $this->get_rendered_field_markup( $updated_form, $updated_field, $current_page ),
			)
		);
	}

	private function handle_form_setting_update( $form, $form_id, $setting, $value, $plugin_settings, $context ) {
		$allowed_items = rgar( $context, 'form_items', array() );

		if ( empty( $allowed_items[ $setting ]['settings'] ) || ! in_array( $setting, $allowed_items[ $setting ]['settings'], true ) ) {
			$this->addon->log_error( __METHOD__ . '(): Blocked setting ' . $setting . ' requested for form ' . $form_id . '.' );
			$this->send_error( 'blocked_setting', __( 'That setting is not enabled for this form.', 'frontend-field-edit-for-gf' ), 403 );
		}

		$sanitized_value = FFE_Sanitizer::sanitize( $setting, $value );
		$validation      = FFE_Validator::validate( $setting, $sanitized_value, $form );

		if ( is_wp_error( $validation ) ) {
			$this->addon->log_error( __METHOD__ . '(): Validation failed for ' . $setting . ' on form ' . $form_id . ': ' . $validation->get_error_message() );
			$this->send_error( $validation->get_error_code(), $validation->get_error_message(), 422 );
		}

		$old_value = $this->get_form_setting_value( $form, $setting );
		$this->set_form_setting_value( $form, $setting, $sanitized_value );

		$update_result = GFAPI::update_form( $form );
		if ( is_wp_error( $update_result ) ) {
			$this->addon->log_error( __METHOD__ . '(): GFAPI::update_form failed for form ' . $form_id . ': ' . $update_result->get_error_message() );
			$this->send_error( 'update_failed', __( 'The form update failed.', 'frontend-field-edit-for-gf' ), 500 );
		}

		$updated_form = GFAPI::get_form( $form_id );
		$updated_hash = is_array( $updated_form ) ? FFE_Config_Resolver::get_form_hash( $updated_form ) : $context['form_hash'];

		if ( ! empty( $plugin_settings['enable_audit_log'] ) ) {
			FFE_Audit_Log::log_change(
				array(
					'form_id'     => $form_id,
					'field_id'    => 0,
					'setting_key' => $setting,
					'old_value'   => $old_value,
					'new_value'   => $sanitized_value,
					'status'      => 'success',
					'page_url'    => wp_get_referer(),
				)
			);
		}

		wp_send_json_success(
			array(
				'formHash' => $updated_hash,
				'item'     => array(
					'id'       => $setting,
					'type'     => 'form',
					'label'    => FFE_Config_Resolver::SETTING_TITLE === $setting ? esc_html__( 'Form Title', 'frontend-field-edit-for-gf' ) : esc_html__( 'Form Description', 'frontend-field-edit-for-gf' ),
					'settings' => array( $setting ),
					'values'   => array(
						$setting => (string) $this->get_form_setting_value( is_array( $updated_form ) ? $updated_form : $form, $setting ),
					),
				),
				'setting'  => $setting,
				'value'    => $sanitized_value,
			)
		);
	}

	private function get_rendered_field_markup( $form, $field, $current_page ) {
		if ( ! is_array( $form ) || empty( $form['id'] ) || ! class_exists( 'GFFormDisplay' ) || ! method_exists( 'GFFormDisplay', 'get_field' ) ) {
			return '';
		}

		if ( ! is_object( $field ) ) {
			if ( class_exists( 'GF_Fields' ) && method_exists( 'GF_Fields', 'create' ) ) {
				$field = GF_Fields::create( $field );
			}

			if ( ! is_object( $field ) ) {
				return '';
			}
		}

		GFFormDisplay::set_current_page( absint( $form['id'] ), max( 1, absint( $current_page ) ) );

		$markup = GFFormDisplay::get_field( $field, '', false, $form, null, max( 1, absint( $current_page ) ) );

		return is_string( $markup ) ? $markup : '';
	}

	private function send_error( $code, $message, $status = 400 ) {
		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}

	private function check_rate_limit( $form_id ) {
		$key = 'ffe_rate_' . get_current_user_id() . '_' . $form_id;
		$count = (int) get_transient( $key );

		if ( $count >= 30 ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	private function find_field_index( $form, $field_id ) {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $index => $field ) {
			if ( absint( $this->get_field_property( $field, 'id' ) ) === $field_id ) {
				return $index;
			}
		}

		return false;
	}

	private function get_field_setting_value( $field, $setting_key ) {
		switch ( $setting_key ) {
			case FFE_Config_Resolver::SETTING_LABEL:
				return (string) $this->get_field_property( $field, 'label' );

			case FFE_Config_Resolver::SETTING_CONSENT_CHECKBOX_LABEL:
				return (string) $this->get_field_property( $field, 'checkboxLabel' );

			case FFE_Config_Resolver::SETTING_CHOICES:
				return $this->get_field_choices( $field );

			case FFE_Config_Resolver::SETTING_REQUIRED:
				return ! empty( $this->get_field_property( $field, 'isRequired' ) ) ? '1' : '0';

			case FFE_Config_Resolver::SETTING_EMAIL_CONFIRMATION:
				return ! empty( $this->get_field_property( $field, 'emailConfirmEnabled' ) ) ? '1' : '0';

				case FFE_Config_Resolver::SETTING_NAME_FIELDS:
					return $this->get_name_field_inputs( $field );

				case FFE_Config_Resolver::SETTING_TIME_FORMAT:
					return $this->get_time_format( $field );

				case FFE_Config_Resolver::SETTING_TIME_SUB_LABELS:
					return $this->get_time_field_inputs( $field );

				case FFE_Config_Resolver::SETTING_ADDRESS_FIELDS:
					return $this->get_address_field_inputs( $field );

			case FFE_Config_Resolver::SETTING_DESCRIPTION:
				return (string) $this->get_field_property( $field, 'description' );

			case FFE_Config_Resolver::SETTING_PLACEHOLDER:
				return (string) $this->get_field_property( $field, 'placeholder' );

			case FFE_Config_Resolver::SETTING_DEFAULT_VALUE:
				return $this->is_time_field( $field ) ? $this->get_time_field_default_values( $field ) : ( $this->is_address_field( $field ) ? $this->get_address_field_default_values( $field ) : (string) $this->get_field_property( $field, 'defaultValue' ) );

			case FFE_Config_Resolver::SETTING_DEFAULT_COUNTRY:
				return $this->is_address_field( $field ) ? (string) $this->get_field_property( $field, 'defaultCountry' ) : '';

			case FFE_Config_Resolver::SETTING_ADMIN_LABEL:
				return (string) $this->get_field_property( $field, 'adminLabel' );

			default:
				return '';
		}
	}

	private function get_form_setting_value( $form, $setting_key ) {
		switch ( $setting_key ) {
			case FFE_Config_Resolver::SETTING_TITLE:
				return (string) rgar( $form, 'title' );

			case FFE_Config_Resolver::SETTING_DESCRIPTION:
				return (string) rgar( $form, 'description' );

			default:
				return '';
		}
	}

	private function set_form_setting_value( &$form, $setting_key, $value ) {
		if ( ! is_array( $form ) ) {
			return;
		}

		switch ( $setting_key ) {
			case FFE_Config_Resolver::SETTING_TITLE:
				$form['title'] = $value;
				break;

			case FFE_Config_Resolver::SETTING_DESCRIPTION:
				$form['description'] = $value;
				break;
		}
	}

	private function set_field_setting_value( &$field, $setting_key, $value ) {
		if ( FFE_Config_Resolver::SETTING_REQUIRED === $setting_key ) {
			$this->set_field_property( $field, 'isRequired', '1' === (string) $value );
			return;
		}

		if ( FFE_Config_Resolver::SETTING_EMAIL_CONFIRMATION === $setting_key ) {
			$this->set_email_confirmation_enabled( $field, '1' === (string) $value );
			return;
		}

		if ( FFE_Config_Resolver::SETTING_CHOICES === $setting_key ) {
			$this->set_field_choices( $field, $value );
			return;
		}

		if ( FFE_Config_Resolver::SETTING_NAME_FIELDS === $setting_key ) {
			$this->set_name_field_inputs( $field, $value );
			return;
		}

		if ( FFE_Config_Resolver::SETTING_TIME_SUB_LABELS === $setting_key ) {
			$this->set_time_field_inputs( $field, $value );
			return;
		}

		if ( FFE_Config_Resolver::SETTING_DEFAULT_VALUE === $setting_key && $this->is_time_field( $field ) && is_array( $value ) ) {
			$this->set_time_field_default_values( $field, $value );
			return;
		}

		if ( FFE_Config_Resolver::SETTING_DEFAULT_VALUE === $setting_key && $this->is_address_field( $field ) && is_array( $value ) ) {
			$this->set_address_field_default_values( $field, $value );
			return;
		}

		if ( FFE_Config_Resolver::SETTING_ADDRESS_FIELDS === $setting_key ) {
			$this->set_address_field_inputs( $field, $value );
			return;
		}

		$property_map = array(
			FFE_Config_Resolver::SETTING_LABEL         => 'label',
			FFE_Config_Resolver::SETTING_CONSENT_CHECKBOX_LABEL => 'checkboxLabel',
			FFE_Config_Resolver::SETTING_TIME_FORMAT   => 'timeFormat',
			FFE_Config_Resolver::SETTING_DESCRIPTION   => 'description',
			FFE_Config_Resolver::SETTING_PLACEHOLDER   => 'placeholder',
			FFE_Config_Resolver::SETTING_DEFAULT_VALUE => 'defaultValue',
			FFE_Config_Resolver::SETTING_DEFAULT_COUNTRY => 'defaultCountry',
			FFE_Config_Resolver::SETTING_ADMIN_LABEL   => 'adminLabel',
		);

		if ( ! isset( $property_map[ $setting_key ] ) ) {
			return;
		}

		$this->set_field_property( $field, $property_map[ $setting_key ], $value );

		if ( in_array( $setting_key, array( FFE_Config_Resolver::SETTING_PLACEHOLDER, FFE_Config_Resolver::SETTING_DEFAULT_VALUE ), true ) ) {
			$this->sync_email_primary_input_property( $field, $property_map[ $setting_key ], $value );
		}
	}

	private function set_field_property( &$field, $property, $value ) {

		if ( is_object( $field ) ) {
			$field->{ $property } = $value;
			return;
		}

		if ( is_array( $field ) ) {
			$field[ $property ] = $value;
		}
	}

	private function build_field_item_payload( $field, $allowed_settings ) {
		return array(
			'id'                => absint( $this->get_field_property( $field, 'id' ) ),
			'type'              => (string) $this->get_field_property( $field, 'type' ),
			'inputType'         => $this->get_field_input_type( $field ),
			'dateConfig'        => $this->get_date_field_config( $field ),
			'addressType'       => $this->get_address_type_key( $field ),
			'addressConfig'     => $this->get_address_field_config( $field ),
			'choiceLimit'       => (string) $this->get_field_property( $field, 'choiceLimit' ),
			'nameFormat'        => (string) $this->get_field_property( $field, 'nameFormat' ),
			'namePrefixChoices' => $this->get_name_prefix_choices( $field ),
			'size'              => (string) $this->get_field_property( $field, 'size' ),
			'subLabelPlacement' => (string) $this->get_field_property( $field, 'subLabelPlacement' ),
			'settings'          => array_values( $allowed_settings ),
			'values'            => array(
				'label'              => (string) $this->get_field_property( $field, 'label' ),
				'consent_checkbox_label' => (string) $this->get_field_property( $field, 'checkboxLabel' ),
				'choices'            => $this->get_field_choices( $field ),
				'required'           => ! empty( $this->get_field_property( $field, 'isRequired' ) ),
				'email_confirmation' => ! empty( $this->get_field_property( $field, 'emailConfirmEnabled' ) ),
				'name_fields'        => $this->get_name_field_inputs( $field ),
				'time_format'        => $this->get_time_format( $field ),
				'time_sub_labels'    => $this->get_time_field_inputs( $field ),
				'address_fields'     => $this->get_address_field_inputs( $field ),
				'description'        => (string) $this->get_field_property( $field, 'description' ),
				'placeholder'        => (string) $this->get_field_property( $field, 'placeholder' ),
				'default_value'      => $this->is_time_field( $field ) ? $this->get_time_field_default_values( $field ) : ( $this->is_address_field( $field ) ? $this->get_address_field_default_values( $field ) : (string) $this->get_field_property( $field, 'defaultValue' ) ),
				'default_country'    => $this->is_address_field( $field ) ? (string) $this->get_field_property( $field, 'defaultCountry' ) : '',
				'admin_label'        => (string) $this->get_field_property( $field, 'adminLabel' ),
			),
		);
	}

	private function get_name_field_inputs( $field ) {
		$inputs = $this->normalize_name_field_inputs( $field );
		$payload = array();

		foreach ( $inputs as $input ) {
			$payload[] = array(
				'id'           => (string) rgar( $input, 'id' ),
				'defaultLabel' => (string) rgar( $input, 'defaultLabel', rgar( $input, 'label' ) ),
				'customLabel'  => (string) rgar( $input, 'customLabel' ),
				'isHidden'     => ! empty( rgar( $input, 'isHidden' ) ),
			);
		}

		return $payload;
	}

	private function get_address_field_inputs( $field ) {
		$inputs = $this->normalize_address_field_inputs( $field );
		$payload = array();

		foreach ( $inputs as $input ) {
			$payload[] = array(
				'id'                    => (string) rgar( $input, 'id' ),
				'defaultLabel'          => (string) rgar( $input, 'defaultLabel', rgar( $input, 'label' ) ),
				'customLabel'           => (string) rgar( $input, 'customLabel' ),
				'isHidden'              => ! empty( rgar( $input, 'isHidden' ) ),
				'isLocked'              => ! empty( rgar( $input, 'isLocked' ) ),
				'placeholder'           => (string) rgar( $input, 'placeholder' ),
				'autocompleteAttribute' => (string) rgar( $input, 'autocompleteAttribute' ),
			);
		}

		return $payload;
	}

	private function get_address_field_default_values( $field ) {
		$inputs  = $this->normalize_address_field_inputs( $field );
		$payload = array();

		foreach ( $inputs as $input ) {
			$input_id = (string) rgar( $input, 'id' );

			if ( ! preg_match( '/\.(?:1|2|3|4|5|6)$/', $input_id ) || ! empty( rgar( $input, 'isHidden' ) ) ) {
				continue;
			}

			$payload[] = array(
				'id'           => $input_id,
				'defaultLabel' => (string) rgar( $input, 'defaultLabel', rgar( $input, 'label' ) ),
				'defaultValue' => (string) rgar( $input, 'defaultValue' ),
			);
		}

		return $payload;
	}

	private function get_time_field_inputs( $field ) {
		$inputs  = $this->normalize_time_field_inputs( $field );
		$payload = array();

		foreach ( $inputs as $input ) {
			if ( ! preg_match( '/\.(?:1|2)$/', (string) rgar( $input, 'id' ) ) ) {
				continue;
			}

			$payload[] = array(
				'id'           => (string) rgar( $input, 'id' ),
				'defaultLabel' => (string) rgar( $input, 'defaultLabel', rgar( $input, 'label' ) ),
				'customLabel'  => (string) rgar( $input, 'customLabel' ),
			);
		}

		return $payload;
	}

	private function get_time_field_default_values( $field ) {
		$inputs          = $this->normalize_time_field_inputs( $field );
		$legacy_defaults = $this->get_legacy_time_default_values( $field );
		$payload         = array();

		foreach ( $inputs as $input ) {
			$input_id = (string) rgar( $input, 'id' );

			if ( ! preg_match( '/\.(?:1|2|3)$/', $input_id ) ) {
				continue;
			}

			$payload[] = array(
				'id'           => $input_id,
				'defaultLabel' => (string) rgar( $input, 'defaultLabel', rgar( $input, 'label' ) ),
				'defaultValue' => '' !== (string) rgar( $input, 'defaultValue' ) ? (string) rgar( $input, 'defaultValue' ) : ( isset( $legacy_defaults[ $input_id ] ) ? $legacy_defaults[ $input_id ] : '' ),
			);
		}

		return $payload;
	}

	private function get_time_format( $field ) {
		return '24' === (string) $this->get_field_property( $field, 'timeFormat' ) ? '24' : '12';
	}

	private function get_date_field_config( $field ) {
		if ( ! $this->is_date_field( $field ) ) {
			return array();
		}

		$date_type          = (string) $this->get_field_property( $field, 'dateType' );
		$date_format        = (string) $this->get_field_property( $field, 'dateFormat' );
		$calendar_icon_type = (string) $this->get_field_property( $field, 'calendarIconType' );

		return array(
			'dateType'         => '' !== $date_type ? $date_type : 'datepicker',
			'dateFormat'       => '' !== $date_format ? $date_format : 'mdy',
			'calendarIconType' => '' !== $calendar_icon_type ? $calendar_icon_type : 'none',
		);
	}

	private function get_address_field_config( $field ) {
		$address_type_config = $this->get_address_type_config( $field );
		$state_options = rgar( $address_type_config, 'states', array() );

		return array(
			'type'           => $this->get_address_type_key( $field ),
			'displayFormat'  => $this->get_address_display_format( $field ),
			'stateLabel'     => (string) rgar( $address_type_config, 'state_label', esc_html__( 'State / Province', 'gravityforms' ) ),
			'zipLabel'       => (string) rgar( $address_type_config, 'zip_label', esc_html__( 'ZIP / Postal Code', 'gravityforms' ) ),
			'fixedCountry'   => (string) rgar( $address_type_config, 'country' ),
			'stateOptions'   => is_array( $state_options ) ? array_values( $state_options ) : array(),
			'countryOptions' => $this->get_address_country_options(),
		);
	}

	private function get_name_prefix_choices( $field ) {
		$inputs = $this->normalize_name_field_inputs( $field );

		foreach ( $inputs as $input ) {
			if ( preg_match( '/\.2$/', (string) rgar( $input, 'id' ) ) && isset( $input['choices'] ) && is_array( $input['choices'] ) ) {
				return array_values( $input['choices'] );
			}
		}

		return $this->get_default_name_prefix_choices();
	}

	private function set_name_field_inputs( &$field, $name_fields ) {
		$normalized_inputs = $this->normalize_name_field_inputs( $field );
		$requested_inputs = is_array( $name_fields ) ? array_values( $name_fields ) : array();
		$requested_inputs_by_id = array();

		foreach ( $requested_inputs as $requested_input ) {
			$requested_id = (string) rgar( $requested_input, 'id' );

			if ( '' === $requested_id ) {
				continue;
			}

			$requested_inputs_by_id[ $requested_id ] = $requested_input;
		}

		foreach ( $normalized_inputs as &$input ) {
			$input_id = (string) rgar( $input, 'id' );

			if ( ! isset( $requested_inputs_by_id[ $input_id ] ) ) {
				continue;
			}

			$requested_input = $requested_inputs_by_id[ $input_id ];
			$input['customLabel'] = (string) rgar( $requested_input, 'customLabel' );
			$input['isHidden'] = ! empty( rgar( $requested_input, 'isHidden' ) );
		}
		unset( $input );

		$this->set_field_property( $field, 'nameFormat', 'advanced' );
		$this->set_field_property( $field, 'inputs', $normalized_inputs );
	}

	private function set_time_field_inputs( &$field, $time_fields ) {
		$normalized_inputs      = $this->normalize_time_field_inputs( $field );
		$requested_inputs       = is_array( $time_fields ) ? array_values( $time_fields ) : array();
		$requested_inputs_by_id = array();

		foreach ( $requested_inputs as $requested_input ) {
			$requested_id = (string) rgar( $requested_input, 'id' );

			if ( '' === $requested_id ) {
				continue;
			}

			$requested_inputs_by_id[ $requested_id ] = $requested_input;
		}

		foreach ( $normalized_inputs as &$input ) {
			$input_id = (string) rgar( $input, 'id' );

			if ( ! isset( $requested_inputs_by_id[ $input_id ] ) ) {
				continue;
			}

			$requested_input      = $requested_inputs_by_id[ $input_id ];
			$input['customLabel'] = (string) rgar( $requested_input, 'customLabel' );
		}
		unset( $input );

		$this->set_field_property( $field, 'inputs', $normalized_inputs );
	}

	private function set_time_field_default_values( &$field, $default_values ) {
		$normalized_inputs      = $this->normalize_time_field_inputs( $field );
		$requested_inputs       = is_array( $default_values ) ? array_values( $default_values ) : array();
		$requested_inputs_by_id = array();

		foreach ( $requested_inputs as $requested_input ) {
			$requested_id = (string) rgar( $requested_input, 'id' );

			if ( '' === $requested_id ) {
				continue;
			}

			$requested_inputs_by_id[ $requested_id ] = $requested_input;
		}

		foreach ( $normalized_inputs as &$input ) {
			$input_id = (string) rgar( $input, 'id' );

			if ( ! isset( $requested_inputs_by_id[ $input_id ] ) ) {
				continue;
			}

			$requested_input       = $requested_inputs_by_id[ $input_id ];
			$input['defaultValue'] = (string) rgar( $requested_input, 'defaultValue' );

			if ( preg_match( '/\.3$/', $input_id ) ) {
				$input['defaultValue'] = strtolower( $input['defaultValue'] );
			}
		}
		unset( $input );

		$this->set_field_property( $field, 'defaultValue', '' );
		$this->set_field_property( $field, 'inputs', $normalized_inputs );
	}

	private function set_address_field_default_values( &$field, $default_values ) {
		$normalized_inputs      = $this->normalize_address_field_inputs( $field );
		$requested_inputs       = is_array( $default_values ) ? array_values( $default_values ) : array();
		$requested_inputs_by_id = array();

		foreach ( $requested_inputs as $requested_input ) {
			$requested_id = (string) rgar( $requested_input, 'id' );

			if ( '' === $requested_id ) {
				continue;
			}

			$requested_inputs_by_id[ $requested_id ] = $requested_input;
		}

		foreach ( $normalized_inputs as &$input ) {
			$input_id = (string) rgar( $input, 'id' );

			if ( ! isset( $requested_inputs_by_id[ $input_id ] ) ) {
				continue;
			}

			$requested_input       = $requested_inputs_by_id[ $input_id ];
			$input['defaultValue'] = (string) rgar( $requested_input, 'defaultValue' );
		}
		unset( $input );

		$this->set_field_property( $field, 'defaultValue', '' );
		$this->set_field_property( $field, 'inputs', $normalized_inputs );
	}

	private function set_address_field_inputs( &$field, $address_fields ) {
		$normalized_inputs = $this->normalize_address_field_inputs( $field );
		$requested_inputs = is_array( $address_fields ) ? array_values( $address_fields ) : array();
		$requested_inputs_by_id = array();
		$field_id = (string) absint( $this->get_field_property( $field, 'id' ) );

		foreach ( $requested_inputs as $requested_input ) {
			$requested_id = (string) rgar( $requested_input, 'id' );

			if ( '' === $requested_id ) {
				continue;
			}

			$requested_inputs_by_id[ $requested_id ] = $requested_input;
		}

		foreach ( $normalized_inputs as &$input ) {
			$input_id = (string) rgar( $input, 'id' );

			if ( isset( $requested_inputs_by_id[ $input_id ] ) ) {
				$requested_input = $requested_inputs_by_id[ $input_id ];
				$input['customLabel'] = (string) rgar( $requested_input, 'customLabel' );

				if ( empty( $input['isLocked'] ) ) {
					$input['isHidden'] = ! empty( rgar( $requested_input, 'isHidden' ) );
				}
			}

			if ( ! empty( $input['isLocked'] ) ) {
				$input['isHidden'] = true;
			}
		}
		unset( $input );

		$address2_input = $this->get_field_input_by_id( $normalized_inputs, $field_id . '.2' );
		$state_input = $this->get_field_input_by_id( $normalized_inputs, $field_id . '.4' );
		$country_input = $this->get_field_input_by_id( $normalized_inputs, $field_id . '.6' );

		$this->set_field_property( $field, 'hideAddress2', ! empty( rgar( $address2_input, 'isHidden' ) ) );
		$this->set_field_property( $field, 'hideState', ! empty( rgar( $state_input, 'isHidden' ) ) );
		$this->set_field_property( $field, 'hideCountry', ! empty( rgar( $country_input, 'isHidden' ) ) );
		$this->set_field_property( $field, 'inputs', $normalized_inputs );
	}

	private function set_email_confirmation_enabled( &$field, $is_enabled ) {
		$field_id = (string) absint( $this->get_field_property( $field, 'id' ) );
		$existing_inputs = $this->get_field_property( $field, 'inputs' );
		$existing_inputs = is_array( $existing_inputs ) ? array_values( $existing_inputs ) : array();

		$this->set_field_property( $field, 'emailConfirmEnabled', $is_enabled );

		if ( ! $is_enabled ) {
			$this->set_field_property( $field, 'inputs', null );
			return;
		}

		$enter_input = $this->get_field_input_by_id( $existing_inputs, $field_id );
		$confirm_input = $this->get_field_input_by_id( $existing_inputs, $field_id . '.2' );

		if ( empty( $enter_input ) ) {
			$enter_input = array(
				'id'    => $field_id,
				'label' => esc_html__( 'Enter Email', 'gravityforms' ),
			);
		}

		if ( empty( $confirm_input ) ) {
			$confirm_input = array(
				'id'    => $field_id . '.2',
				'label' => esc_html__( 'Confirm Email', 'gravityforms' ),
			);
		}

		if ( '' === (string) rgar( $enter_input, 'placeholder' ) && '' !== (string) $this->get_field_property( $field, 'placeholder' ) ) {
			$enter_input['placeholder'] = (string) $this->get_field_property( $field, 'placeholder' );
		}

		if ( '' === (string) rgar( $enter_input, 'defaultValue' ) && '' !== (string) $this->get_field_property( $field, 'defaultValue' ) ) {
			$enter_input['defaultValue'] = (string) $this->get_field_property( $field, 'defaultValue' );
		}

		$this->set_field_property( $field, 'inputs', array( $enter_input, $confirm_input ) );
	}

	private function sync_email_primary_input_property( &$field, $property, $value ) {
		$inputs = $this->get_field_property( $field, 'inputs' );
		$field_id = (string) absint( $this->get_field_property( $field, 'id' ) );

		if ( 'email' !== (string) $this->get_field_property( $field, 'type' ) || empty( $this->get_field_property( $field, 'emailConfirmEnabled' ) ) || ! is_array( $inputs ) ) {
			return;
		}

		foreach ( $inputs as &$input ) {
			if ( (string) rgar( $input, 'id' ) !== $field_id ) {
				continue;
			}

			$input[ $property ] = $value;
			break;
		}
		unset( $input );

		$this->set_field_property( $field, 'inputs', $inputs );
	}

	private function get_field_input_by_id( $inputs, $input_id ) {
		if ( ! is_array( $inputs ) ) {
			return array();
		}

		foreach ( $inputs as $input ) {
			if ( (string) rgar( $input, 'id' ) === (string) $input_id ) {
				return is_array( $input ) ? $input : array();
			}
		}

		return array();
	}

	private function normalize_time_field_inputs( $field ) {
		$field_id   = absint( $this->get_field_property( $field, 'id' ) );
		$defaults   = $this->get_default_time_inputs( $field_id );
		$inputs     = $this->get_field_property( $field, 'inputs' );
		$normalized = array();

		foreach ( $defaults as $default_input ) {
			$input        = $this->get_field_input_by_id( $inputs, rgar( $default_input, 'id' ) );
			$merged_input = array_merge( $default_input, $input );

			if ( '' === (string) rgar( $merged_input, 'label' ) ) {
				$merged_input['label'] = (string) rgar( $default_input, 'label' );
			}

			$normalized[] = $merged_input;
		}

		return $normalized;
	}

	private function get_legacy_time_default_values( $field ) {
		$default_value = trim( (string) $this->get_field_property( $field, 'defaultValue' ) );
		$field_id      = absint( $this->get_field_property( $field, 'id' ) );
		$matches       = array();

		if ( '' === $default_value || ! preg_match( '/^(\d*):(\d*)\s*(.*)$/', $default_value, $matches ) ) {
			return array();
		}

		$defaults = array();

		if ( '' !== trim( (string) rgar( $matches, 1, '' ) ) ) {
			$defaults[ $field_id . '.1' ] = trim( (string) rgar( $matches, 1, '' ) );
		}

		if ( '' !== trim( (string) rgar( $matches, 2, '' ) ) ) {
			$defaults[ $field_id . '.2' ] = trim( (string) rgar( $matches, 2, '' ) );
		}

		if ( in_array( strtolower( trim( (string) rgar( $matches, 3, '' ) ) ), array( 'am', 'pm' ), true ) ) {
			$defaults[ $field_id . '.3' ] = strtolower( trim( (string) rgar( $matches, 3, '' ) ) );
		}

		return $defaults;
	}

	private function normalize_name_field_inputs( $field ) {
		$field_id = absint( $this->get_field_property( $field, 'id' ) );
		$defaults = $this->get_default_name_inputs( $field_id );
		$inputs = $this->get_field_property( $field, 'inputs' );
		$normalized = array();

		foreach ( $defaults as $default_input ) {
			$input = $this->get_field_input_by_id( $inputs, rgar( $default_input, 'id' ) );
			$merged_input = array_merge( $default_input, $input );

			if ( '' === (string) rgar( $merged_input, 'label' ) ) {
				$merged_input['label'] = (string) rgar( $default_input, 'label' );
			}

			$normalized[] = $merged_input;
		}

		return $normalized;
	}

	private function normalize_address_field_inputs( $field ) {
		$defaults = $this->get_default_address_inputs( $field );
		$inputs = $this->get_field_property( $field, 'inputs' );
		$normalized = array();

		foreach ( $defaults as $default_input ) {
			$input = $this->get_field_input_by_id( $inputs, rgar( $default_input, 'id' ) );
			$merged_input = array_merge( $default_input, $input );

			if ( '' === (string) rgar( $merged_input, 'label' ) ) {
				$merged_input['label'] = (string) rgar( $default_input, 'label' );
			}

			if ( ! empty( $default_input['isLocked'] ) ) {
				$merged_input['isLocked'] = true;
				$merged_input['isHidden'] = true;
			}

			if ( preg_match( '/\.2$/', (string) rgar( $merged_input, 'id' ) ) && ! empty( $this->get_field_property( $field, 'hideAddress2' ) ) ) {
				$merged_input['isHidden'] = true;
			}

			if ( preg_match( '/\.4$/', (string) rgar( $merged_input, 'id' ) ) && ! empty( $this->get_field_property( $field, 'hideState' ) ) ) {
				$merged_input['isHidden'] = true;
			}

			if ( preg_match( '/\.6$/', (string) rgar( $merged_input, 'id' ) ) && ! empty( $this->get_field_property( $field, 'hideCountry' ) ) ) {
				$merged_input['isHidden'] = true;
			}

			$normalized[] = $merged_input;
		}

		return $normalized;
	}

	private function get_default_name_inputs( $field_id ) {
		$field_id = absint( $field_id );

		return array(
			array(
				'id'           => $field_id . '.2',
				'label'        => esc_html__( 'Prefix', 'gravityforms' ),
				'defaultLabel' => esc_html__( 'Prefix', 'gravityforms' ),
				'isHidden'     => true,
				'choices'      => $this->get_default_name_prefix_choices(),
			),
			array(
				'id'           => $field_id . '.3',
				'label'        => esc_html__( 'First', 'gravityforms' ),
				'defaultLabel' => esc_html__( 'First', 'gravityforms' ),
				'isHidden'     => false,
			),
			array(
				'id'           => $field_id . '.4',
				'label'        => esc_html__( 'Middle', 'gravityforms' ),
				'defaultLabel' => esc_html__( 'Middle', 'gravityforms' ),
				'isHidden'     => true,
			),
			array(
				'id'           => $field_id . '.6',
				'label'        => esc_html__( 'Last', 'gravityforms' ),
				'defaultLabel' => esc_html__( 'Last', 'gravityforms' ),
				'isHidden'     => false,
			),
			array(
				'id'           => $field_id . '.8',
				'label'        => esc_html__( 'Suffix', 'gravityforms' ),
				'defaultLabel' => esc_html__( 'Suffix', 'gravityforms' ),
				'isHidden'     => true,
			),
		);
	}

	private function get_default_time_inputs( $field_id ) {
		$field_id = absint( $field_id );

		return array(
			array(
				'id'           => $field_id . '.1',
				'label'        => esc_html__( 'Hour', 'gravityforms' ),
				'defaultLabel' => esc_html__( 'Hour', 'gravityforms' ),
			),
			array(
				'id'           => $field_id . '.2',
				'label'        => esc_html__( 'Minute', 'gravityforms' ),
				'defaultLabel' => esc_html__( 'Minute', 'gravityforms' ),
			),
			array(
				'id'           => $field_id . '.3',
				'label'        => esc_html__( 'AM/PM', 'gravityforms' ),
				'defaultLabel' => esc_html__( 'AM/PM', 'gravityforms' ),
			),
		);
	}

	private function get_default_name_prefix_choices() {
		$prefixes_string = __( 'Mr., Mrs., Miss, Ms., Mx., Dr., Prof., Rev.', 'gravityforms' );
		$prefixes_array  = explode( ', ', $prefixes_string );
		$prefixes        = array_unique( array_filter( $prefixes_array ) );
		$choices         = array();

		sort( $prefixes );

		foreach ( $prefixes as $prefix ) {
			$prefix = wp_strip_all_tags( $prefix );

			$choices[] = array(
				'text'  => $prefix,
				'value' => $prefix,
			);
		}

		return $choices;
	}

	private function get_default_address_inputs( $field ) {
		$field_id = absint( $this->get_field_property( $field, 'id' ) );
		$address_config = $this->get_address_field_config( $field );

		return array(
			array(
				'id'                    => $field_id . '.1',
				'label'                 => esc_html__( 'Street Address', 'gravityforms' ),
				'defaultLabel'          => esc_html__( 'Street Address', 'gravityforms' ),
				'isHidden'              => false,
				'autocompleteAttribute' => 'address-line1',
			),
			array(
				'id'                    => $field_id . '.2',
				'label'                 => esc_html__( 'Address Line 2', 'gravityforms' ),
				'defaultLabel'          => esc_html__( 'Address Line 2', 'gravityforms' ),
				'isHidden'              => ! empty( $this->get_field_property( $field, 'hideAddress2' ) ),
				'autocompleteAttribute' => 'address-line2',
			),
			array(
				'id'                    => $field_id . '.3',
				'label'                 => esc_html__( 'City', 'gravityforms' ),
				'defaultLabel'          => esc_html__( 'City', 'gravityforms' ),
				'isHidden'              => false,
				'autocompleteAttribute' => 'address-level2',
			),
			array(
				'id'                    => $field_id . '.4',
				'label'                 => (string) rgar( $address_config, 'stateLabel', esc_html__( 'State / Province', 'gravityforms' ) ),
				'defaultLabel'          => (string) rgar( $address_config, 'stateLabel', esc_html__( 'State / Province', 'gravityforms' ) ),
				'isHidden'              => ! empty( $this->get_field_property( $field, 'hideState' ) ),
				'autocompleteAttribute' => 'address-level1',
			),
			array(
				'id'                    => $field_id . '.5',
				'label'                 => (string) rgar( $address_config, 'zipLabel', esc_html__( 'ZIP / Postal Code', 'gravityforms' ) ),
				'defaultLabel'          => (string) rgar( $address_config, 'zipLabel', esc_html__( 'ZIP / Postal Code', 'gravityforms' ) ),
				'isHidden'              => false,
				'autocompleteAttribute' => 'postal-code',
			),
			array(
				'id'                    => $field_id . '.6',
				'label'                 => esc_html__( 'Country', 'gravityforms' ),
				'defaultLabel'          => esc_html__( 'Country', 'gravityforms' ),
				'isHidden'              => ! empty( $this->get_field_property( $field, 'hideCountry' ) ) || '' !== (string) rgar( $address_config, 'fixedCountry' ),
				'isLocked'              => '' !== (string) rgar( $address_config, 'fixedCountry' ),
				'autocompleteAttribute' => 'country-name',
			),
		);
	}

	private function get_address_type_config( $field ) {
		$address_field = $this->get_address_field_helper();
		$form_id = $this->get_field_form_id( $field );
		$address_type_key = $this->get_address_type_key( $field );
		$address_types = $address_field && method_exists( $address_field, 'get_address_types' ) ? $address_field->get_address_types( $form_id ) : array();

		return isset( $address_types[ $address_type_key ] ) && is_array( $address_types[ $address_type_key ] ) ? $address_types[ $address_type_key ] : array();
	}

	private function get_address_type_key( $field ) {
		$address_type = (string) $this->get_field_property( $field, 'addressType' );

		if ( '' !== $address_type ) {
			return $address_type;
		}

		$address_field = $this->get_address_field_helper();
		$form_id = $this->get_field_form_id( $field );

		if ( $address_field && method_exists( $address_field, 'get_default_address_type' ) ) {
			return (string) $address_field->get_default_address_type( $form_id );
		}

		return 'international';
	}

	private function get_address_field_helper() {
		return class_exists( 'GF_Fields' ) ? GF_Fields::get( 'address' ) : null;
	}

	private function get_address_country_options() {
		$address_field = $this->get_address_field_helper();
		$countries = $address_field && method_exists( $address_field, 'get_countries' ) ? $address_field->get_countries() : array();
		$options = array();

		foreach ( (array) $countries as $country ) {
			$options[] = array(
				'value' => (string) $country,
				'text'  => (string) $country,
			);
		}

		return $options;
	}

	private function get_field_form_id( $field ) {
		return absint( $this->get_field_property( $field, 'formId' ) );
	}

	private function get_address_display_format( $field ) {
		return (string) apply_filters( 'gform_address_display_format', 'default', $field );
	}

	private function get_field_input_type( $field ) {
		$input_type = $this->get_field_property( $field, 'inputType' );

		return $input_type ? (string) $input_type : (string) $this->get_field_property( $field, 'type' );
	}

	private function is_time_field( $field ) {
		return 'time' === $this->get_field_input_type( $field );
	}

	private function is_date_field( $field ) {
		return 'date' === $this->get_field_input_type( $field );
	}

	private function is_address_field( $field ) {
		return 'address' === $this->get_field_input_type( $field );
	}

	private function get_field_choices( $field ) {
		$choices = $this->get_field_property( $field, 'choices' );
		$payload = array();

		if ( ! is_array( $choices ) ) {
			return $payload;
		}

		foreach ( array_values( $choices ) as $index => $choice ) {
			$payload[] = array(
				'text'        => (string) rgar( $choice, 'text' ),
				'value'       => (string) rgar( $choice, 'value' ),
				'isSelected'  => ! empty( rgar( $choice, 'isSelected' ) ),
				'key'         => (string) rgar( $choice, 'key' ),
				'inputId'     => $this->get_choice_input_id( $field, $choice, $index ),
				'sourceIndex' => $index,
			);
		}

		return $payload;
	}

	private function get_choice_input_id( $field, $choice, $index ) {
		$field_type = (string) $this->get_field_property( $field, 'type' );
		$inputs = $this->get_field_property( $field, 'inputs' );

		if ( ! is_array( $inputs ) ) {
			return '';
		}

		if ( 'checkbox' === $field_type ) {
			return isset( $inputs[ $index ]['id'] ) ? (string) $inputs[ $index ]['id'] : '';
		}

		if ( 'multi_choice' === $field_type ) {
			$choice_key = (string) rgar( $choice, 'key' );

			foreach ( $inputs as $input ) {
				if ( (string) rgar( $input, 'key' ) === $choice_key ) {
					return (string) rgar( $input, 'id' );
				}
			}
		}

		return '';
	}

	private function set_field_choices( &$field, $choices ) {
		$choices = is_array( $choices ) ? array_values( $choices ) : array();
		$existing_choices = $this->get_field_property( $field, 'choices' );
		$existing_choices = is_array( $existing_choices ) ? array_values( $existing_choices ) : array();
		$existing_inputs = $this->get_field_property( $field, 'inputs' );
		$existing_inputs = is_array( $existing_inputs ) ? array_values( $existing_inputs ) : array();
		$rebuilt_choices = array();
		$rebuilt_inputs = array();
		$field_type = (string) $this->get_field_property( $field, 'type' );
		$enable_choice_value = ! empty( $this->get_field_property( $field, 'enableChoiceValue' ) );
		$existing_inputs_by_key = array();

		foreach ( $existing_inputs as $input ) {
			$key = (string) rgar( $input, 'key' );

			if ( '' !== $key ) {
				$existing_inputs_by_key[ $key ] = $input;
			}
		}

		foreach ( $choices as $index => $choice ) {
			$source_index = isset( $choice['sourceIndex'] ) && null !== $choice['sourceIndex'] ? absint( $choice['sourceIndex'] ) : null;
			$existing_choice = null !== $source_index && isset( $existing_choices[ $source_index ] ) ? $existing_choices[ $source_index ] : array();
			$text = isset( $choice['text'] ) ? (string) $choice['text'] : '';
			$value = isset( $choice['value'] ) ? (string) $choice['value'] : '';
			$is_selected = ! empty( $choice['isSelected'] );
			$stored_choice = is_array( $existing_choice ) ? $existing_choice : array();

			$stored_choice['text'] = $text;
			$stored_choice['value'] = $enable_choice_value ? ( '' !== $value ? $value : $text ) : $text;
			$stored_choice['isSelected'] = $is_selected;

			if ( 'multi_choice' === $field_type ) {
				$key = isset( $choice['key'] ) ? (string) $choice['key'] : '';

				if ( '' === $key ) {
					$key = (string) rgar( $stored_choice, 'key' );
				}

				if ( '' === $key ) {
					$key = $this->generate_choice_key();
				}

				$stored_choice['key'] = $key;
				$input = isset( $existing_inputs_by_key[ $key ] ) ? $existing_inputs_by_key[ $key ] : array(
					'id' => $this->get_next_choice_input_id( $field, $rebuilt_inputs ),
				);
				$input['label'] = $text;
				$input['key'] = $key;

				if ( empty( $input['id'] ) ) {
					$input['id'] = $this->get_next_choice_input_id( $field, $rebuilt_inputs );
				}

				$rebuilt_inputs[] = $input;
			}

			$rebuilt_choices[] = $stored_choice;
		}

		if ( 'checkbox' === $field_type ) {
			$rebuilt_inputs = $this->build_checkbox_inputs( $field, $rebuilt_choices );
		}

		$this->set_field_property( $field, 'choices', $rebuilt_choices );

		if ( 'checkbox' === $field_type || 'multi_choice' === $field_type ) {
			$this->set_field_property( $field, 'inputs', $rebuilt_inputs );
		}
	}

	private function build_checkbox_inputs( $field, $choices ) {
		$inputs = array();
		$skip = 0;
		$field_id = absint( $this->get_field_property( $field, 'id' ) );

		foreach ( array_values( $choices ) as $index => $choice ) {
			if ( 0 === ( $index + 1 + $skip ) % 10 ) {
				$skip++;
			}

			$inputs[] = array(
				'id'    => $field_id . '.' . ( $index + 1 + $skip ),
				'label' => (string) rgar( $choice, 'text' ),
			);
		}

		return $inputs;
	}

	private function get_next_choice_input_id( $field, $inputs ) {
		$field_id = absint( $this->get_field_property( $field, 'id' ) );
		$last_index = 0;

		foreach ( $inputs as $input ) {
			$input_id = (string) rgar( $input, 'id' );

			if ( preg_match( '/\.(\d+)$/', $input_id, $matches ) ) {
				$last_index = max( $last_index, absint( $matches[1] ) );
			}
		}

		$next_index = $last_index > 0 ? $last_index + 1 : 1;

		if ( 0 === $next_index % 10 ) {
			$next_index++;
		}

		return $field_id . '.' . $next_index;
	}

	private function generate_choice_key() {
		return strtolower( wp_generate_password( 9, false, false ) . base_convert( time(), 10, 36 ) );
	}

	private function get_field_property( $field, $property ) {
		if ( is_object( $field ) && isset( $field->{$property} ) ) {
			return $field->{$property};
		}

		if ( is_array( $field ) ) {
			return rgar( $field, $property );
		}

		return null;
	}
}