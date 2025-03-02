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

// Include diagnostic and debug tools
if (is_admin()) {
    require_once(LZA_CLASS_MANAGER_PATH . 'diagnostics.php');
    require_once(LZA_CLASS_MANAGER_PATH . 'debug-tool.php');
}

class LZA_Class_Manager {
    /**
     * Constructor
     */
    public function __construct() {
        // Frontend hooks - only load custom classes
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        
        // Proper way to add styles that will be included in the iframe
        add_action('enqueue_block_assets', array($this, 'enqueue_block_editor_styles'));
        
        // Editor hooks - load React components
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Register AJAX handler
        add_action('wp_ajax_lza_save_editor_theme', array($this, 'save_editor_theme'));
        
        // Make sure the CSS directories exist on plugin activation or settings save
        $this->ensure_css_directories();
        
        // Check if we need to initialize default CSS files
        add_action('admin_init', array($this, 'maybe_initialize_css_files'));
    }

    /**
     * Get CSS file paths - central function to determine paths for all CSS files
     *
     * @return array Array of CSS file paths and URLs
     */
    private function get_css_paths() {
        // Get WordPress uploads directory info
        $upload_dir = wp_upload_dir();
        
        // Define the plugin's CSS folder in uploads
        $css_folder = 'lza-css';
        
        // Create the paths
        $uploads_path = trailingslashit($upload_dir['basedir']) . $css_folder;
        $uploads_url = trailingslashit($upload_dir['baseurl']) . $css_folder;
        
        // Return array of all important paths and URLs
        return array(
            'uploads_dir' => $uploads_path,
            'uploads_url' => $uploads_url,
            'custom_css_path' => trailingslashit($uploads_path) . 'custom-classes.css',
            'custom_css_url' => trailingslashit($uploads_url) . 'custom-classes.css',
            'custom_css_min_path' => trailingslashit($uploads_path) . 'custom-classes.min.css',
            'custom_css_min_url' => trailingslashit($uploads_url) . 'custom-classes.min.css',
            'editor_css_path' => trailingslashit($uploads_path) . 'editor-safe-classes.css',
            'editor_css_url' => trailingslashit($uploads_url) . 'editor-safe-classes.css',
            'plugin_custom_css' => LZA_CLASS_MANAGER_PATH . 'css/custom-classes.css',
            'plugin_editor_css' => LZA_CLASS_MANAGER_PATH . 'css/editor-safe-classes.css',
        );
    }
    
    /**
     * Ensure CSS directories exist and are writable
     */
    private function ensure_css_directories() {
        $paths = $this->get_css_paths();
        
        // Create the uploads CSS directory if it doesn't exist
        if (!file_exists($paths['uploads_dir'])) {
            wp_mkdir_p($paths['uploads_dir']);
            
            // Add an index.php file for security
            file_put_contents(trailingslashit($paths['uploads_dir']) . 'index.php', "<?php\n// Silence is golden.");
        }
        
        // Ensure the directory is writable
        if (!is_writable($paths['uploads_dir'])) {
            @chmod($paths['uploads_dir'], 0755);
        }
    }
    
    /**
     * Check if we need to initialize the CSS files on plugin activation or new installation
     */
    public function maybe_initialize_css_files() {
        $paths = $this->get_css_paths();
        
        // Check if custom CSS file exists in uploads
        if (!file_exists($paths['custom_css_path'])) {
            // Get default CSS content from plugin directory
            $default_css = '';
            if (file_exists($paths['plugin_custom_css'])) {
                $default_css = file_get_contents($paths['plugin_custom_css']);
            } else {
                // If no plugin CSS file, use built-in default
                $default_css = "/* Add your custom classes here */\n\n" .
                               ".p-l {\n    padding: 1rem;\n}\n\n" .
                               ".p-xl {\n    padding: 3rem;\n}\n\n" .
                               ".bg-red {\n    background-color: red;\n}\n\n" .
                               ".text-white {\n    color: white;\n}\n";
            }
            
            // Save to uploads directory
            file_put_contents($paths['custom_css_path'], $default_css);
            
            // Create minified version
            $minified_css = $this->minify_css($default_css);
            file_put_contents($paths['custom_css_min_path'], $minified_css);
            
            // Generate editor-safe CSS
            $this->generate_editor_safe_css($default_css);
        }
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        $paths = $this->get_css_paths();
        
        // Check if minified version exists and use it for better performance
        if (file_exists($paths['custom_css_min_path'])) {
            wp_enqueue_style(
                'lza-custom-classes',
                $paths['custom_css_min_url'],
                array(),
                filemtime($paths['custom_css_min_path'])
            );
        } elseif (file_exists($paths['custom_css_path'])) {
            // Fall back to the non-minified version in uploads
            wp_enqueue_style(
                'lza-custom-classes',
                $paths['custom_css_url'],
                array(),
                filemtime($paths['custom_css_path'])
            );
        } else {
            // Last resort - use plugin directory file
            wp_enqueue_style(
                'lza-custom-classes',
                LZA_CLASS_MANAGER_URL . 'css/custom-classes.css',
                array(),
                LZA_CLASS_MANAGER_VERSION
            );
        }
    }

