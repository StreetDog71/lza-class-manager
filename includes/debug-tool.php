<?php
/**
 * Debug tools for the plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling debug operations
 */
class LZA_Debug_Tool {
    /**
     * Initialize the debug tools
     */
    public static function init() {
        // Only in debug mode
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        // Add debug endpoint
        add_action('admin_post_lza_debug_action', array(__CLASS__, 'handle_debug_action'));
        
        // Add admin bar debug menu
        add_action('admin_bar_menu', array(__CLASS__, 'add_debug_menu'), 999);
    }
    
    /**
     * Add debug menu to admin bar
     */
    public static function add_debug_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add main debug menu
        $wp_admin_bar->add_node(array(
            'id'    => 'lza-debug',
            'title' => 'LZA Debug',
            'href'  => '#',
            'meta'  => array(
                'title' => 'LZA Class Manager Debug Tools',
            ),
        ));
        
        // Add rebuild CSS action
        $wp_admin_bar->add_node(array(
            'id'     => 'lza-rebuild-css',
            'parent' => 'lza-debug',
            'title'  => 'Rebuild CSS Files',
            'href'   => admin_url('admin-post.php?action=lza_debug_action&task=rebuild_css&nonce=' . wp_create_nonce('lza_debug_action')),
            'meta'   => array(
                'title' => 'Rebuild all CSS files',
            ),
        ));
        
        // Add CSS info action
        $wp_admin_bar->add_node(array(
            'id'     => 'lza-css-info',
            'parent' => 'lza-debug',
            'title'  => 'CSS Info',
            'href'   => admin_url('admin-post.php?action=lza_debug_action&task=css_info&nonce=' . wp_create_nonce('lza_debug_action')),
            'meta'   => array(
                'title' => 'Show CSS file information',
            ),
        ));
    }
    
    /**
     * Handle debug actions
     */
    public static function handle_debug_action() {
        if (!current_user_can('manage_options') || !isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'lza_debug_action')) {
            wp_die('Security check failed');
        }
        
        $task = isset($_GET['task']) ? sanitize_key($_GET['task']) : '';
        
        // Initialize CSS processor
        $core = new LZA_Core();
        $core->init();
        $css_processor = new LZA_CSS_Processor();
        
        switch ($task) {
            case 'rebuild_css':
                // Get current CSS content
                $css_content = $css_processor->get_default_css();
                
                // Process and save it again
                $css_processor->process_css($css_content);
                
                // Redirect back with success message
                wp_redirect(add_query_arg('lza-debug-message', 'css-rebuilt', wp_get_referer()));
                exit;
                
            case 'css_info':
                // Display CSS file info
                $paths = $css_processor->get_css_paths();
                echo '<div style="padding:20px; background:#fff; max-width:800px; margin:20px auto; font-family:monospace;">';
                echo '<h2>LZA Class Manager CSS Files</h2>';
                
                // Check each file
                $css_files = array(
                    'custom_css_path' => 'Main CSS File',
                    'custom_css_min_path' => 'Minified CSS File',
                    'root_vars_path' => 'Root Variables CSS File',
                    'editor_css_path' => 'Editor CSS File'
                );
                
                echo '<table style="width:100%; border-collapse:collapse; margin:20px 0;">';
                echo '<tr><th style="text-align:left; padding:5px; border-bottom:1px solid #ccc;">File</th>';
                echo '<th style="text-align:left; padding:5px; border-bottom:1px solid #ccc;">Size</th>';
                echo '<th style="text-align:left; padding:5px; border-bottom:1px solid #ccc;">Last Modified</th>';
                echo '<th style="text-align:left; padding:5px; border-bottom:1px solid #ccc;">Status</th></tr>';
                
                foreach ($css_files as $path_key => $description) {
                    $file_path = $paths[$path_key];
                    $file_exists = file_exists($file_path);
                    $file_size = $file_exists ? $css_processor->format_file_size(filesize($file_path)) : 'N/A';
                    $last_modified = $file_exists ? date('Y-m-d H:i:s', filemtime($file_path)) : 'N/A';
                    $status = $file_exists ? 'OK' : 'Missing';
                    $status_color = $file_exists ? 'green' : 'red';
                    
                    echo "<tr>";
                    echo "<td style='padding:5px; border-bottom:1px solid #eee;'>{$description}<br><small>{$file_path}</small></td>";
                    echo "<td style='padding:5px; border-bottom:1px solid #eee;'>{$file_size}</td>";
                    echo "<td style='padding:5px; border-bottom:1px solid #eee;'>{$last_modified}</td>";
                    echo "<td style='padding:5px; border-bottom:1px solid #eee; color:{$status_color};'>{$status}</td>";
                    echo "</tr>";
                }
                
                echo '</table>';
                
                // Add back button
                echo '<a href="' . esc_url(wp_get_referer()) . '" style="display:inline-block; margin-top:20px; padding:5px 10px; background:#2271b1; color:#fff; text-decoration:none; border-radius:3px;">Go Back</a>';
                echo '</div>';
                exit;
                
            default:
                // Unknown task
                wp_redirect(wp_get_referer());
                exit;
        }
    }
}

// Initialize debug tools
LZA_Debug_Tool::init();
