<?php

defined( 'ABSPATH' ) || exit;

class FFE_Config_Resolver {
	const MODE_INHERIT = 'inherit';
	const MODE_ENABLED = 'enabled';
	const MODE_DISABLED = 'disabled';

	const SETTING_TITLE = 'title';
	const SETTING_LABEL = 'label';
	const SETTING_CONSENT_CHECKBOX_LABEL = 'consent_checkbox_label';
	const SETTING_CHOICES = 'choices';
	const SETTING_REQUIRED = 'required';
	const SETTING_EMAIL_CONFIRMATION = 'email_confirmation';
	const SETTING_NAME_FIELDS = 'name_fields';
	const SETTING_TIME_FORMAT = 'time_format';
	const SETTING_TIME_SUB_LABELS = 'time_sub_labels';
	const SETTING_DESCRIPTION = 'description';
	const SETTING_PLACEHOLDER = 'placeholder';
	const SETTING_DEFAULT_VALUE = 'default_value';
	const SETTING_ADMIN_LABEL = 'admin_label';
	const SETTING_ADDRESS_FIELDS = 'address_fields';

	public static function get_editable_form_setting_keys() {
		return array(
			self::SETTING_TITLE,
			self::SETTING_DESCRIPTION,
		);
	}

	public static function get_editable_setting_keys() {
		return array(
			self::SETTING_LABEL,
			self::SETTING_CONSENT_CHECKBOX_LABEL,
			self::SETTING_CHOICES,
			self::SETTING_REQUIRED,
			self::SETTING_EMAIL_CONFIRMATION,
			self::SETTING_NAME_FIELDS,
			self::SETTING_TIME_FORMAT,
			self::SETTING_TIME_SUB_LABELS,
			self::SETTING_ADDRESS_FIELDS,
			self::SETTING_DESCRIPTION,
			self::SETTING_PLACEHOLDER,
			self::SETTING_DEFAULT_VALUE,
			self::SETTING_ADMIN_LABEL,
		);
	}

	public static function get_non_choice_editable_setting_keys() {
		return array(
			self::SETTING_LABEL,
			self::SETTING_REQUIRED,
			self::SETTING_DESCRIPTION,
			self::SETTING_PLACEHOLDER,
			self::SETTING_DEFAULT_VALUE,
			self::SETTING_ADMIN_LABEL,
		);
	}

	public static function get_email_editable_setting_keys() {
		return array(
			self::SETTING_LABEL,
			self::SETTING_REQUIRED,
			self::SETTING_DESCRIPTION,
			self::SETTING_EMAIL_CONFIRMATION,
			self::SETTING_PLACEHOLDER,
			self::SETTING_DEFAULT_VALUE,
			self::SETTING_ADMIN_LABEL,
		);
	}

	public static function get_name_editable_setting_keys() {
		return array(
			self::SETTING_LABEL,
			self::SETTING_NAME_FIELDS,
			self::SETTING_REQUIRED,
			self::SETTING_DESCRIPTION,
			self::SETTING_ADMIN_LABEL,
		);
	}

	public static function get_time_editable_setting_keys() {
		return array(
			self::SETTING_LABEL,
			self::SETTING_DESCRIPTION,
			self::SETTING_TIME_FORMAT,
			self::SETTING_TIME_SUB_LABELS,
			self::SETTING_REQUIRED,
		);
	}

	public static function get_consent_editable_setting_keys() {
		return array(
			self::SETTING_LABEL,
			self::SETTING_CONSENT_CHECKBOX_LABEL,
			self::SETTING_REQUIRED,
			self::SETTING_DESCRIPTION,
			self::SETTING_ADMIN_LABEL,
		);
	}

	public static function get_address_editable_setting_keys() {
		return array(
			self::SETTING_LABEL,
			self::SETTING_ADDRESS_FIELDS,
			self::SETTING_REQUIRED,
			self::SETTING_DESCRIPTION,
			self::SETTING_ADMIN_LABEL,
		);
	}

