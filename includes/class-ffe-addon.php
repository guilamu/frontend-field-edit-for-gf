<?php

GFForms::include_addon_framework();

require_once FFE_GF_PLUGIN_DIR . 'includes/class-ffe-config-resolver.php';
require_once FFE_GF_PLUGIN_DIR . 'includes/class-ffe-audit-log.php';
require_once FFE_GF_PLUGIN_DIR . 'includes/class-ffe-frontend-controller.php';
require_once FFE_GF_PLUGIN_DIR . 'includes/class-ffe-sanitizer.php';
require_once FFE_GF_PLUGIN_DIR . 'includes/class-ffe-validator.php';
require_once FFE_GF_PLUGIN_DIR . 'includes/class-ffe-save-controller.php';

class FFE_AddOn extends GFAddOn {
	const CAPABILITY = 'gf_frontend_field_edit';

	private $field_settings = null;
	private $frontend_controller = null;
	private $save_controller = null;

	protected $_version = FFE_GF_VERSION;
	protected $_min_gravityforms_version = '2.5';
	protected $_slug = 'frontend-field-edit-for-gf';
	protected $_path = 'frontend-field-edit-for-gravity-forms/frontend-field-edit-for-gravity-forms.php';
	protected $_full_path = FFE_GF_PLUGIN_FILE;
	protected $_title = 'Frontend Field Edit for Gravity Forms';
	protected $_short_title = 'Frontend Field Edit';
	protected $_capabilities = array( 'gravityforms_edit_forms', 'gf_frontend_field_edit' );
	protected $_capabilities_settings_page = array( 'gravityforms_edit_forms' );
	protected $_capabilities_form_settings = array( 'gravityforms_edit_forms' );
	protected $_capabilities_uninstall = array( 'gravityforms_uninstall' );

	private static $_instance = null;

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function init() {
		parent::init();

		$this->load_textdomain();
		$this->sync_role_capabilities();
		$this->init_save_controller();
	}

	public function init_admin() {
		parent::init_admin();

		$this->maybe_seed_default_settings();

		if ( class_exists( 'GFForms' ) && 'form_editor' === GFForms::get_page() ) {
			$this->init_field_settings();
		}
	}

	public function init_frontend() {
		parent::init_frontend();

		$this->init_frontend_controller();
	}

	public function init_ajax() {
		parent::init_ajax();

		$this->init_save_controller();
	}

