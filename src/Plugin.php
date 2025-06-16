<?php

namespace ContentUploader;

use ContentUploader\Admin\AdminPage;
use ContentUploader\Ajax\Uploader;
use ContentUploader\Ajax\Importer;

class Plugin {
	/**
	 * Run the plugin
	 */
	public function run() {
		$this->init_hooks();
		$this->init_components();
		add_action( 'admin_init', [ $this, 'check_for_updates' ] );
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Register admin menu
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );

		// Register custom post types
		add_action( 'init', [ $this, 'register_custom_post_types' ], 0 );

		// Add filter for unique post slugs
		add_filter( 'wp_unique_post_slug', [
			$this,
			'filter_unique_post_slug'
		], 10, 6 );
	}

	/**
	 * Initialize plugin components
	 */
	private function init_components() {
		// Initialize AJAX handlers
		new Uploader();
		new Importer();
	}

	/**
	 * Register admin menu
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Content Uploader', 'content-uploader' ),
			__( 'Content Uploader', 'content-uploader' ),
			'manage_options',
			'content-uploader-admin',
			[ new AdminPage(), 'render' ],
			'dashicons-upload',
			60
		);
	}

	/**
	 * Register custom post types
	 */
	public function register_custom_post_types() {
		$cpt_types = get_option( 'content_uploader_cpt_types', [] );
		if ( ! empty( $cpt_types ) ) {
			foreach ( $cpt_types as $cpt ) {
				register_post_type( $cpt, [
					'label'    => ucfirst( str_replace( '_', ' ', $cpt ) ),
					'public'   => true,
					'show_ui'  => true,
					'supports' => [ 'title', 'editor', 'thumbnail' ],
				] );
			}
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Filter for unique post slug
	 */
	public function filter_unique_post_slug( $override_slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
		static $in_filter = false;
		if ( $in_filter ) {
			return $override_slug;
		}
		$in_filter = true;
		if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE !== 'uk' ) {
			$in_filter = false;

			return $original_slug;
		}
		$in_filter = false;

		return $override_slug;
	}

	/**
	 * Check for plugin updates.
	 */
	public function check_for_updates() {
		$transient_name = 'content_uploader_update_check';
		$update_info = get_transient( $transient_name );

		if ( false === $update_info ) {
			$response = wp_remote_get( 'https://stage.t42it.info/wp-content/plugins/content-uploader/info.json' );

			if ( is_wp_error( $response ) ) {
				return;
			}

			$body = wp_remote_retrieve_body( $response );
			$update_info = json_decode( $body, true );

			if ( ! is_array( $update_info ) ) {
				return;
			}

			set_transient( $transient_name, $update_info, DAY_IN_SECONDS );
		}

		$current_version = CONTENT_UPLOADER_VERSION;
		$new_version = $update_info['new_version'];

		if ( version_compare( $current_version, $new_version, '<' ) ) {
			add_filter( 'pre_set_site_transient_update_plugins', function( $transient ) use ( $update_info ) {
				$plugin_file = plugin_basename( CONTENT_UPLOADER_PATH . 'content-uploader.php' );

				$transient->response[ $plugin_file ] = (object) [
					'slug'        => 'content-uploader',
					'plugin'      => $plugin_file,
					'new_version' => $update_info['new_version'],
					'url'         => 'https://stage.t42it.info/wp-content/plugins/content-uploader/readme.md',
					'package'     => $update_info['package'],
				];

				return $transient;
			});

			add_filter( 'plugins_api', function( $result, $action, $args ) use ( $update_info ) {
				if ( 'plugin_information' !== $action ) {
					return $result;
				}

				if ( 'content-uploader' !== $args->slug ) {
					return $result;
				}

				$plugin_info = (object) [
					'name'            => 'Content Uploader',
					'slug'            => 'content-uploader',
					'version'         => $update_info['new_version'],
					'tested'          => $update_info['tested'],
					'requires'        => $update_info['requires'],
					'last_updated'    => $update_info['last_updated'],
					'sections'        => $update_info['sections'],
					'download_link'   => $update_info['package'],
				];

				return $plugin_info;
			}, 10, 3 );
		}
	}

	/**
	 * Activate plugin
	 */
	public function activate() {

	}

	/**
	 * Deactivate plugin
	 */
	public function deactivate() {

	}
}