	public static function get_setting_labels() {
		return array(
			self::SETTING_TITLE         => esc_html__( 'Title', 'frontend-field-edit-for-gf' ),
			self::SETTING_LABEL         => esc_html__( 'Label', 'frontend-field-edit-for-gf' ),
			self::SETTING_CONSENT_CHECKBOX_LABEL => esc_html__( 'Consent Checkbox Label', 'frontend-field-edit-for-gf' ),
			self::SETTING_CHOICES       => esc_html__( 'Choices', 'frontend-field-edit-for-gf' ),
			self::SETTING_REQUIRED      => esc_html__( 'Required', 'frontend-field-edit-for-gf' ),
			self::SETTING_EMAIL_CONFIRMATION => esc_html__( 'Enable Email Confirmation', 'frontend-field-edit-for-gf' ),
			self::SETTING_NAME_FIELDS   => esc_html__( 'Name Fields', 'frontend-field-edit-for-gf' ),
			self::SETTING_TIME_FORMAT   => esc_html__( 'Time Format', 'frontend-field-edit-for-gf' ),
			self::SETTING_TIME_SUB_LABELS => esc_html__( 'Sub-Labels', 'frontend-field-edit-for-gf' ),
			self::SETTING_ADDRESS_FIELDS => esc_html__( 'Address Fields', 'frontend-field-edit-for-gf' ),
			self::SETTING_DESCRIPTION   => esc_html__( 'Description', 'frontend-field-edit-for-gf' ),
			self::SETTING_PLACEHOLDER   => esc_html__( 'Placeholder', 'frontend-field-edit-for-gf' ),
			self::SETTING_DEFAULT_VALUE => esc_html__( 'Default Value', 'frontend-field-edit-for-gf' ),
			self::SETTING_ADMIN_LABEL   => esc_html__( 'Admin Field Label', 'frontend-field-edit-for-gf' ),
		);
	}

	public static function get_form_item_labels() {
		return array(
			self::SETTING_TITLE       => esc_html__( 'Form Title', 'frontend-field-edit-for-gf' ),
			self::SETTING_DESCRIPTION => esc_html__( 'Form Description', 'frontend-field-edit-for-gf' ),
		);
	}

	public static function get_supported_field_types() {
		return array(
			'text',
			'textarea',
			'address',
			'consent',
			'email',
			'name',
			'time',
			'phone',
			'website',
			'number',
			'select',
			'multi_choice',
			'radio',
			'checkbox',
			'date',
		);
	}

	public static function get_field_type_labels() {
		return array(
			'text'     => esc_html__( 'Single Line Text', 'frontend-field-edit-for-gf' ),
			'textarea' => esc_html__( 'Paragraph Text', 'frontend-field-edit-for-gf' ),
			'address'  => esc_html__( 'Address', 'frontend-field-edit-for-gf' ),
			'consent'  => esc_html__( 'Consent', 'frontend-field-edit-for-gf' ),
			'email'    => esc_html__( 'Email', 'frontend-field-edit-for-gf' ),
			'name'     => esc_html__( 'Name', 'frontend-field-edit-for-gf' ),
			'time'     => esc_html__( 'Time', 'frontend-field-edit-for-gf' ),
			'phone'    => esc_html__( 'Phone', 'frontend-field-edit-for-gf' ),
			'website'  => esc_html__( 'Website', 'frontend-field-edit-for-gf' ),
			'number'   => esc_html__( 'Number', 'frontend-field-edit-for-gf' ),
			'select'   => esc_html__( 'Drop Down', 'frontend-field-edit-for-gf' ),
			'multi_choice' => esc_html__( 'Multiple Choice', 'frontend-field-edit-for-gf' ),
			'radio'    => esc_html__( 'Radio Buttons', 'frontend-field-edit-for-gf' ),
			'checkbox' => esc_html__( 'Checkboxes', 'frontend-field-edit-for-gf' ),
			'date'     => esc_html__( 'Date', 'frontend-field-edit-for-gf' ),
		);
	}