    /**
     * Enqueue styles that need to be included in the editor iframe
     * This uses the enqueue_block_assets hook which ensures styles are added to the iframe
     */
    public function enqueue_block_editor_styles() {
        // Only load in editor context
        if (!is_admin()) {
            return;
        }
        
        $paths = $this->get_css_paths();
        
        // First check if editor-safe CSS exists in uploads
        if (file_exists($paths['editor_css_path'])) {
            // Use wp_add_inline_style with a dependency on 'wp-block-editor' to ensure it's included in the iframe
            wp_enqueue_style(
                'wp-block-editor'
            );
            
            // Load the stylesheet content and add it inline
            $editor_css_content = file_get_contents($paths['editor_css_path']);
            if ($editor_css_content) {
                wp_add_inline_style('wp-block-editor', $editor_css_content);
            }
        } elseif (file_exists($paths['plugin_editor_css'])) {
            // Fall back to plugin directory
            $editor_css_content = file_get_contents($paths['plugin_editor_css']);
            if ($editor_css_content) {
                wp_enqueue_style('wp-block-editor');
                wp_add_inline_style('wp-block-editor', $editor_css_content);
            }
        }
    }

    /**
     * Enqueue editor assets (React components only)
     */
    public function enqueue_editor_assets() {
        $paths = $this->get_css_paths();
        
        // Make sure WordPress loads all plugin-related scripts that our panel depends on
        wp_enqueue_script('wp-plugins');
        wp_enqueue_script('wp-edit-post');
        wp_enqueue_script('wp-element');
        wp_enqueue_script('wp-components');
        wp_enqueue_script('wp-block-editor');
        wp_enqueue_script('wp-hooks');
        wp_enqueue_script('wp-compose');
        wp_enqueue_script('wp-data');
        
        // Debug built file existence 
        $asset_file_path = LZA_CLASS_MANAGER_PATH . 'build/index.asset.php';
        $js_file_path = LZA_CLASS_MANAGER_PATH . 'build/index.js';
        
        if (!file_exists($asset_file_path)) {
            error_log('LZA Class Manager: Missing build/index.asset.php file');
            return;
        }
        
        if (!file_exists($js_file_path)) {
            error_log('LZA Class Manager: Missing build/index.js file');
            return;
        }
        
        // Load the build files from the React app 
        $asset_file = include($asset_file_path);
        
        // Add debugging info
        error_log('LZA Class Manager: Loading JS from ' . $js_file_path);
        error_log('LZA Class Manager: Dependencies ' . json_encode($asset_file['dependencies']));
        
        // Make sure all dependencies are included
        $dependencies = array_merge(
            $asset_file['dependencies'],
            array(
                'wp-plugins',
                'wp-element',
                'wp-blocks',
                'wp-components',
                'wp-editor',
                'wp-block-editor',
                'wp-hooks',
                'wp-compose',
                'wp-data'
            )
        );
        
        // Enqueue React app and dependencies
        wp_enqueue_script(
            'lza-class-manager',
            LZA_CLASS_MANAGER_URL . 'build/index.js',
            $dependencies,
            $asset_file['version'] . '-' . time(), // Force no-cache during development
            true
        );
        
        // Enqueue plugin UI styles (these don't need to be in iframe)
        wp_enqueue_style(
            'lza-plugin-styles',
            LZA_CLASS_MANAGER_URL . 'css/plugin-styles.css',
            array(),
            filemtime(LZA_CLASS_MANAGER_PATH . 'css/plugin-styles.css')
        );

        // Pass CSS classes to JavaScript
        $this->localize_class_data();
    }

