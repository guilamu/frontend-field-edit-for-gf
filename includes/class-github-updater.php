<?php

defined( 'ABSPATH' ) || exit;

class FFE_GF_GitHub_Updater {
	private const GITHUB_USER = 'guilamu';
	private const GITHUB_REPO = 'frontend-field-edit-for-gf';
	private const PLUGIN_FILE = 'frontend-field-edit-for-gravity-forms/frontend-field-edit-for-gravity-forms.php';
	private const PLUGIN_SLUG = 'frontend-field-edit-for-gravity-forms';
	private const PLUGIN_NAME = 'Frontend Field Edit for Gravity Forms';
	private const PLUGIN_DESCRIPTION = 'Lets trusted users edit safe Gravity Forms field settings from the frontend.';
	private const REQUIRES_WP = '5.8';
	private const TESTED_WP = '6.7';
	private const REQUIRES_PHP = '7.4';
	private const REQUIRES_GF = '2.5';
	private const TEXT_DOMAIN = 'frontend-field-edit-for-gf';
	private const CACHE_KEY = 'ffe_gf_github_release';
	private const CACHE_EXPIRATION = 43200;

	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check_for_update' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder_name' ), 10, 4 );
		add_action( 'admin_head', array( __CLASS__, 'plugin_info_css' ) );
	}

	private static function get_github_token() {
		if ( defined( 'FFE_GF_GITHUB_TOKEN' ) && is_string( FFE_GF_GITHUB_TOKEN ) ) {
			return FFE_GF_GITHUB_TOKEN;
		}

		return '';
	}

	private static function get_release_data() {
		$release_data = get_transient( self::CACHE_KEY );

		if ( false !== $release_data && is_array( $release_data ) ) {
			return $release_data;
		}

		$token = self::get_github_token();
		$headers = array();

		if ( '' !== $token ) {
			$headers['Authorization'] = 'token ' . $token;
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_USER, self::GITHUB_REPO ),
			array(
				'user-agent' => 'WordPress/' . self::PLUGIN_SLUG,
				'timeout'    => 15,
				'headers'    => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( self::PLUGIN_NAME . ' Update Error: ' . $response->get_error_message() );
			}

			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $response_code ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( self::PLUGIN_NAME . ' Update Error: HTTP ' . $response_code );
			}

			return null;
		}

		$release_data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $release_data['tag_name'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( self::PLUGIN_NAME . ' Update Error: No tag_name in release' );
			}

			return null;
		}

		set_transient( self::CACHE_KEY, $release_data, self::CACHE_EXPIRATION );

		return $release_data;
	}

	private static function get_package_url( array $release_data ) {
		if ( ! empty( $release_data['assets'] ) && is_array( $release_data['assets'] ) ) {
			foreach ( $release_data['assets'] as $asset ) {
				if ( empty( $asset['browser_download_url'] ) || empty( $asset['name'] ) ) {
					continue;
				}

				if ( '.zip' === substr( $asset['name'], -4 ) ) {
					return $asset['browser_download_url'];
				}
			}
		}

		return isset( $release_data['zipball_url'] ) ? $release_data['zipball_url'] : '';
	}

	public static function check_for_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $locales );

		if ( self::PLUGIN_FILE !== $plugin_file ) {
			return $update;
		}

		$release_data = self::get_release_data();

		if ( null === $release_data ) {
			return $update;
		}

		$new_version = ltrim( $release_data['tag_name'], 'v' );
		$current_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0.0.0';

		if ( version_compare( $current_version, $new_version, '>=' ) ) {
			return $update;
		}

		return array(
			'id'            => 'github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
			'slug'          => self::PLUGIN_SLUG,
			'plugin'        => self::PLUGIN_FILE,
			'new_version'   => $new_version,
			'version'       => $new_version,
			'package'       => self::get_package_url( $release_data ),
			'url'           => isset( $release_data['html_url'] ) ? $release_data['html_url'] : '',
			'tested'        => self::TESTED_WP,
			'requires'      => self::REQUIRES_WP,
			'requires_php'  => self::REQUIRES_PHP,
			'compatibility' => new stdClass(),
			'icons'         => array(),
			'banners'       => array(),
		);
	}

	public static function plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $res;
		}

		if ( ! isset( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $res;
		}

		$plugin_file = WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE;
		$plugin_data = get_plugin_data( $plugin_file, false, false );
		$release_data = self::get_release_data();
		$installed_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0.0.0';
		$release_version = ( $release_data && ! empty( $release_data['tag_name'] ) ) ? ltrim( $release_data['tag_name'], 'v' ) : '';
		$version = $installed_version;

		if ( '' !== $release_version && version_compare( $release_version, $installed_version, '>' ) ) {
			$version = $release_version;
		}

		$res = new stdClass();
		$res->name = self::PLUGIN_NAME;
		$res->slug = self::PLUGIN_SLUG;
		$res->plugin = self::PLUGIN_FILE;
		$res->version = $version;
		$res->author = sprintf( '<a href="https://github.com/%s">%s</a>', esc_attr( self::GITHUB_USER ), esc_html( self::GITHUB_USER ) );
		$res->homepage = sprintf( 'https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO );
		$res->requires = self::REQUIRES_WP;
		$res->tested = get_bloginfo( 'version' );
		$res->requires_php = self::REQUIRES_PHP;

		if ( $release_data && '' !== $release_version && version_compare( $release_version, $installed_version, '>' ) ) {
			$res->download_link = self::get_package_url( $release_data );
			$res->last_updated = isset( $release_data['published_at'] ) ? $release_data['published_at'] : '';
		}

		$readme = self::parse_readme();

		$res->sections = array(
			'description' => ! empty( $readme['description'] ) ? $readme['description'] : '<p>' . esc_html( self::PLUGIN_DESCRIPTION ) . '</p>',
		);

		if ( ! empty( $readme['installation'] ) ) {
			$res->sections['installation'] = $readme['installation'];
		}

		if ( ! empty( $readme['faq'] ) ) {
			$res->sections['faq'] = $readme['faq'];
		}

		$changelog_html = '';

		if ( $release_data && ! empty( $release_data['body'] ) && version_compare( $installed_version, $version, '<' ) ) {
			$changelog_html .= '<h4>' . esc_html( $version ) . '</h4>' . self::markdown_to_html( $release_data['body'] );
		}

		if ( ! empty( $readme['changelog'] ) ) {
			$changelog_html .= $readme['changelog'];
		}

		$res->sections['changelog'] = ! empty( $changelog_html )
			? $changelog_html
			: sprintf(
				'<p>See <a href="https://github.com/%1$s/%2$s/releases" target="_blank" rel="noopener noreferrer">GitHub releases</a> for changelog.</p>',
				esc_attr( self::GITHUB_USER ),
				esc_attr( self::GITHUB_REPO )
			);

		return $res;
	}

	public static function plugin_info_css() {
		if ( ! isset( $_GET['plugin'], $_GET['tab'] ) ) {
			return;
		}

		$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
		$plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );

		if ( 'plugin-information' !== $tab || self::PLUGIN_SLUG !== $plugin ) {
			return;
		}

		$pattern_css = '--s: 27px;' . '--c1: #b2b2b2;' . '--c2: #ffffff;' . '--c3: #d9d9d9;' . '--_g: var(--c3) 0 120deg, #0000 0;';
		$pattern_bg = 'conic-gradient(from -60deg at 50% calc(100%/3), var(--_g)),'
			. 'conic-gradient(from 120deg at 50% calc(200%/3), var(--_g)),'
			. 'conic-gradient(from 60deg at calc(200%/3), var(--c3) 60deg, var(--c2) 0 120deg, #0000 0),'
			. 'conic-gradient(from 180deg at calc(100%/3), var(--c1) 60deg, var(--_g)),'
			. 'linear-gradient(90deg, var(--c1) calc(100%/6), var(--c2) 0 50%, var(--c1) 0 calc(500%/6), var(--c2) 0)';

		echo '<style>'
			. '#plugin-information-title.with-banner {' . $pattern_css . 'background: ' . $pattern_bg . ' !important;background-size: calc(1.732 * var(--s)) var(--s) !important;}'
			. '#plugin-information-title.with-banner h2 {position: relative;font-family: "Helvetica Neue", sans-serif;display: inline-block;font-size: 30px;line-height: 1.68;box-sizing: border-box;max-width: 100%;padding: 0 15px;margin-top: 174px;color: #fff;background: rgba(29, 35, 39, 0.9);text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);box-shadow: 0 0 30px rgba(255, 255, 255, 0.1);border-radius: 8px;}'
			. '#section-holder .section h2 { margin: 1.5em 0 0.5em; clear: none; }'
			. '#section-holder .section h3 { margin: 1.5em 0 0.5em; }'
			. '#section-holder .section > :first-child { margin-top: 0; }'
			. '.md-table { display: table; width: 100%; border-collapse: collapse; margin: 1em 0; font-size: 13px; }'
			. '.md-tr { display: table-row; }'
			. '.md-tr > span { display: table-cell; padding: 6px 10px; border: 1px solid #ddd; vertical-align: top; }'
			. '.md-th > span { font-weight: 600; background: #f5f5f5; }'
			. '</style>';

		echo '<script>'
			. 'document.addEventListener("DOMContentLoaded",function(){'
			. 'var title=document.getElementById("plugin-information-title");'
			. 'if(title){title.classList.add("with-banner");}'
			. '});'
			. '</script>';

		if ( '' !== self::REQUIRES_GF ) {
			$requires_gf_html = sprintf(
				'<strong>%1$s</strong> %2$s',
				esc_html__( 'Requires Gravity Forms:', self::TEXT_DOMAIN ),
				esc_html( sprintf( __( '%s or higher', self::TEXT_DOMAIN ), self::REQUIRES_GF ) )
			);

			echo '<script>'
				. 'document.addEventListener("DOMContentLoaded",function(){'
				. 'var items=document.querySelectorAll(".fyi ul li");'
				. 'var php=null;'
				. 'for(var i=0;i<items.length;i++){if(items[i].textContent.indexOf("Requires PHP")!==-1){php=items[i];break;}}'
				. 'if(!php){return;}'
				. 'var li=document.createElement("li");'
				. 'li.innerHTML=' . wp_json_encode( $requires_gf_html ) . ';'
				. 'php.parentNode.insertBefore(li,php.nextSibling);'
				. '});'
				. '</script>';
		}
	}

	private static function parse_readme() {
		$readme_path = WP_PLUGIN_DIR . '/' . dirname( self::PLUGIN_FILE ) . '/README.md';

		if ( ! file_exists( $readme_path ) ) {
			return array();
		}

		$content = file_get_contents( $readme_path );

		if ( false === $content ) {
			return array();
		}

		$content = preg_replace( '/^#\s+[^\n]+\n*/m', '', $content, 1 );

		$utility_sections = array(
			'changelog',
			'requirements',
			'installation',
			'faq',
			'project structure',
			'acknowledgements',
			'license',
		);

		$parts = preg_split( '/^##\s+/m', $content );
		$description = trim( isset( $parts[0] ) ? $parts[0] : '' );
		$installation = '';
		$faq = '';
		$changelog = '';

		for ( $index = 1, $count = count( $parts ); $index < $count; $index++ ) {
			$lines = explode( "\n", $parts[ $index ], 2 );
			$title = strtolower( trim( $lines[0] ) );
			$body = trim( isset( $lines[1] ) ? $lines[1] : '' );

			if ( 'installation' === $title ) {
				$installation .= $body . "\n\n";
			} elseif ( 'faq' === $title ) {
				$faq .= $body . "\n\n";
			} elseif ( 'changelog' === $title ) {
				$changelog .= $body . "\n\n";
			} elseif ( ! in_array( $title, $utility_sections, true ) ) {
				$description .= "\n\n## " . trim( $lines[0] ) . "\n" . $body;
			}
		}

		return array(
			'description'  => self::markdown_to_html( trim( $description ) ),
			'installation' => self::markdown_to_html( trim( $installation ) ),
			'faq'          => self::markdown_to_html( trim( $faq ) ),
			'changelog'    => self::markdown_to_html( trim( $changelog ) ),
		);
	}

	private static function markdown_to_html( $markdown ) {
		if ( '' === $markdown ) {
			return '';
		}

		$markdown = preg_replace( '/!\[[^\]]*\]\([^\)]+\)/', '', $markdown );

		if ( ! class_exists( 'Parsedown' ) ) {
			$parsedown_path = __DIR__ . '/Parsedown.php';

			if ( ! file_exists( $parsedown_path ) ) {
				return wpautop( esc_html( $markdown ) );
			}

			require_once $parsedown_path;
		}

		$parsedown = new Parsedown();
		$parsedown->setSafeMode( true );
		$html = $parsedown->text( $markdown );

		return self::tables_to_divs( $html );
	}

	private static function tables_to_divs( $html ) {
		return preg_replace_callback(
			'/<table>(.*?)<\/table>/s',
			function ( $matches ) {
				$table_html = $matches[1];
				$output = '<div class="md-table">';
				$rows = array();

				preg_match_all( '/<tr>(.*?)<\/tr>/s', $table_html, $rows );

				foreach ( $rows[1] as $index => $row_content ) {
					$is_header = 0 === $index && false !== strpos( $table_html, '<thead>' );
					$row_class = $is_header ? 'md-tr md-th' : 'md-tr';
					$cells = array();

					preg_match_all( '/<t[hd]>(.*?)<\/t[hd]>/s', $row_content, $cells );

					$output .= '<div class="' . esc_attr( $row_class ) . '">';

					foreach ( $cells[1] as $cell ) {
						$output .= '<span>' . $cell . '</span>';
					}

					$output .= '</div>';
				}

				$output .= '</div>';

				return $output;
			},
			$html
		);
	}

	public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		unset( $upgrader );

		if ( ! isset( $hook_extra['plugin'] ) || self::PLUGIN_FILE !== $hook_extra['plugin'] ) {
			return $source;
		}

		$correct_folder = dirname( self::PLUGIN_FILE );
		$source_folder = basename( untrailingslashit( $source ) );

		if ( $source_folder === $correct_folder ) {
			return $source;
		}

		$new_source = trailingslashit( $remote_source ) . $correct_folder . '/';

		if ( $wp_filesystem && $wp_filesystem->move( $source, $new_source ) ) {
			return $new_source;
		}

		if ( $wp_filesystem && $wp_filesystem->copy( $source, $new_source, true ) && $wp_filesystem->delete( $source, true ) ) {
			return $new_source;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '%s updater: failed to rename update folder from %s to %s', self::PLUGIN_NAME, $source, $new_source ) );
		}

		return new WP_Error(
			'rename_failed',
			__( 'Unable to rename the update folder. Please retry or update manually.', self::TEXT_DOMAIN )
		);
	}
}

FFE_GF_GitHub_Updater::init();