<?php

defined( 'ABSPATH' ) || exit;

class FFE_Frontend_Controller {
	/**
	 * @var FFE_AddOn
	 */
	private $addon;

	private $frontend_data = array();

	public function __construct( FFE_AddOn $addon ) {
		$this->addon = $addon;

		add_filter( 'gform_pre_render', array( $this, 'collect_frontend_data' ) );
		add_action( 'wp_footer', array( $this, 'print_frontend_data' ), 8 );
	}

	public function collect_frontend_data( $form ) {
		$plugin_settings = $this->addon->get_plugin_settings();
		$form_id         = absint( rgar( $form, 'id' ) );
		$stored_form     = $form_id ? GFAPI::get_form( $form_id ) : array();
		$canonical_form  = ( ! empty( $stored_form ) && ! is_wp_error( $stored_form ) ) ? $stored_form : $form;

		if ( empty( $plugin_settings['enable_frontend_field_edit'] ) || ! is_user_logged_in() ) {
			return $form;
		}

		$required_capability = rgar( $plugin_settings, 'required_capability', FFE_AddOn::CAPABILITY );
		if ( ! current_user_can( $required_capability ) ) {
			return $form;
		}

		$form_settings  = $this->addon->get_form_settings_with_defaults( $canonical_form );
		$context        = FFE_Config_Resolver::resolve_editor_context( $canonical_form, $plugin_settings, $form_settings );

		if ( ! $context['allowed'] ) {
			return $form;
		}

		wp_enqueue_script( 'ffe_frontend_editor' );
		wp_enqueue_style( 'ffe_frontend_editor' );
		$this->maybe_enqueue_datepicker_assets( $form, rgar( $context, 'settings', array() ) );

		$this->frontend_data[ (string) $form_id ] = array(
			'formId'                => $form_id,
			'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
			'nonce'                 => wp_create_nonce( 'ffe_save_field_setting_' . $form_id ),
			'formHash'              => $context['form_hash'],
			'formSubLabelPlacement' => (string) rgar( $canonical_form, 'subLabelPlacement' ),
			'requiredIndicatorHtml' => class_exists( 'GFFormsModel' ) ? '<span class="gfield_required">' . GFFormsModel::get_required_indicator( $form_id ) . '</span>' : '',
			'formItems'             => $this->build_form_item_payload( $canonical_form, rgar( $context, 'form_items', array() ) ),
			'fields'                => $this->build_field_payload( $form, $context['settings'] ),
		);

		return $form;
	}

	public function print_frontend_data() {
		if ( empty( $this->frontend_data ) ) {
			return;
		}
		?>
		<script>
			window.ffeData = Object.assign( {}, window.ffeData || {}, <?php echo wp_json_encode( $this->frontend_data ); ?> );
		</script>
		<?php
	}

	private function build_field_payload( $form, $allowed_fields ) {
		$payload = array();

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $payload;
		}

		foreach ( $form['fields'] as $field ) {
			$field_id = (string) rgar( $field, 'id' );

			if ( empty( $field_id ) || empty( $allowed_fields[ $field_id ]['settings'] ) ) {
				continue;
			}

			$payload[ $field_id ] = array(
				'id'                => absint( $field_id ),
				'type'              => rgar( $field, 'type' ),
				'inputType'         => $this->get_field_input_type( $field ),
				'dateConfig'        => $this->get_date_field_config( $field ),
				'addressType'       => $this->get_address_type_key( $field ),
				'addressConfig'     => $this->get_address_field_config( $field ),
				'choiceLimit'       => (string) $this->get_field_property( $field, 'choiceLimit' ),
				'nameFormat'        => (string) $this->get_field_property( $field, 'nameFormat' ),
				'namePrefixChoices' => $this->get_name_prefix_choices( $field ),
				'size'              => (string) $this->get_field_property( $field, 'size' ),
				'subLabelPlacement' => (string) $this->get_field_property( $field, 'subLabelPlacement' ),
				'settings'          => array_values( $allowed_fields[ $field_id ]['settings'] ),
				'values'            => $this->get_field_values( $field ),
			);
		}

