<?php
/**
 * Plugin Name: Frontend Field Edit for Gravity Forms
 * Plugin URI: https://github.com/guilamu/frontend-field-edit-for-gf
 * Description: Lets trusted users edit safe Gravity Forms field settings from the frontend.
 * Version: 1.0.2
 * Author: Guilamu
 * Author URI: https://github.com/guilamu
 * Text Domain: frontend-field-edit-for-gf
 * Domain Path: /languages
 * Update URI: https://github.com/guilamu/frontend-field-edit-for-gf
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: AGPL-3.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'FFE_GF_VERSION', '1.0.2' );
define( 'FFE_GF_PLUGIN_FILE', __FILE__ );
define( 'FFE_GF_PLUGIN_SLUG', 'frontend-field-edit-for-gf' );
define( 'FFE_GF_PLUGIN_NAME', 'Frontend Field Edit for Gravity Forms' );
define( 'FFE_GF_GITHUB_REPO', 'guilamu/frontend-field-edit-for-gf' );
define( 'FFE_GF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FFE_GF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FFE_GF_PLUGIN_DIR . 'includes/class-github-updater.php';

add_action( 'gform_loaded', array( 'FFE_GF_Bootstrap', 'load' ), 5 );
add_action( 'plugins_loaded', 'ffe_gf_register_bug_reporter', 20 );
add_filter( 'plugin_row_meta', 'ffe_gf_plugin_row_meta', 10, 2 );

class FFE_GF_Bootstrap {
	public static function load() {
		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		require_once FFE_GF_PLUGIN_DIR . 'includes/class-ffe-addon.php';
		GFAddOn::register( 'FFE_AddOn' );
	}
}

function ffe_gf() {
	if ( ! class_exists( 'FFE_AddOn' ) ) {
		return null;
	}

	return call_user_func( array( 'FFE_AddOn', 'get_instance' ) );
}

function ffe_gf_register_bug_reporter() {
	if ( ! class_exists( 'Guilamu_Bug_Reporter' ) ) {
		return;
	}

	Guilamu_Bug_Reporter::register(
		array(
			'slug'        => FFE_GF_PLUGIN_SLUG,
			'name'        => FFE_GF_PLUGIN_NAME,
			'version'     => FFE_GF_VERSION,
			'github_repo' => FFE_GF_GITHUB_REPO,
		)
	);
}

function ffe_gf_plugin_row_meta( $links, $file ) {
	if ( plugin_basename( FFE_GF_PLUGIN_FILE ) !== $file ) {
		return $links;
	}

	$links[] = sprintf(
		'<a href="%1$s" class="thickbox open-plugin-details-modal" aria-label="%2$s" data-title="%3$s">%4$s</a>',
		esc_url(
			self_admin_url(
				'plugin-install.php?tab=plugin-information&plugin=' . FFE_GF_PLUGIN_SLUG . '&TB_iframe=true&width=772&height=926'
			)
		),
		esc_attr__( 'More information about Frontend Field Edit for Gravity Forms', 'frontend-field-edit-for-gf' ),
		esc_attr__( 'Frontend Field Edit for Gravity Forms', 'frontend-field-edit-for-gf' ),
		esc_html__( 'View details', 'frontend-field-edit-for-gf' )
	);

	if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
		$links[] = sprintf(
			'<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="%1$s" data-plugin-name="%2$s">%3$s</a>',
			esc_attr( FFE_GF_PLUGIN_SLUG ),
			esc_attr__( FFE_GF_PLUGIN_NAME, 'frontend-field-edit-for-gf' ),
			esc_html__( 'Report a Bug', 'frontend-field-edit-for-gf' )
		);

		return $links;
	}

	$links[] = sprintf(
		'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
		esc_url( 'https://github.com/guilamu/guilamu-bug-reporter/releases' ),
		esc_html__( 'Report a Bug (install Bug Reporter)', 'frontend-field-edit-for-gf' )
	);

	return $links;
}