	public static function get_field_type_setting_matrix() {
		return array(
			'text'     => self::get_non_choice_editable_setting_keys(),
			'textarea' => self::get_non_choice_editable_setting_keys(),
			'address'  => self::get_address_editable_setting_keys(),
			'consent'  => self::get_consent_editable_setting_keys(),
			'email'    => self::get_email_editable_setting_keys(),
			'name'     => self::get_name_editable_setting_keys(),
			'time'     => self::get_time_editable_setting_keys(),
			'phone'    => self::get_non_choice_editable_setting_keys(),
			'website'  => self::get_non_choice_editable_setting_keys(),
			'number'   => self::get_non_choice_editable_setting_keys(),
			'select'   => array( self::SETTING_LABEL, self::SETTING_CHOICES, self::SETTING_REQUIRED, self::SETTING_DESCRIPTION, self::SETTING_ADMIN_LABEL ),
			'multi_choice' => array( self::SETTING_LABEL, self::SETTING_CHOICES, self::SETTING_REQUIRED, self::SETTING_DESCRIPTION, self::SETTING_ADMIN_LABEL ),
			'radio'    => array( self::SETTING_LABEL, self::SETTING_CHOICES, self::SETTING_REQUIRED, self::SETTING_DESCRIPTION, self::SETTING_ADMIN_LABEL ),
			'checkbox' => array( self::SETTING_LABEL, self::SETTING_CHOICES, self::SETTING_REQUIRED, self::SETTING_DESCRIPTION, self::SETTING_ADMIN_LABEL ),
			'date'     => self::get_non_choice_editable_setting_keys(),
		);
	}

	public static function get_default_plugin_settings() {
		$defaults = array(
			'enable_frontend_field_edit' => true,
			'default_form_mode'          => self::MODE_DISABLED,
			'required_capability'        => 'gf_frontend_field_edit',
			'enable_audit_log'           => true,
			'role_cap_administrator'     => true,
			'role_cap_editor'            => false,
			'role_cap_author'            => false,
			'role_cap_contributor'       => false,
			'role_cap_subscriber'        => false,
		);

		foreach ( self::get_editable_setting_keys() as $setting_key ) {
			$defaults[ self::get_setting_option_key( $setting_key ) ] = self::SETTING_ADMIN_LABEL !== $setting_key;
		}

		foreach ( self::get_editable_form_setting_keys() as $setting_key ) {
			$defaults[ self::get_form_item_option_key( $setting_key ) ] = true;
		}

		foreach ( self::get_supported_field_types() as $field_type ) {
			$defaults[ self::get_field_type_option_key( $field_type ) ] = true;
		}

		return $defaults;
	}

	public static function get_default_form_settings() {
		$defaults = array(
			'mode'                   => self::MODE_INHERIT,
			'new_field_default_mode' => self::MODE_INHERIT,
		);

		foreach ( self::get_editable_form_setting_keys() as $setting_key ) {
			$defaults[ self::get_form_item_setting_key( $setting_key ) ] = true;
		}

		return $defaults;
	}

	public static function get_default_field_settings( $field_type ) {
		$defaults          = array();
		$available_settings = self::get_available_settings_for_field_type( $field_type );

		foreach ( $available_settings as $setting_key ) {
			$defaults[ $setting_key ] = self::SETTING_ADMIN_LABEL !== $setting_key;
		}

		return $defaults;
	}

	public static function get_setting_option_key( $setting_key ) {
		return 'allowed_setting_' . $setting_key;
	}

	public static function get_form_item_option_key( $setting_key ) {
		return 'allowed_form_item_' . $setting_key;
	}

	public static function get_form_item_setting_key( $setting_key ) {
		return 'enabled_form_item_' . $setting_key;
	}

	public static function get_field_type_option_key( $field_type ) {
		return 'supported_type_' . $field_type;
	}

	public static function get_role_capability_setting_key( $role_name ) {
		return 'role_cap_' . sanitize_key( $role_name );
	}

	public static function normalize_mode( $mode, $fallback = self::MODE_INHERIT ) {
		$allowed_modes = array( self::MODE_INHERIT, self::MODE_ENABLED, self::MODE_DISABLED );
		$mode          = is_string( $mode ) ? strtolower( $mode ) : '';

		return in_array( $mode, $allowed_modes, true ) ? $mode : $fallback;
	}

	public static function is_supported_field_type( $field_type, $plugin_settings = array() ) {
		$field_type = is_string( $field_type ) ? $field_type : '';
		if ( ! in_array( $field_type, self::get_supported_field_types(), true ) ) {
			return false;
		}

		$plugin_settings = wp_parse_args( is_array( $plugin_settings ) ? $plugin_settings : array(), self::get_default_plugin_settings() );

		return ! empty( $plugin_settings[ self::get_field_type_option_key( $field_type ) ] );
	}

	public static function get_available_settings_for_field_type( $field_type ) {
		$matrix = self::get_field_type_setting_matrix();

		return isset( $matrix[ $field_type ] ) ? $matrix[ $field_type ] : array();
	}

