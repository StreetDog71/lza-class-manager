<?php
/**
 * Plugin Name: LZA Class Manager
 * Description: Manages custom CSS classes for Gutenberg blocks
 * Version: 1.0.0
 * Author: Lazy Algorithm
 * Text Domain: lza-class-manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LZA_CLASS_MANAGER_VERSION', '1.0.0');
define('LZA_CLASS_MANAGER_PATH', plugin_dir_path(__FILE__));
define('LZA_CLASS_MANAGER_URL', plugin_dir_url(__FILE__));

// Include class files
require_once LZA_CLASS_MANAGER_PATH . 'includes/class-lza-core.php';
require_once LZA_CLASS_MANAGER_PATH . 'includes/class-lza-css-processor.php';
require_once LZA_CLASS_MANAGER_PATH . 'includes/class-lza-admin.php';
require_once LZA_CLASS_MANAGER_PATH . 'includes/class-lza-editor.php';
require_once LZA_CLASS_MANAGER_PATH . 'includes/class-lza-frontend.php';

// Include diagnostic and debug tools in admin context
if (is_admin()) {
    require_once LZA_CLASS_MANAGER_PATH . 'includes/diagnostics.php';
    require_once LZA_CLASS_MANAGER_PATH . 'includes/debug-tool.php';
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
register_activation_hook(__FILE__, 'lza_class_manager_activate');

/**
 * Plugin activation hook
 */
function lza_class_manager_activate() {
    // Initialize CSS processor and ensure initial files are created
    $css_processor = new LZA_CSS_Processor();
    $css_processor->maybe_initialize_css_files();
}