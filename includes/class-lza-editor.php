<?php
/**
 * Editor integration functionality
 */
class LZA_Editor {
    
    /**
     * CSS Processor instance
     *
     * @var LZA_CSS_Processor
     */
    private $css_processor;
    
    /**
     * Constructor
     *
     * @param LZA_CSS_Processor $css_processor CSS processor instance
     */
    public function __construct($css_processor) {
        $this->css_processor = $css_processor;
    }
    
    /**
     * Initialize editor hooks
     */
    public function init() {
        // Proper way to add styles that will be included in the iframe
        add_action('enqueue_block_assets', array($this, 'enqueue_block_editor_styles'));
        
        // Editor hooks - load React components
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
    }
    
    /**
     * Enqueue styles that need to be included in the editor iframe
     */
    public function enqueue_block_editor_styles() {
        // Only load in editor context
        if (!is_admin()) {
            return;
        }
        
        $paths = $this->css_processor->get_css_paths();
        
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
     * Enqueue editor assets (React components)
     */
    public function enqueue_editor_assets() {
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
     * Pass CSS class data to JavaScript
     */
    private function localize_class_data() {
        $paths = $this->css_processor->get_css_paths();
        $css_file = file_exists($paths['custom_css_path']) ? $paths['custom_css_path'] : $paths['plugin_custom_css'];
        
        if (file_exists($css_file)) {
            $css_content = file_get_contents($css_file);
            $class_names = $this->css_processor->extract_class_names($css_content);
            
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
}
