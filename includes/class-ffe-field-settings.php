<?php

defined( 'ABSPATH' ) || exit;

class FFE_Field_Settings {
	/**
	 * @var FFE_AddOn
	 */
	private $addon;

	public function __construct( FFE_AddOn $addon ) {
		$this->addon = $addon;

		add_action( 'gform_field_advanced_settings', array( $this, 'render_mode_setting' ), 10, 2 );
		add_action( 'gform_field_advanced_settings', array( $this, 'render_editable_settings_setting' ), 10, 2 );
		add_action( 'gform_editor_js', array( $this, 'editor_js_init' ) );
	}

	public function render_mode_setting( $position, $form_id ) {
		unset( $form_id );

		if ( 425 !== (int) $position ) {
			return;
		}
		?>
		<li class="ffe_mode_setting field_setting" style="display:none;">
			<label for="ffe_mode" class="section_label"><?php esc_html_e( 'Frontend Field Edit', 'frontend-field-edit-for-gf' ); ?></label>
			<select id="ffe_mode">
				<option value="inherit"><?php esc_html_e( 'Inherit', 'frontend-field-edit-for-gf' ); ?></option>
				<option value="enabled"><?php esc_html_e( 'Enable', 'frontend-field-edit-for-gf' ); ?></option>
				<option value="disabled"><?php esc_html_e( 'Disable', 'frontend-field-edit-for-gf' ); ?></option>
			</select>
			<span class="gf_settings_description"><?php esc_html_e( 'Choose whether this field can be edited from the frontend. Inherit uses the form-level setting.', 'frontend-field-edit-for-gf' ); ?></span>
		</li>
		<?php
	}

	public function render_editable_settings_setting( $position, $form_id ) {
		unset( $form_id );

		if ( 450 !== (int) $position ) {
			return;
		}
		?>
		<li class="ffe_editable_settings_setting field_setting" style="display:none;">
			<fieldset>
				<legend class="section_label"><?php esc_html_e( 'Frontend Editable Settings', 'frontend-field-edit-for-gf' ); ?></legend>
				<div class="ffe-setting-options">
					<?php foreach ( FFE_Config_Resolver::get_setting_labels() as $setting_key => $label ) : ?>
						<div class="ffe-setting-option" data-setting-key="<?php echo esc_attr( $setting_key ); ?>">
							<input type="checkbox" id="ffe_setting_<?php echo esc_attr( $setting_key ); ?>" />
							<label class="inline" for="ffe_setting_<?php echo esc_attr( $setting_key ); ?>"><?php echo esc_html( $label ); ?></label>
						</div>
					<?php endforeach; ?>
				</div>
				<span class="gf_settings_description" id="ffe_field_setting_help"><?php esc_html_e( 'Only supported options for this field type are shown here. Global plugin settings can still restrict what appears on the frontend.', 'frontend-field-edit-for-gf' ); ?></span>
			</fieldset>
		</li>
		<?php
	}

	public function editor_js_init() {
		$config = array(
			'supportedTypes'       => FFE_Config_Resolver::get_supported_field_types(),
			'settingMatrix'        => FFE_Config_Resolver::get_field_type_setting_matrix(),
			'settingLabels'        => FFE_Config_Resolver::get_setting_labels(),
			'defaultFieldSettings' => $this->get_default_field_settings_map(),
			'strings'              => array(
				'noSettingsHelp' => esc_html__( 'This field type does not expose any frontend-editable settings in v1.', 'frontend-field-edit-for-gf' ),
				'settingsHelp'   => esc_html__( 'Only supported options for this field type are shown here. Global plugin settings can still restrict what appears on the frontend.', 'frontend-field-edit-for-gf' ),
			),
		);
		?>
		<script>
			window.ffeFieldSettingConfig = <?php echo wp_json_encode( $config ); ?>;
			if ( window.FFEAdminFieldSettings && typeof window.FFEAdminFieldSettings.boot === 'function' ) {
				window.FFEAdminFieldSettings.boot( window.ffeFieldSettingConfig );
			}
		</script>
		<?php
	}

	private function get_default_field_settings_map() {
		$defaults = array();

		foreach ( FFE_Config_Resolver::get_supported_field_types() as $field_type ) {
			$defaults[ $field_type ] = FFE_Config_Resolver::get_default_field_settings( $field_type );
		}

		return $defaults;
	}
}