	public static function get_global_allowed_settings( $plugin_settings ) {
		$plugin_settings = wp_parse_args( is_array( $plugin_settings ) ? $plugin_settings : array(), self::get_default_plugin_settings() );
		$allowed         = array();

		foreach ( self::get_editable_setting_keys() as $setting_key ) {
			if ( ! empty( $plugin_settings[ self::get_setting_option_key( $setting_key ) ] ) ) {
				$allowed[] = $setting_key;
			}
		}

		return $allowed;
	}

	public static function get_global_allowed_form_items( $plugin_settings ) {
		$plugin_settings = wp_parse_args( is_array( $plugin_settings ) ? $plugin_settings : array(), self::get_default_plugin_settings() );
		$allowed         = array();

		foreach ( self::get_editable_form_setting_keys() as $setting_key ) {
			if ( ! empty( $plugin_settings[ self::get_form_item_option_key( $setting_key ) ] ) ) {
				$allowed[] = $setting_key;
			}
		}

		return $allowed;
	}

	public static function get_form_enabled_form_items( $form_settings ) {
		$form_settings = wp_parse_args( is_array( $form_settings ) ? $form_settings : array(), self::get_default_form_settings() );
		$allowed       = array();

		foreach ( self::get_editable_form_setting_keys() as $setting_key ) {
			if ( ! empty( $form_settings[ self::get_form_item_setting_key( $setting_key ) ] ) ) {
				$allowed[] = $setting_key;
			}
		}

		return $allowed;
	}

	public static function resolve_form_mode( $plugin_settings, $form_settings ) {
		$plugin_settings = wp_parse_args( is_array( $plugin_settings ) ? $plugin_settings : array(), self::get_default_plugin_settings() );
		$form_settings   = wp_parse_args( is_array( $form_settings ) ? $form_settings : array(), self::get_default_form_settings() );

		$form_mode = self::normalize_mode( rgar( $form_settings, 'mode' ) );
		if ( self::MODE_INHERIT !== $form_mode ) {
			return $form_mode;
		}

		return self::normalize_mode( rgar( $plugin_settings, 'default_form_mode' ), self::MODE_DISABLED );
	}

	public static function resolve_field_mode( $field, $plugin_settings, $form_settings ) {
		$field_mode = self::normalize_mode( rgar( $field, 'ffe_mode' ) );
		if ( self::MODE_INHERIT !== $field_mode ) {
			return $field_mode;
		}

		return self::resolve_form_mode( $plugin_settings, $form_settings );
	}

	public static function get_field_enabled_settings( $field ) {
		$field_type       = self::get_field_type_key( $field );
		$default_settings = self::get_default_field_settings( $field_type );
		$field_settings   = rgar( $field, 'ffe_settings' );

		if ( ! is_array( $field_settings ) ) {
			return $default_settings;
		}

		return wp_parse_args( $field_settings, $default_settings );
	}

	public static function resolve_allowed_settings_for_field( $field, $plugin_settings, $form_settings ) {
		if ( empty( $plugin_settings['enable_frontend_field_edit'] ) ) {
			return array();
		}

		$field_type = self::get_field_type_key( $field );
		if ( ! self::is_supported_field_type( $field_type, $plugin_settings ) ) {
			return array();
		}

		if ( self::MODE_ENABLED !== self::resolve_field_mode( $field, $plugin_settings, $form_settings ) ) {
			return array();
		}

		$available_settings = self::get_available_settings_for_field_type( $field_type );
		$global_settings    = self::get_global_allowed_settings( $plugin_settings );
		$field_settings     = self::get_field_enabled_settings( $field );
		$allowed_settings   = array();

		foreach ( $available_settings as $setting_key ) {
			if ( in_array( $setting_key, $global_settings, true ) && ! empty( $field_settings[ $setting_key ] ) ) {
				$allowed_settings[] = $setting_key;
			}
		}

		return $allowed_settings;
	}

	public static function get_form_hash( $form ) {
		if ( ! is_array( $form ) ) {
			return '';
		}

		return md5( wp_json_encode( self::get_hashable_form_snapshot( $form ) ) );
	}

	private static function get_hashable_form_snapshot( $form ) {
		$snapshot = array(
			'id'          => absint( rgar( $form, 'id' ) ),
			'title'       => (string) rgar( $form, 'title' ),
			'description' => (string) rgar( $form, 'description' ),
			'fields'      => array(),
		);

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $snapshot;
		}