		return $payload;
	}

	/**
	 * Enqueue the datepicker assets needed by the default value control.
	 *
	 * Gravity Forms 2.x only. Gravity Forms 3.0 removed both handles along with
	 * jQuery UI and loads its own datepicker automatically for any form that
	 * renders a datepicker field, so the wp_script_is()/wp_style_is() guards
	 * below simply find nothing to enqueue there.
	 */
	private function maybe_enqueue_datepicker_assets( $form, $allowed_fields ) {
		if ( ! $this->has_editable_date_default_field( $form, $allowed_fields ) ) {
			return;
		}

		if ( wp_script_is( 'gform_datepicker_init', 'registered' ) ) {
			wp_enqueue_script( 'gform_datepicker_init' );
		}

		if ( wp_style_is( 'gforms_datepicker_css', 'registered' ) ) {
			wp_enqueue_style( 'gforms_datepicker_css' );
		}
	}

	private function has_editable_date_default_field( $form, $allowed_fields ) {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) || ! is_array( $allowed_fields ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			$field_id       = (string) rgar( $field, 'id' );
			$field_settings = isset( $allowed_fields[ $field_id ]['settings'] ) && is_array( $allowed_fields[ $field_id ]['settings'] ) ? $allowed_fields[ $field_id ]['settings'] : array();

			if ( empty( $field_settings ) || ! in_array( FFE_Config_Resolver::SETTING_DEFAULT_VALUE, $field_settings, true ) ) {
				continue;
			}

			if ( $this->is_date_field( $field ) ) {
				return true;
			}
		}

		return false;
	}

	private function build_form_item_payload( $form, $allowed_items ) {
		$payload = array();
		$form_item_labels = FFE_Config_Resolver::get_form_item_labels();

		if ( empty( $allowed_items ) || ! is_array( $allowed_items ) ) {
			return $payload;
		}

		foreach ( $allowed_items as $item_key => $item_config ) {
			$payload[ $item_key ] = array(
				'id'       => (string) $item_key,
				'type'     => 'form',
				'label'    => rgar( $form_item_labels, $item_key ),
				'settings' => array_values( rgar( $item_config, 'settings', array() ) ),
				'values'   => $this->get_form_item_values( $form, $item_key ),
			);
		}

		return $payload;
	}

	private function get_field_values( $field ) {
		return array(
			'label'              => (string) rgar( $field, 'label' ),
			'consent_checkbox_label' => (string) rgar( $field, 'checkboxLabel' ),
			'choices'            => $this->get_field_choices( $field ),
			'required'           => ! empty( $this->get_field_property( $field, 'isRequired' ) ),
			'email_confirmation' => ! empty( $this->get_field_property( $field, 'emailConfirmEnabled' ) ),
			'name_fields'        => $this->get_name_field_inputs( $field ),
			'time_format'        => $this->get_time_format( $field ),
			'time_sub_labels'    => $this->get_time_field_inputs( $field ),
			'address_fields'     => $this->get_address_field_inputs( $field ),
			'description'        => (string) rgar( $field, 'description' ),
			'placeholder'        => (string) rgar( $field, 'placeholder' ),
			'default_value'      => $this->is_time_field( $field ) ? $this->get_time_field_default_values( $field ) : ( $this->is_address_field( $field ) ? $this->get_address_field_default_values( $field ) : (string) rgar( $field, 'defaultValue' ) ),
			'default_country'    => $this->is_address_field( $field ) ? (string) $this->get_field_property( $field, 'defaultCountry' ) : '',
			'admin_label'        => (string) rgar( $field, 'adminLabel' ),
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

	private function get_name_prefix_choices( $field ) {
		$inputs = $this->normalize_name_field_inputs( $field );
		foreach ( $inputs as $input ) {
			if ( preg_match( '/\.2$/', (string) rgar( $input, 'id' ) ) && isset( $input['choices'] ) && is_array( $input['choices'] ) ) {
				return array_values( $input['choices'] );
			}
		}

		return $this->get_default_name_prefix_choices();
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
		$inputs   = $this->normalize_address_field_inputs( $field );
		$payload  = array();

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

	private function get_field_input_type( $field ) {
		$input_type = $this->get_field_property( $field, 'inputType' );

		return $input_type ? (string) $input_type : (string) rgar( $field, 'type' );
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
		$field_type = (string) rgar( $field, 'type' );
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

	private function get_form_item_values( $form, $item_key ) {
		switch ( $item_key ) {
			case FFE_Config_Resolver::SETTING_TITLE:
				return array(
					FFE_Config_Resolver::SETTING_TITLE => (string) rgar( $form, 'title' ),
				);

			case FFE_Config_Resolver::SETTING_DESCRIPTION:
				return array(
					FFE_Config_Resolver::SETTING_DESCRIPTION => (string) rgar( $form, 'description' ),
				);

			default:
				return array();
		}
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