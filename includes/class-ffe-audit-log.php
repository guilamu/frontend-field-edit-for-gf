<?php

defined( 'ABSPATH' ) || exit;

class FFE_Audit_Log {
	private static $table_exists = null;

	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gf_frontend_field_edit_log';
	}

	public static function install() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	form_id BIGINT(20) UNSIGNED NOT NULL,
	field_id BIGINT(20) UNSIGNED NOT NULL,
	setting_key VARCHAR(50) NOT NULL,
	old_value LONGTEXT NULL,
	new_value LONGTEXT NULL,
	status VARCHAR(20) NOT NULL,
	user_id BIGINT(20) UNSIGNED NOT NULL,
	ip_address VARCHAR(45) NOT NULL,
	page_url VARCHAR(500) NULL,
	created_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	KEY idx_form_field (form_id, field_id),
	KEY idx_created_at (created_at),
	KEY idx_user_id (user_id),
	KEY idx_setting_key (setting_key)
) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		self::$table_exists = true;
	}

	public static function uninstall() {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::get_table_name() );
		self::$table_exists = false;
	}

	public static function log_change( $data ) {
		global $wpdb;

		if ( ! self::ensure_table_exists() ) {
			return false;
		}

		$defaults = array(
			'form_id'     => 0,
			'field_id'    => 0,
			'setting_key' => '',
			'old_value'   => null,
			'new_value'   => null,
			'status'      => 'success',
			'user_id'     => get_current_user_id(),
			'ip_address'  => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'page_url'    => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
			'created_at'  => current_time( 'mysql', true ),
		);

		$data = wp_parse_args( is_array( $data ) ? $data : array(), $defaults );

		$result = $wpdb->insert(
			self::get_table_name(),
			array(
				'form_id'     => absint( $data['form_id'] ),
				'field_id'    => absint( $data['field_id'] ),
				'setting_key' => sanitize_key( $data['setting_key'] ),
				'old_value'   => maybe_serialize( $data['old_value'] ),
				'new_value'   => maybe_serialize( $data['new_value'] ),
				'status'      => sanitize_key( $data['status'] ),
				'user_id'     => absint( $data['user_id'] ),
				'ip_address'  => sanitize_text_field( $data['ip_address'] ),
				'page_url'    => esc_url_raw( $data['page_url'] ),
				'created_at'  => gmdate( 'Y-m-d H:i:s', strtotime( $data['created_at'] ) ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	private static function ensure_table_exists() {
		if ( true === self::$table_exists ) {
			return true;
		}

		if ( self::table_exists() ) {
			self::$table_exists = true;
			return true;
		}

		self::install();

		self::$table_exists = self::table_exists();

		return self::$table_exists;
	}

	private static function table_exists() {
		global $wpdb;

		$table_name = self::get_table_name();
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return is_string( $result ) && $result === $table_name;
	}
}