	public function scripts() {
		return array_merge(
			parent::scripts(),
			array(
				array(
					'handle'  => 'ffe_admin_field_settings',
					'src'     => $this->get_base_url() . '/assets/js/admin-field-settings.js',
					'version' => $this->_version,
					'deps'    => array( 'jquery' ),
					'enqueue' => array(
						array( 'admin_page' => array( 'form_editor' ) ),
					),
					'strings' => array(
						'activeText'   => esc_html__( 'Active', 'frontend-field-edit-for-gf' ),
						'inactiveText' => esc_html__( 'Inactive', 'frontend-field-edit-for-gf' ),
					),
				),
				array(
					'handle'  => 'ffe_frontend_editor',
					'src'     => $this->get_base_url() . '/assets/js/frontend-editor.js',
					'version' => $this->_version,
					'deps'    => array( 'jquery', 'jquery-ui-sortable' ),
					'enqueue' => array(
						array( 'field_types' => FFE_Config_Resolver::get_supported_field_types() ),
					),
					'strings' => array(
						'panelTitle'    => esc_html__( 'Edit Field', 'frontend-field-edit-for-gf' ),
						'closeLabel'    => esc_html__( 'Close', 'frontend-field-edit-for-gf' ),
						'previousLabel' => esc_html__( 'Previous', 'frontend-field-edit-for-gf' ),
						'nextLabel'     => esc_html__( 'Next', 'frontend-field-edit-for-gf' ),
						'previousTooltip' => esc_html__( 'Previous field (CTRL+←)', 'frontend-field-edit-for-gf' ),
						'nextTooltip'     => esc_html__( 'Next field (CTRL+→)', 'frontend-field-edit-for-gf' ),
						'cancelLabel'   => esc_html__( 'Cancel', 'frontend-field-edit-for-gf' ),
						'saveLabel'     => esc_html__( 'Save', 'frontend-field-edit-for-gf' ),
						'saveTooltip'   => esc_html__( 'CTRL+S/↵ to save', 'frontend-field-edit-for-gf' ),
						'savingLabel'   => esc_html__( 'Saving...', 'frontend-field-edit-for-gf' ),
						'editLabel'     => esc_html__( 'Edit', 'frontend-field-edit-for-gf' ),
						'addChoiceLabel' => esc_html__( 'Add choice', 'frontend-field-edit-for-gf' ),
						'deleteChoiceLabel' => esc_html__( 'Delete choice', 'frontend-field-edit-for-gf' ),
						'selectedChoiceLabel' => esc_html__( 'Selected by default', 'frontend-field-edit-for-gf' ),
						'activeText'    => esc_html__( 'Active', 'frontend-field-edit-for-gf' ),
						'inactiveText'  => esc_html__( 'Inactive', 'frontend-field-edit-for-gf' ),
						'showLabel'     => esc_html__( 'Show', 'frontend-field-edit-for-gf' ),
						'fieldLabel'    => esc_html__( 'Field', 'frontend-field-edit-for-gf' ),
						'customSubLabelLabel' => esc_html__( 'Custom Sub-Label', 'frontend-field-edit-for-gf' ),
						'enterEmailLabel' => esc_html__( 'Enter Email', 'frontend-field-edit-for-gf' ),
						'confirmEmailLabel' => esc_html__( 'Confirm Email', 'frontend-field-edit-for-gf' ),
						'timeFormat12Label' => esc_html__( '12 hour', 'frontend-field-edit-for-gf' ),
						'timeFormat24Label' => esc_html__( '24 hour', 'frontend-field-edit-for-gf' ),
						'errorLabel'    => esc_html__( 'Unable to save the requested change.', 'frontend-field-edit-for-gf' ),
						'successLabel'  => esc_html__( 'Changes saved.', 'frontend-field-edit-for-gf' ),
						'conflictLabel' => esc_html__( 'This form changed since the panel was opened. Refresh the page and try again.', 'frontend-field-edit-for-gf' ),
					),
				),
			)
		);
	}

	public function get_menu_icon() {
		return 'dashicons-edit';
	}

	public function get_app_menu_icon() {
		return 'dashicons-edit';
	}

	public function styles() {
		return array_merge(
			parent::styles(),
			array(
				array(
					'handle'  => 'ffe_admin_field_settings',
					'src'     => $this->get_base_url() . '/assets/css/admin-field-settings.css',
					'version' => $this->_version,
					'enqueue' => array(
						array( 'admin_page' => array( 'form_editor' ) ),
					),
				),
				array(
					'handle'  => 'ffe_frontend_editor',
					'src'     => $this->get_base_url() . '/assets/css/frontend-editor.css',
					'version' => $this->_version,
					'enqueue' => array(
						array( 'field_types' => FFE_Config_Resolver::get_supported_field_types() ),
					),
				),
			)
		);
	}

	public function get_plugin_settings() {
		$settings = parent::get_plugin_settings();

		return wp_parse_args( is_array( $settings ) ? $settings : array(), FFE_Config_Resolver::get_default_plugin_settings() );
	}