    /**
     * Parse CSS file and pass class names to JavaScript
     */
    private function localize_class_data() {
        $paths = $this->get_css_paths();
        $css_file = file_exists($paths['custom_css_path']) ? $paths['custom_css_path'] : $paths['plugin_custom_css'];
        
        if (file_exists($css_file)) {
            $css_content = file_get_contents($css_file);
            $class_names = array();
            
            // Extract classes with a better regex
            if (preg_match_all('/\.([a-zA-Z0-9\-_]+)(?:\s*\{|\s*,|\s*\.)/', $css_content, $matches)) {
                // Clean up and filter class names
                if (isset($matches[1]) && is_array($matches[1])) {
                    $class_names = array_values(array_unique(array_filter($matches[1], function($name) {
                        return !empty($name) && is_string($name);
                    })));
                }
            }
            
            // Debug info
            if (is_admin() && WP_DEBUG) {
                error_log('LZA Class Manager - Available classes: ' . json_encode($class_names));
            }
            
            wp_localize_script('lza-class-manager', 'lzaClassManager', array(
                'availableClasses' => $class_names,
                'showSuggestions' => false
            ));
        }
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        // Change from add_menu_page to add_management_page (Tools menu)
        add_management_page(
            'LZA Class Manager',  // Page title
            'LZA Class Manager',         // Menu title
            'manage_options',      // Capability
            'lza-class-manager',   // Menu slug
            array($this, 'render_admin_page'), // Callback function
            20                     // Position in the menu
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'lza_class_manager_options',
            'lza_custom_css',
            array(
                'sanitize_callback' => array($this, 'sanitize_custom_css'),
                'default' => $this->get_default_css(),
            )
        );
    }
    
    /**
     * Sanitize CSS input and save to file
     */
    public function sanitize_custom_css($input) {
        if (!current_user_can('manage_options')) {
            return '';
        }
        
        // Get the file paths
        $paths = $this->get_css_paths();
        $css_file_path = $paths['custom_css_path'];
        $min_css_file_path = $paths['custom_css_min_path'];
        
        // Ensure the css directory exists
        $this->ensure_css_directories();
        
        // Make sure the file is writable
        if (!is_writable(dirname($css_file_path))) {
            add_settings_error(
                'lza_custom_css',
                'file_permission_error',
                'Could not save to CSS file. Uploads directory is not writable.',
                'error'
            );
            return $input;
        }
        
        // Save the original CSS content to file
        $result = file_put_contents($css_file_path, $input);
        
        if ($result === false) {
            add_settings_error(
                'lza_custom_css',
                'file_save_error',
                'Failed to save CSS file. Check file permissions.',
                'error'
            );
        } else {
            // Create minified version for frontend
            $minified_css = $this->minify_css($input);
            file_put_contents($min_css_file_path, $minified_css);
            
            // Generate editor-safe CSS after successful save
            $this->generate_editor_safe_css($input);
        }
        
        return $input;
    }
    
    /**
     * Simple CSS Minification
     * 
     * @param string $css The CSS to minify
     * @return string Minified CSS
     */
    private function minify_css($css) {
        // Store original length for comparison
        $original_length = strlen($css);
        
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove spaces after colons, semicolons, commas, brackets, etc.
        $css = preg_replace('/\s*([\{\};:,>+~])\s*/', '$1', $css);
        
        // Remove unnecessary spaces
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove all newlines, tabs, multiple spaces
        $css = str_replace(array("\r\n", "\r", "\n", "\t"), '', $css);
        
        // Remove spaces around brackets
        $css = str_replace('{ ', '{', $css);
        $css = str_replace(' }', '}', $css);
        $css = str_replace('; ', ';', $css);
        $css = str_replace(', ', ',', $css);
        $css = str_replace(' {', '{', $css);
        $css = str_replace('} ', '}', $css);
        $css = str_replace(': ', ':', $css);
        
        // Remove trailing semicolons
        $css = str_replace(';}', '}', $css);
        
        // Remove leading zeros from decimal values
        $css = preg_replace('/([^0-9])0+\.([0-9]+)/', '$1.$2', $css);
        
        // Remove zero units
        $css = str_replace(array('0px', '0em', '0rem', '0%'), '0', $css);
        
        // Replace multiple zeros
        $css = preg_replace('/\s0 0 0 0;/', ':0;', $css);
        $css = preg_replace('/\s0 0 0;/', ':0;', $css);
        $css = preg_replace('/\s0 0;/', ':0;', $css);
        
        // Replace hex colors with short notation when possible
        $css = preg_replace('/#([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3/i', '#$1$2$3', $css);
        
        // Single line, ultra-compressed version
        $minified_css = "/* Minified CSS */\n" . $css;
        
        // Only use minified version if it's actually smaller than the original
        if (strlen($minified_css) < $original_length) {
            return $minified_css;
        } else {
            // If the minified version is larger or equal, just return the cleaned original
            return "/* Optimized CSS */\n" . $css;
        }
    }

