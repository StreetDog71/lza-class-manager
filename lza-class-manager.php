<?php
/**
 * Plugin Name: LZA Class Manager
 * Description: Manages custom CSS classes for Gutenberg blocks
 * Version: 1.0.0
 * Author: Lazy Algorithm
 * Text Domain: lza-class-manager
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'LZA_CLASS_MANAGER_VERSION', '1.0.0' );
define( 'LZA_CLASS_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'LZA_CLASS_MANAGER_URL', plugin_dir_url( __FILE__ ) );

// Include class files
require_once LZA_CLASS_MANAGER_PATH . 'includes/class-lza-core.php';
require_once LZA_CLASS_MANAGER_PATH . 'includes/class-lza-css-processor.php';
require_once LZA_CLASS_MANAGER_PATH . 'includes/class-lza-admin.php';
require_once LZA_CLASS_MANAGER_PATH . 'includes/class-lza-editor.php';
require_once LZA_CLASS_MANAGER_PATH . 'includes/class-lza-frontend.php';

// Include diagnostic and debug tools in admin context
if ( is_admin() ) {
	require_once LZA_CLASS_MANAGER_PATH . 'diagnostics.php';
	require_once LZA_CLASS_MANAGER_PATH . 'debug-tool.php';
}

/**
 * Initialize the plugin
 */
function lza_class_manager_init() {
	// Initialize the core class which coordinates the others
	$lza_core = new LZA_Core();
	$lza_core->init();
}

// Start the plugin
lza_class_manager_init();

// Register activation hook
register_activation_hook( __FILE__, 'lza_class_manager_activate' );

/**
 * Plugin activation hook
 */
function lza_class_manager_activate() {
	// Initialize CSS processor to generate required CSS files
	$css_processor = new LZA_CSS_Processor();

	// Create the directory structure and generate CSS files
	$files_created = $css_processor->maybe_initialize_css_files();

	if ( ! $files_created ) {
		error_log( 'LZA Class Manager: Failed to create one or more CSS files' );

		// Add admin notice for file creation failures
		add_option( 'lza_css_file_creation_failed', true );
		add_action( 'admin_notices', 'lza_css_file_creation_notice' );
	}

	// Clear any cached styles
	delete_transient( 'lza_cached_css' );

	// Flush rewrite rules if needed
	flush_rewrite_rules();
}

/**
 * Admin notice for CSS file creation failures
 */
function lza_css_file_creation_notice() {
	if ( get_option( 'lza_css_file_creation_failed' ) ) {
		echo '<div class="notice notice-error is-dismissible">';
		echo '<p>' . esc_html__( 'LZA Class Manager: Failed to create one or more CSS files. Please check file permissions in your uploads directory.', 'lza-class-manager' ) . '</p>';
		echo '</div>';

		// Remove the option so the notice only appears once
		delete_option( 'lza_css_file_creation_failed' );
	}
}
