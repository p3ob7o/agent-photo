<?php
/**
 * Plugin Name: Agent Photo
 * Plugin URI: https://paolobelcastro.com
 * Description: Enhance your Gutenberg image block with AI-powered assistance to add alt text, legends, and title options.
 * Version: 0.1.4
 * Author: Paolo Belcastro
 * Author URI: https://paolobelcastro.com
 * License: GPL2+
 * Text Domain: agent-photo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'AGENT_PHOTO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENT_PHOTO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files.
require_once AGENT_PHOTO_PLUGIN_DIR . 'inc/class-agent-photo-settings.php';
require_once AGENT_PHOTO_PLUGIN_DIR . 'inc/class-agent-photo-api.php';
require_once AGENT_PHOTO_PLUGIN_DIR . 'inc/class-agent-photo-processor.php';

// Initialize settings.
add_action( 'admin_menu', array( 'Agent_Photo_Settings', 'register_settings_page' ) );
add_action( 'admin_init', array( 'Agent_Photo_Settings', 'register_settings' ) );

// Enqueue plugin scripts and styles.
function agent_photo_enqueue_assets() {
	// Only enqueue if we're in the block editor
	if ( ! is_admin() ) {
		return;
	}

	wp_enqueue_style(
		'agent-photo-style',
		AGENT_PHOTO_PLUGIN_URL . 'assets/css/agent-photo.css',
		array(),
		'1.0.0'
	);

	wp_enqueue_script(
		'agent-photo-sidebar',
		AGENT_PHOTO_PLUGIN_URL . 'assets/js/agent-photo-sidebar.js',
		array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-hooks', 'wp-block-editor', 'wp-i18n', 'wp-editor' ),
		'1.0.0',
		true
	);

	// Localize the script with new data
	wp_localize_script(
		'agent-photo-sidebar',
		'agentPhotoSettings',
		array(
			'restUrl' => esc_url_raw( rest_url() ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		)
	);

	wp_enqueue_script(
		'agent-photo-modal',
		AGENT_PHOTO_PLUGIN_URL . 'assets/js/agent-photo-modal.js',
		array( 'wp-element', 'wp-components', 'wp-i18n' ),
		'1.0.0',
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'agent_photo_enqueue_assets' );

// Add this near the other add_action calls
add_action( 'rest_api_init', function() {
    error_log('Agent Photo: REST API routes registration check');
}); 