		foreach ( $form['fields'] as $field ) {
			$snapshot['fields'][] = self::get_hashable_field_snapshot( $field );
		}

		return $snapshot;
	}

	private static function get_hashable_field_snapshot( $field ) {
		$field_settings = self::get_field_enabled_settings( $field );

		foreach ( $field_settings as $setting_key => $is_enabled ) {
			$field_settings[ $setting_key ] = ! empty( $is_enabled );
		}

		ksort( $field_settings );

		return array(
			'id'           => absint( rgar( $field, 'id' ) ),
			'type'         => self::get_field_type_key( $field ),
			'label'        => (string) rgar( $field, 'label' ),
			'isRequired'   => ! empty( self::get_field_property( $field, 'isRequired' ) ),
			'description'  => (string) rgar( $field, 'description' ),
			'placeholder'  => (string) rgar( $field, 'placeholder' ),
			'defaultValue' => (string) rgar( $field, 'defaultValue' ),
			'adminLabel'   => (string) rgar( $field, 'adminLabel' ),
			'ffe_mode'     => self::normalize_mode( rgar( $field, 'ffe_mode' ) ),
			'ffe_settings' => $field_settings,
		);
	}

	public static function resolve_editor_context( $form, $plugin_settings, $form_settings ) {
		$plugin_settings = wp_parse_args( is_array( $plugin_settings ) ? $plugin_settings : array(), self::get_default_plugin_settings() );
		$form_settings   = wp_parse_args( is_array( $form_settings ) ? $form_settings : array(), self::get_default_form_settings() );
		$form_mode       = self::resolve_form_mode( $plugin_settings, $form_settings );
		$context         = array(
			'allowed'     => false,
			'form_items'  => array(),
			'settings'    => array(),
			'form_hash'   => self::get_form_hash( $form ),
			'field_types' => self::get_supported_field_types(),
		);

		if ( empty( $plugin_settings['enable_frontend_field_edit'] ) || self::MODE_ENABLED !== $form_mode ) {
			return $context;
		}

		$context['form_items'] = self::resolve_allowed_form_items( $form, $plugin_settings, $form_settings );

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			$context['allowed']    = ! empty( $context['form_items'] );

			return $context;
		}

		foreach ( $form['fields'] as $field ) {
			$field_id          = (string) rgar( $field, 'id' );
			$allowed_settings  = self::resolve_allowed_settings_for_field( $field, $plugin_settings, $form_settings );

			if ( empty( $field_id ) || empty( $allowed_settings ) ) {
				continue;
			}

			$context['settings'][ $field_id ] = array(
				'type'     => self::get_field_type_key( $field ),
				'settings' => $allowed_settings,
			);
		}

		$context['allowed'] = ! empty( $context['settings'] ) || ! empty( $context['form_items'] );

		return $context;
	}

	private static function resolve_allowed_form_items( $form, $plugin_settings, $form_settings ) {
		$allowed_items = array();

		if ( empty( $plugin_settings['enable_frontend_field_edit'] ) || self::MODE_ENABLED !== self::resolve_form_mode( $plugin_settings, $form_settings ) ) {
			return $allowed_items;
		}

		$global_allowed_items = self::get_global_allowed_form_items( $plugin_settings );
		$form_allowed_items   = self::get_form_enabled_form_items( $form_settings );
		$editable_items       = array_values( array_intersect( self::get_editable_form_setting_keys(), $global_allowed_items, $form_allowed_items ) );

		foreach ( $editable_items as $setting_key ) {
			if ( '' === trim( (string) rgar( $form, $setting_key ) ) ) {
				continue;
			}

			$allowed_items[ $setting_key ] = array(
				'type'     => 'form',
				'settings' => array( $setting_key ),
			);
		}

		return $allowed_items;
	}

	public static function get_field_type_key( $field ) {
		$field_type = (string) self::get_field_property( $field, 'type' );
		$input_type = (string) self::get_field_property( $field, 'inputType' );

		if ( '' !== $input_type && ( 'consent' === $input_type || ( ! self::is_supported_field_type( $field_type ) && self::is_supported_field_type( $input_type ) ) ) ) {
			return $input_type;
		}

		return $field_type;
	}

	private static function get_field_property( $field, $property ) {
		if ( is_object( $field ) && isset( $field->{$property} ) ) {
			return $field->{$property};
		}

		if ( is_array( $field ) ) {
			return rgar( $field, $property );
		}

		return null;
	}
}