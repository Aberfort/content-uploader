<?php
/**
 * Plugin Name: Content Uploader
 * Plugin URI:  https://example.com
 * Description: A plugin that uploads and imports content from ZIP archives with multi-language support (WPML).
 * Version:     1.0.14
 * Author:      Serhii Vasyliev
 * Text Domain: content-uploader
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define constants
if ( ! defined( 'CONTENT_UPLOADER_VERSION' ) ) {
	define( 'CONTENT_UPLOADER_VERSION', '1.0.14' );
}
if ( ! defined( 'CONTENT_UPLOADER_PATH' ) ) {
	define( 'CONTENT_UPLOADER_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CONTENT_UPLOADER_URL' ) ) {
	define( 'CONTENT_UPLOADER_URL', plugin_dir_url( __FILE__ ) );
}

// Autoload dependencies
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize and run the plugin
function run_content_uploader_plugin() {
	$plugin = new ContentUploader\Plugin();
	$plugin->run();
}

run_content_uploader_plugin();