    /**
     * Generate editor-safe CSS from the custom CSS
     * Fix handling of media queries to properly include nested classes
     */
    private function generate_editor_safe_css($css_content) {
        $paths = $this->get_css_paths();
        $editor_css_path = $paths['editor_css_path'];
        
        // Start with a clean slate
        $editor_css = "/* Editor-safe classes - Generated automatically */\n\n";
        
        // Process regular classes first (outside media queries)
        $standard_classes = $this->extract_regular_classes($css_content);
        $editor_css .= $standard_classes;
        
        // Process media queries with their nested classes
        $media_query_css = $this->extract_media_query_classes($css_content);
        $editor_css .= $media_query_css;
        
        // Write the generated CSS to file
        file_put_contents($editor_css_path, $editor_css);
        
        // Log completion if in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LZA Class Manager: Generated editor-safe CSS in uploads directory');
            
            // Debug log the generated CSS content
            error_log('Editor CSS content: ' . substr($editor_css, 0, 500) . '...');
        }
        
        return true;
    }
    
    /**
     * Extract regular classes (not in media queries)
     * 
     * @param string $css_content Full CSS content
     * @return string Editor-safe CSS for regular classes
     */
    private function extract_regular_classes($css_content) {
        $output = '';
        
        // First, remove all media queries to avoid duplicates
        $css_without_media = preg_replace('/@media[^{]*\{[^}]*\}[^}]*\}/s', '', $css_content);
        
        // Now extract all remaining class selectors and their rules
        if (preg_match_all('/\.([a-zA-Z0-9_\-]+)(?:\s*,\s*\.(?:[a-zA-Z0-9_\-]+))*\s*\{([^}]+)\}/s', $css_without_media, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (isset($match[1]) && isset($match[2])) {
                    $class_name = $match[1];
                    $rules = $match[2];
                    
                    // Simply wrap with editor selectors, preserving the original rules
                    $output .= ".editor-styles-wrapper .{$class_name},\n";
                    $output .= ".block-editor-block-list__block.{$class_name} {\n";
                    $output .= $rules; // Keep the rules exactly as they are
                    $output .= "}\n\n";
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Extract classes from media queries
     * 
     * @param string $css_content Full CSS content
     * @return string Editor-safe CSS for media query classes
     */
    private function extract_media_query_classes($css_content) {
        $output = '';
        
        // Define a more robust pattern for matching media queries
        $pattern = '#@media\s+([^{]+)\s*{\s*((?:[^{}]|(?R))*)\s*}#s';
        
        if (!preg_match_all($pattern, $css_content, $media_matches, PREG_SET_ORDER)) {
            // If the complex pattern fails, try a simpler approach
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LZA Class Manager: Complex media query regex failed, trying simpler approach');
            }
            
            // Fallback to simple extraction using string functions
            preg_match_all('/@media[^{]*{/i', $css_content, $media_starts);
            
            if (!empty($media_starts[0])) {
                foreach ($media_starts[0] as $media_start) {
                    $start_pos = strpos($css_content, $media_start);
                    if ($start_pos === false) continue;
                    
                    $brace_count = 1;
                    $end_pos = $start_pos + strlen($media_start);
                    $max_pos = strlen($css_content);
                    
                    // Find the end of this media query by matching braces
                    while ($brace_count > 0 && $end_pos < $max_pos) {
                        $char = $css_content[$end_pos];
                        if ($char === '{') $brace_count++;
                        if ($char === '}') $brace_count--;
                        $end_pos++;
                    }
                    
                    if ($brace_count === 0) {
                        // Extract the complete media query
                        $media_query = substr($css_content, $start_pos, $end_pos - $start_pos);
                        
                        // Extract the condition part
                        $condition = trim(substr($media_start, 6, -1)); // Remove "@media" and "{"
                        
                        // Extract the content part (everything between the outer braces)
                        $content_start = strpos($media_query, '{') + 1;
                        $content_length = strrpos($media_query, '}') - $content_start;
                        $media_content = substr($media_query, $content_start, $content_length);
                        
                        // Start new media query block
                        $output .= "@media {$condition} {\n";
                        
                        // Extract and transform class rules inside this media query
                        if (preg_match_all('/\.([a-zA-Z0-9_\-]+)(?:\s*,\s*\.(?:[a-zA-Z0-9_\-]+))*\s*\{([^}]+)\}/s', 
                                         $media_content, $inner_matches, PREG_SET_ORDER)) {
                            foreach ($inner_matches as $inner_match) {
                                if (isset($inner_match[1]) && isset($inner_match[2])) {
                                    $class_name = $inner_match[1];
                                    $rules = $inner_match[2];
                                    
                                    // Editor-safe selectors for classes in media query
                                    $output .= "  .editor-styles-wrapper .{$class_name},\n";
                                    $output .= "  .block-editor-block-list__block.{$class_name} {\n";
                                    $output .= $rules; // Fixed: Actually output the rules
                                    $output .= "  }\n\n";
                                }
                            }
                        }
                        
                        // Close the media query
                        $output .= "}\n\n";
                    }
                }
            }
        } else {
            // Process matches from the complex regex pattern
            foreach ($media_matches as $media_match) {
                if (isset($media_match[1]) && isset($media_match[2])) {
                    $media_condition = trim($media_match[1]);
                    $media_content = $media_match[2];
                    
                    // Start new media query block
                    $output .= "@media {$media_condition} {\n";
                    
                    // Extract all class rules inside this media query
                    if (preg_match_all('/\.([a-zA-Z0-9_\-]+)(?:\s*,\s*\.(?:[a-zA-Z0-9_\-]+))*\s*\{([^}]+)\}/s', 
                                     $media_content, $inner_matches, PREG_SET_ORDER)) {
                        foreach ($inner_matches as $inner_match) {
                            if (isset($inner_match[1]) && isset($inner_match[2])) {
                                $class_name = $inner_match[1];
                                $rules = $inner_match[2];
                                
                                // Editor-safe selectors for classes in media query
                                $output .= "  .editor-styles-wrapper .{$class_name},\n";
                                $output .= "  .block-editor-block-list__block.{$class_name} {\n";
                                $output .= $rules;
                                $output .= "  }\n\n";
                            }
                        }
                    }
                    
                    // Close the media query
                    $output .= "}\n\n";
                }
            }
        }
        
        // If we still don't have any output, try a direct string extraction as a last resort
        if (empty($output)) {
            // ...existing fallback code...
        }
        
        return $output;
    }
    
    /**
     * Get default CSS content
     */
    private function get_default_css() {
        $paths = $this->get_css_paths();
        
        // First check in uploads directory
        if (file_exists($paths['custom_css_path'])) {
            return file_get_contents($paths['custom_css_path']);
        }
        
        // Then check in plugin directory
        if (file_exists($paths['plugin_custom_css'])) {
            return file_get_contents($paths['plugin_custom_css']);
        }
        
        // Default content focuses only on classes, not selectors
        return "/* Add your custom classes here */\n\n" .
               ".p-l {\n    padding: 1rem;\n}\n\n" .
               ".p-xl {\n    padding: 3rem;\n}\n\n" .
               ".bg-red {\n    background-color: red;\n}\n\n" .
               ".text-white {\n    color: white;\n}\n";
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        // Update the hook check for the Tools submenu page
        if ('tools_page_lza-class-manager' !== $hook) {
            return;
        }

        // Debug info
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LZA Class Manager: Loading admin scripts for hook ' . $hook);
        }
        
        // Get user's preferred theme or default to 'default'
        $preferred_theme = get_user_meta(get_current_user_id(), 'lza_editor_theme', true) ?: 'default';
        
        // List of available themes
        $available_themes = array(
            'default' => 'Light',
            'dracula' => 'Dark'
        );

        // Make sure we load all the required CodeMirror components
        wp_enqueue_style('wp-codemirror');
        $code_editor_settings = wp_enqueue_code_editor(array('type' => 'text/css'));
        
        // Force CSS Editor specific settings
        $settings = array(
            'type' => 'text/css',
            'codemirror' => array(
                'mode' => 'css',
                'lineNumbers' => true,
                'indentUnit' => 4,
                'tabSize' => 4,
                'indentWithTabs' => true,
                'lineWrapping' => true,
                'autoCloseBrackets' => true,
                'matchBrackets' => true,
                'styleActiveLine' => true,
                'gutters' => array('CodeMirror-linenumbers'),
                'theme' => $preferred_theme,
            ),
        );
        
        // Enqueue all available themes from our plugin directory
        foreach ($available_themes as $theme_key => $theme_name) {
            if ($theme_key !== 'default') {
                // Check if the theme file exists in our plugin
                $theme_file_path = LZA_CLASS_MANAGER_PATH . 'css/themes/' . $theme_key . '.css';
                $theme_file_url = LZA_CLASS_MANAGER_URL . 'css/themes/' . $theme_key . '.css';
                
                // Only try to enqueue if the file exists
                if (file_exists($theme_file_path)) {
                    wp_enqueue_style(
                        'codemirror-theme-' . $theme_key,
                        $theme_file_url,
                        array('wp-codemirror'),
                        LZA_CLASS_MANAGER_VERSION
                    );
                } else {
                    // If theme doesn't exist, try to get it from WordPress core
                    wp_enqueue_style(
                        'codemirror-theme-' . $theme_key,
                        includes_url('js/codemirror/theme/' . $theme_key . '.css'),
                        array('wp-codemirror'),
                        false
                    );
                }
            }
        }
        
        // Ensure all required scripts are loaded
        wp_enqueue_script('jquery');
        wp_enqueue_script('wp-codemirror');
        
        // Enqueue admin styles
        wp_enqueue_style(
            'lza-admin-styles',
            LZA_CLASS_MANAGER_URL . 'css/admin-styles.css',
            array('wp-codemirror'),
            LZA_CLASS_MANAGER_VERSION
        );
        
        // Enqueue admin script with editor settings
        wp_enqueue_script(
            'lza-admin-script',
            LZA_CLASS_MANAGER_URL . 'js/admin.js',
            array('jquery', 'wp-codemirror'),
            LZA_CLASS_MANAGER_VERSION,
            true
        );
        
        // Pass the settings and theme options to the script
        wp_localize_script('lza-admin-script', 'lzaEditorSettings', array(
            'settings' => $settings,
            'themes' => $available_themes,
            'currentTheme' => $preferred_theme,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lza-theme-switcher'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }
    
    /**
     * AJAX handler to save editor theme preference
     */
    public function save_editor_theme() {
        // Force debug output regardless of WP_DEBUG
        error_log('LZA Theme Save AJAX called - POST data: ' . json_encode($_POST));
        
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lza-theme-switcher')) {
            error_log('LZA Theme Save: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security verification failed'));
            return;
        }
        
        // Get the theme from POST
        $theme = isset($_POST['theme']) ? sanitize_text_field($_POST['theme']) : 'default';
        $user_id = get_current_user_id();
        
        // Save user preference
        $result = update_user_meta($user_id, 'lza_editor_theme', $theme);
        
        // Log the result
        error_log("LZA Theme Save: User ID $user_id, Theme '$theme', Result: " . 
                 ($result ? 'Success' : 'Failed (or no change)'));
        
        // Check if the value was actually set
        $saved_theme = get_user_meta($user_id, 'lza_editor_theme', true);
        error_log("LZA Theme Save: Verification - Saved theme for User $user_id is: '$saved_theme'");
        
        // Return success with theme info
        wp_send_json_success(array(
            'theme' => $theme,
            'saved' => $result !== false,
            'user_id' => $user_id,
            'stored_value' => $saved_theme,
            'message' => 'Theme preference processed'
        ));
    }
    
    /**
     * Get all CSS variables from theme.json
     */
    private function get_all_theme_json_css_variables() {
        $variables = array();
        
        // Get the compiled CSS from global stylesheet
        $css = wp_get_global_stylesheet();
        
        // Extract all CSS variable declarations using regex
        preg_match_all('/(--wp--[a-zA-Z0-9-]+)\s*:/', $css, $matches);
        
        if (!empty($matches[1])) {
            $variables = array_unique($matches[1]);
            sort($variables); // Sort variables alphabetically
        }
        
        return $variables;
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $css_content = $this->get_default_css();
        $success_message = false;
        $error_message = false;
        
        // Save the CSS if form was submitted
        if (isset($_POST['lza_custom_css']) && isset($_POST['lza_css_nonce'])) {
            if (wp_verify_nonce($_POST['lza_css_nonce'], 'lza_save_css')) {
                $css_content = wp_unslash($_POST['lza_custom_css']);
                
                // Save to option for backup
                update_option('lza_custom_css', $css_content);
                
                // Save to file
                $result = $this->sanitize_custom_css($css_content);
                
                // Check for errors
                $settings_errors = get_settings_errors('lza_custom_css');
                if (empty($settings_errors)) {
                    $paths = $this->get_css_paths();
                    $relative_path = str_replace(ABSPATH, '', $paths['custom_css_path']);
                    $success_message = 'CSS saved successfully to: ' . $relative_path;
                } else {
                    foreach ($settings_errors as $error) {
                        $error_message = $error['message'];
                    }
                }
            } else {
                $error_message = 'Security check failed. Please refresh the page and try again.';
            }
        }
        
        // Show messages
        if ($success_message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success_message) . '</p></div>';
        }
        if ($error_message) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
        }

        // Get CSS variables from theme.json
        $css_variables = $this->get_all_theme_json_css_variables();
        
        // Get file info - we'll use this later
        $paths = $this->get_css_paths();
        $min_css_file_path = $paths['custom_css_min_path'];
        $css_file_path = $paths['custom_css_path'];
        $file_info_html = '';
        
        if (file_exists($min_css_file_path) && file_exists($css_file_path)) {
            $min_size = filesize($min_css_file_path);
            $orig_size = filesize($css_file_path);
            $savings = $orig_size - $min_size;
            $percent = ($orig_size > 0) ? round(($savings / $orig_size) * 100, 1) : 0;
            
            if ($min_size < $orig_size) {
                $file_info_html .= '<p>
                    <span class="dashicons dashicons-media-archive"></span>
                    Minified file size: <strong>' . $this->format_file_size($min_size) . '</strong>
                    (Original: ' . $this->format_file_size($orig_size) . ') - 
                    <strong>' . $percent . '% reduction</strong>
                </p>';
                
                // Add storage location
                $file_info_html .= '<p>
                    <span class="dashicons dashicons-database"></span>
                    CSS files stored in: <code>' . 
                    esc_html(str_replace(ABSPATH, '', $paths['uploads_dir'])) . 
                    '</code>
                </p>';
            } else {
                $file_info_html .= '<p>
                    <span class="dashicons dashicons-warning"></span>
                    Minified file is not smaller than original. Using optimized version.
                </p>';
            }
        }
        ?>
        <div class="wrap">
            <h1>LZA Class Manager</h1>
            <p>Edit your custom CSS classes below. These classes will be available in the LZA Class Manager panel in the block editor.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('lza_save_css', 'lza_css_nonce'); ?>
                <div class="lza-editor-layout">
                    <div class="lza-css-editor-container">
                        <div class="lza-editor-header">
                            <h2>Custom Classes CSS</h2>
                            <div class="lza-editor-actions">
                                <button type="submit" class="button button-primary">Save Changes</button>
                            </div>
                        </div>
                        <textarea id="lza_custom_css" name="lza_custom_css" rows="20" class="large-text code"><?php echo esc_textarea($css_content); ?></textarea>
                    </div>
                    
                    <?php if (!empty($css_variables)): ?>
                    <div class="lza-variables-sidebar">
                        <div class="lza-variables-header">
                            <h3>Theme CSS Variables</h3>
                            <p class="description">Click a variable to insert it at the cursor position</p>
                        </div>
                        <div class="lza-variables-list">
                            <?php foreach ($css_variables as $variable): ?>
                                <div class="lza-css-variable" data-variable="<?php echo esc_attr($variable); ?>">
                                    <?php echo esc_html($variable); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($file_info_html)): ?>
                <!-- File information panel -->
                <div class="lza-file-info-panel">
                    <h3><span class="dashicons dashicons-info"></span> File Information</h3>
                    <?php echo $file_info_html; ?>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
        <!-- Debug section - only visible in WP_DEBUG mode -->
        <div class="lza-debug-section">
            <h3>Debug Tools</h3>
            <p>These tools are only visible in debug mode.</p>
            <div class="lza-debug-tools">
                <button type="button" id="lza-test-ajax" class="button">Test AJAX Connection</button>
                <button type="button" id="lza-test-theme-save" class="button">Test Theme Save (Default)</button>
                <span id="lza-ajax-result" style="margin-left: 10px; font-style: italic;"></span>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Test AJAX connection
            $('#lza-test-ajax').on('click', function() {
                $('#lza-ajax-result').text('Testing AJAX connection...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'lza_save_editor_theme',
                        theme: 'default',
                        nonce: '<?php echo wp_create_nonce('lza-theme-switcher'); ?>'
                    },
                    success: function(response) {
                        console.log('Test AJAX response:', response);
                        $('#lza-ajax-result').text('AJAX connected successfully: ' + JSON.stringify(response));
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX test error:', status, error);
                        $('#lza-ajax-result').text('AJAX error: ' + error);
                    }
                });
            });
            
            // Test theme save functionality
            $('#lza-test-theme-save').on('click', function() {
                $('#lza-ajax-result').text('Testing theme save...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'lza_save_editor_theme',
                        theme: 'default',
                        nonce: '<?php echo wp_create_nonce('lza-theme-switcher'); ?>'
                    },
                    success: function(response) {
                        var result = 'Theme save ' + (response.success ? 'successful' : 'failed');
                        if (response.data) {
                            result += ': ' + JSON.stringify(response.data);
                        }
                        $('#lza-ajax-result').text(result);
                    },
                    error: function(xhr, status, error) {
                        $('#lza-ajax-result').text('Theme save error: ' + error);
                    }
                });
            });
        });
        </script>
        <?php endif; ?>
        
        <!-- Fallback script to ensure CodeMirror initializes even if the main JS fails -->
        <script>
        jQuery(document).ready(function($) {
            // Wait a moment to see if our main JS initializes the editor
            setTimeout(function() {
                // Check if CodeMirror has been initialized
                var $editor = $('#lza_custom_css');
                var hasCMWrapper = $editor.siblings('.CodeMirror').length > 0;
                
                // If not initialized, try a fallback initialization
                if (!hasCMWrapper && typeof wp !== 'undefined' && wp.codeEditor) {
                    console.log('Using fallback editor initialization');
                    
                    // Basic initialization
                    var settings = {
                        codemirror: {
                            mode: 'css',
                            lineNumbers: true,
                            lineWrapping: true
                        }
                    };
                    
                    try {
                        wp.codeEditor.initialize($editor, settings);
                    } catch(e) {
                        console.error('Fallback initialization failed:', e);
                    }
                }
            }, 1000); // Check after 1 second
        });
        </script>
        
        <!-- Script for handling CSS variables - REPLACING previous implementation -->
        <script>
        jQuery(document).ready(function($) {
            // Make sure we don't double-bind click events
            $('.lza-css-variable').off('click').on('click', function() {
                var variable = $(this).data('variable');
                
                // Find CodeMirror instance directly
                var cmInstance = null;
                
                // Try to find the CodeMirror instance
                if (window.lzaCodeMirror) {
                    // Use the globally stored instance
                    cmInstance = window.lzaCodeMirror;
                } else {
                    // Fall back to searching for it
                    $('.CodeMirror').each(function() {
                        if (this.CodeMirror) {
                            cmInstance = this.CodeMirror;
                            return false; // Break the loop
                        }
                    });
                }
                
                // Insert at cursor position
                if (cmInstance) {
                    var doc = cmInstance.getDoc();
                    var cursor = doc.getCursor();
                    doc.replaceRange('var(' + variable + ')', cursor);
                    
                    // Focus the editor
                    cmInstance.focus();
                    
                    // Show visual feedback
                    $(this).addClass('inserted');
                    setTimeout(function() {
                        $('.lza-css-variable').removeClass('inserted');
                    }, 500);
                } else {
                    // Fallback - insert at textarea
                    var $textarea = $('#lza_custom_css');
                    var text = $textarea.val();
                    var pos = $textarea.prop('selectionStart');
                    var textToInsert = 'var(' + variable + ')';
                    
                    $textarea.val(
                        text.substring(0, pos) + 
                        textToInsert + 
                        text.substring(pos)
                    );
                    
                    // Set cursor position after inserted text
                    $textarea.prop('selectionStart', pos + textToInsert.length);
                    $textarea.prop('selectionEnd', pos + textToInsert.length);
                    $textarea.focus();
                }
            });
        });
        </script>
        
        <?php
    }
    
    /**
     * Format file size for display
     * 
     * @param int $size File size in bytes
     * @return string Formatted size with unit
     */
    private function format_file_size($size) {
        $units = array('B', 'KB', 'MB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}

// Initialize the plugin
$lza_class_manager = new LZA_Class_Manager();

// Run setup on activation
register_activation_hook(__FILE__, array($lza_class_manager, 'maybe_initialize_css_files'));