	public function get_form_settings_with_defaults( $form ) {
		$settings = parent::get_form_settings( $form );

		return wp_parse_args( is_array( $settings ) ? $settings : array(), FFE_Config_Resolver::get_default_form_settings() );
	}

	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'General', 'frontend-field-edit-for-gf' ),
				'description' => esc_html__( 'Control the global availability and baseline behavior of frontend field editing.', 'frontend-field-edit-for-gf' ),
				'fields'      => array(
					array(
						'type'    => 'checkbox',
						'name'    => 'enable_frontend_field_edit',
						'label'   => '',
						'choices' => array(
							array(
								'name'  => 'enable_frontend_field_edit',
								'label' => esc_html__( 'Enable Frontend Field Edit', 'frontend-field-edit-for-gf' ),
							),
						),
					),
					array(
						'type'          => 'radio',
						'name'          => 'default_form_mode',
						'label'         => esc_html__( 'Enable on new forms by default', 'frontend-field-edit-for-gf' ),
						'horizontal'    => true,
						'default_value' => FFE_Config_Resolver::MODE_DISABLED,
						'choices'       => array(
							array(
								'value' => FFE_Config_Resolver::MODE_ENABLED,
								'label' => esc_html__( 'Enabled', 'frontend-field-edit-for-gf' ),
							),
							array(
								'value' => FFE_Config_Resolver::MODE_DISABLED,
								'label' => esc_html__( 'Disabled', 'frontend-field-edit-for-gf' ),
							),
						),
						'description'   => esc_html__( 'Disabled is the safer default for live sites; individual forms can opt in later.', 'frontend-field-edit-for-gf' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Permissions', 'frontend-field-edit-for-gf' ),
				'fields' => array(
					array(
						'type'          => 'text',
						'name'          => 'required_capability',
						'label'         => esc_html__( 'Required capability', 'frontend-field-edit-for-gf' ),
						'class'         => 'medium',
						'default_value' => self::CAPABILITY,
						'description'   => esc_html__( 'The frontend save controller checks this capability on every request. Keep the dedicated capability unless you have a specific integration requirement.', 'frontend-field-edit-for-gf' ),
					),
					array(
						'type'        => 'checkbox',
						'label'       => esc_html__( 'Grant dedicated capability to roles', 'frontend-field-edit-for-gf' ),
						'description' => esc_html__( 'These role assignments apply only when the required capability is gf_frontend_field_edit.', 'frontend-field-edit-for-gf' ),
						'choices'     => $this->get_role_capability_choices(),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Editable Settings', 'frontend-field-edit-for-gf' ),
				'fields' => array(
					array(
						'type'        => 'checkbox',
						'label'       => esc_html__( 'Allowed editable settings', 'frontend-field-edit-for-gf' ),
						'description' => esc_html__( 'These global switches are intersected with each field\'s own toggles and field-type support rules.', 'frontend-field-edit-for-gf' ),
						'choices'     => $this->get_editable_setting_choices(),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Editable Form Content', 'frontend-field-edit-for-gf' ),
				'fields' => array(
					array(
						'type'        => 'checkbox',
						'label'       => esc_html__( 'Allowed form items', 'frontend-field-edit-for-gf' ),
						'description' => esc_html__( 'These global switches are intersected with each form\'s own toggles. Pills only appear when the title or description is actually rendered.', 'frontend-field-edit-for-gf' ),
						'choices'     => $this->get_editable_form_item_choices(),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Supported Field Types', 'frontend-field-edit-for-gf' ),
				'fields' => array(
					array(
						'type'        => 'checkbox',
						'label'       => esc_html__( 'Supported field types', 'frontend-field-edit-for-gf' ),
						'description' => esc_html__( 'Unsupported field types remain unavailable even if a browser tries to post changes for them.', 'frontend-field-edit-for-gf' ),
						'choices'     => $this->get_supported_field_type_choices(),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Audit Logging', 'frontend-field-edit-for-gf' ),
				'fields' => array(
					array(
						'type'    => 'checkbox',
						'name'    => 'enable_audit_log',
						'label'   => '',
						'choices' => array(
							array(
								'name'  => 'enable_audit_log',
								'label' => esc_html__( 'Enable audit log', 'frontend-field-edit-for-gf' ),
							),
						),
						'description' => esc_html__( 'Successful changes are stored in a dedicated audit table. Permission denials and validation failures should also be logged through Gravity Forms logging.', 'frontend-field-edit-for-gf' ),
					),
				),
			),
		);
	}

	public function form_settings_fields( $form ) {
		unset( $form );

		return array(
			array(
				'title'  => esc_html__( 'Frontend Field Edit', 'frontend-field-edit-for-gf' ),
				'fields' => array(
					array(
						'type'          => 'radio',
						'name'          => 'mode',
						'label'         => esc_html__( 'Enable frontend field editing on this form', 'frontend-field-edit-for-gf' ),
						'horizontal'    => true,
						'default_value' => FFE_Config_Resolver::MODE_INHERIT,
						'choices'       => $this->get_mode_choices(),
					),
					array(
						'type'          => 'radio',
						'name'          => 'new_field_default_mode',
						'label'         => esc_html__( 'Default for newly added supported fields on this form', 'frontend-field-edit-for-gf' ),
						'horizontal'    => true,
						'default_value' => FFE_Config_Resolver::MODE_INHERIT,
						'choices'       => $this->get_mode_choices(),
					),
					array(
						'type'        => 'checkbox',
						'label'       => esc_html__( 'Editable form content', 'frontend-field-edit-for-gf' ),
						'description' => esc_html__( 'These per-form toggles are intersected with the global form-content settings. The title or description must also exist in the rendered form heading.', 'frontend-field-edit-for-gf' ),
						'choices'     => $this->get_form_item_setting_choices(),
					),
				),
			),
		);
	}

	public function install() {
		FFE_Audit_Log::install();
		$this->grant_capability_to_role( 'administrator', self::CAPABILITY );
		$this->update_plugin_settings( $this->get_plugin_settings() );
	}

	public function uninstall() {
		FFE_Audit_Log::uninstall();
		$this->remove_capability_from_all_roles( self::CAPABILITY );
		$this->cleanup_custom_field_properties();

		return true;
	}

	private function load_textdomain() {
		load_plugin_textdomain( 'frontend-field-edit-for-gf', false, dirname( plugin_basename( FFE_GF_PLUGIN_FILE ) ) . '/languages' );
	}

	private function init_field_settings() {
		if ( $this->field_settings instanceof FFE_Field_Settings ) {
			return;
		}

		require_once FFE_GF_PLUGIN_DIR . 'includes/class-ffe-field-settings.php';
		$this->field_settings = new FFE_Field_Settings( $this );
	}

	private function init_frontend_controller() {
		if ( $this->frontend_controller instanceof FFE_Frontend_Controller ) {
			return;
		}

		$this->frontend_controller = new FFE_Frontend_Controller( $this );
	}

	private function init_save_controller() {
		if ( $this->save_controller instanceof FFE_Save_Controller ) {
			return;
		}

		$this->save_controller = new FFE_Save_Controller( $this );
	}

	private function maybe_seed_default_settings() {
		$raw_settings = parent::get_plugin_settings();
		if ( ! is_array( $raw_settings ) || array_diff_key( FFE_Config_Resolver::get_default_plugin_settings(), $raw_settings ) ) {
			$this->update_plugin_settings( $this->get_plugin_settings() );
		}
	}

	private function get_mode_choices() {
		return array(
			array(
				'value' => FFE_Config_Resolver::MODE_INHERIT,
				'label' => esc_html__( 'Inherit', 'frontend-field-edit-for-gf' ),
			),
			array(
				'value' => FFE_Config_Resolver::MODE_ENABLED,
				'label' => esc_html__( 'Enable', 'frontend-field-edit-for-gf' ),
			),
			array(
				'value' => FFE_Config_Resolver::MODE_DISABLED,
				'label' => esc_html__( 'Disable', 'frontend-field-edit-for-gf' ),
			),
		);
	}

	private function get_role_capability_choices() {
		global $wp_roles;

		if ( ! $wp_roles instanceof WP_Roles ) {
			$wp_roles = wp_roles();
		}

		$choices = array();
		foreach ( $wp_roles->roles as $role_name => $role_data ) {
			$choices[] = array(
				'name'  => FFE_Config_Resolver::get_role_capability_setting_key( $role_name ),
				'label' => translate_user_role( $role_data['name'] ),
			);
		}

		return $choices;
	}

	private function get_editable_setting_choices() {
		$choices        = array();
		$setting_labels = FFE_Config_Resolver::get_setting_labels();

		foreach ( FFE_Config_Resolver::get_editable_setting_keys() as $setting_key ) {
			$choices[] = array(
				'name'  => FFE_Config_Resolver::get_setting_option_key( $setting_key ),
				'label' => rgar( $setting_labels, $setting_key ),
			);
		}

		return $choices;
	}

	private function get_editable_form_item_choices() {
		$choices          = array();
		$form_item_labels = FFE_Config_Resolver::get_form_item_labels();

		foreach ( FFE_Config_Resolver::get_editable_form_setting_keys() as $setting_key ) {
			$choices[] = array(
				'name'  => FFE_Config_Resolver::get_form_item_option_key( $setting_key ),
				'label' => rgar( $form_item_labels, $setting_key ),
			);
		}

		return $choices;
	}

	private function get_form_item_setting_choices() {
		$choices          = array();
		$form_item_labels = FFE_Config_Resolver::get_form_item_labels();

		foreach ( FFE_Config_Resolver::get_editable_form_setting_keys() as $setting_key ) {
			$choices[] = array(
				'name'  => FFE_Config_Resolver::get_form_item_setting_key( $setting_key ),
				'label' => rgar( $form_item_labels, $setting_key ),
			);
		}

		return $choices;
	}

	private function get_supported_field_type_choices() {
		$choices           = array();
		$field_type_labels = FFE_Config_Resolver::get_field_type_labels();

		foreach ( FFE_Config_Resolver::get_supported_field_types() as $field_type ) {
			$choices[] = array(
				'name'  => FFE_Config_Resolver::get_field_type_option_key( $field_type ),
				'label' => rgar( $field_type_labels, $field_type ),
			);
		}

		return $choices;
	}

	private function sync_role_capabilities() {
		global $wp_roles;

		$settings             = $this->get_plugin_settings();
		$required_capability  = rgar( $settings, 'required_capability', self::CAPABILITY );

		if ( self::CAPABILITY !== $required_capability ) {
			return;
		}

		if ( ! $wp_roles instanceof WP_Roles ) {
			$wp_roles = wp_roles();
		}

		foreach ( $wp_roles->role_objects as $role_name => $role ) {
			$setting_key = FFE_Config_Resolver::get_role_capability_setting_key( $role_name );
			$selected    = ! empty( $settings[ $setting_key ] );

			if ( $selected && ! $role->has_cap( self::CAPABILITY ) ) {
				$role->add_cap( self::CAPABILITY );
				continue;
			}

			if ( ! $selected && $role->has_cap( self::CAPABILITY ) ) {
				$role->remove_cap( self::CAPABILITY );
			}
		}
	}

	private function grant_capability_to_role( $role_name, $capability ) {
		$role = get_role( $role_name );
		if ( $role instanceof WP_Role ) {
			$role->add_cap( $capability );
		}
	}

	private function remove_capability_from_all_roles( $capability ) {
		global $wp_roles;

		if ( ! $wp_roles instanceof WP_Roles ) {
			$wp_roles = wp_roles();
		}

		foreach ( $wp_roles->role_objects as $role ) {
			$role->remove_cap( $capability );
		}
	}

	private function cleanup_custom_field_properties() {
		if ( ! class_exists( 'GFAPI' ) ) {
			return;
		}

		$forms = array_merge( GFAPI::get_forms( true, false ), GFAPI::get_forms( false, false ) );
		foreach ( $forms as $form ) {
			if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
				continue;
			}

			$form_updated = false;
			foreach ( $form['fields'] as $index => $field ) {
				if ( is_object( $field ) ) {
					if ( isset( $field->ffe_mode ) || isset( $field->ffe_settings ) ) {
						unset( $field->ffe_mode, $field->ffe_settings );
						$form['fields'][ $index ] = $field;
						$form_updated             = true;
					}
					continue;
				}

				if ( is_array( $field ) && ( isset( $field['ffe_mode'] ) || isset( $field['ffe_settings'] ) ) ) {
					unset( $field['ffe_mode'], $field['ffe_settings'] );
					$form['fields'][ $index ] = $field;
					$form_updated             = true;
				}
			}

			if ( $form_updated ) {
				GFAPI::update_form( $form );
			}
		}
	}
}