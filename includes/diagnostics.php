<?php
/**
 * Diagnostic functionality for the plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add diagnostic information to the Site Health screen
 */
function lza_class_manager_add_debug_info($info) {
    $css_processor = new LZA_CSS_Processor();
    $paths = $css_processor->get_css_paths();
    
    // Get file sizes
    $css_size = file_exists($paths['custom_css_path']) ? filesize($paths['custom_css_path']) : 0;
    $min_css_size = file_exists($paths['custom_css_min_path']) ? filesize($paths['custom_css_min_path']) : 0;
    $vars_size = file_exists($paths['root_vars_path']) ? filesize($paths['root_vars_path']) : 0;
    $editor_css_size = file_exists($paths['editor_css_path']) ? filesize($paths['editor_css_path']) : 0;
    
    // Format paths for display
    $uploads_dir = str_replace(ABSPATH, '', $paths['uploads_dir']);
    
    // Count classes in the CSS
    $class_count = 0;
    if (file_exists($paths['custom_css_path'])) {
        $css_content = file_get_contents($paths['custom_css_path']);
        $class_names = $css_processor->extract_class_names($css_content);
        $class_count = count($class_names);
    }
    
    // Add debug info
    $info['lza-class-manager'] = array(
        'label' => 'LZA Class Manager',
        'fields' => array(
            'version' => array(
                'label' => 'Plugin Version',
                'value' => LZA_CLASS_MANAGER_VERSION
            ),
            'css_storage' => array(
                'label' => 'CSS Storage Location',
                'value' => $uploads_dir
            ),
            'css_files' => array(
                'label' => 'CSS Files',
                'value' => 'Custom CSS: ' . $css_processor->format_file_size($css_size) . '<br>' .
                           'Minified CSS: ' . $css_processor->format_file_size($min_css_size) . '<br>' .
                           'Root Variables: ' . $css_processor->format_file_size($vars_size) . '<br>' .
                           'Editor CSS: ' . $css_processor->format_file_size($editor_css_size)
            ),
            'css_writable' => array(
                'label' => 'CSS Directory Writable',
                'value' => is_writable($paths['uploads_dir']) ? 'Yes' : 'No'
            ),
            'classes_count' => array(
                'label' => 'Defined Classes',
                'value' => $class_count
            )
        )
    );
    
    return $info;
}

// Add to the Site Health Debug info
add_filter('debug_information', 'lza_class_manager_add_debug_info');

/**
 * Add LZA Class Manager tests to Site Health
 */
function lza_class_manager_site_health_tests($tests) {
    $tests['direct']['lza_css_writable'] = array(
        'label' => 'LZA Class Manager CSS directory is writable',
        'test'  => 'lza_test_css_directory',
    );
    
    return $tests;
}
add_filter('site_status_tests', 'lza_class_manager_site_health_tests');

/**
 * Test if CSS directory is writable
 */
function lza_test_css_directory() {
    $css_processor = new LZA_CSS_Processor();
    $paths = $css_processor->get_css_paths();
    
    $result = array(
        'label' => 'LZA Class Manager CSS directory is writable',
        'status'      => 'good',
        'badge'       => array(
            'label' => 'LZA Class Manager',
            'color' => 'blue',
        ),
        'description' => '<p>The CSS directory used by LZA Class Manager is writable.</p>',
        'actions'     => '',
        'test'        => 'lza_css_writable',
    );
    
    if (!file_exists($paths['uploads_dir'])) {
        $result['status'] = 'recommended';
        $result['description'] = '<p>The CSS directory does not exist. This will be created when needed.</p>';
    } elseif (!is_writable($paths['uploads_dir'])) {
        $result['status'] = 'critical';
        $result['description'] = '<p>The CSS directory is not writable. Please check the permissions on ' . 
                                esc_html($paths['uploads_dir']) . '</p>';
        $result['actions'] = '<p><a href="https://wordpress.org/support/article/changing-file-permissions/" target="_blank">Learn more about file permissions</a></p>';
    }
    
    return $result;
}