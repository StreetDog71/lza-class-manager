<?php
/**
 * Core class that initializes and coordinates all other components
 */
class LZA_Core {
    
    /**
     * CSS Processor instance
     *
     * @var LZA_CSS_Processor
     */
    private $css_processor;
    
    /**
     * Admin interface instance
     *
     * @var LZA_Admin
     */
    private $admin;
    
    /**
     * Editor integration instance
     *
     * @var LZA_Editor
     */
    private $editor;
    
    /**
     * Frontend styles instance
     *
     * @var LZA_Frontend
     */
    private $frontend;

    /**
     * Initialize the plugin core
     */
    public function init() {
        // Create component instances
        $this->css_processor = new LZA_CSS_Processor();
        $this->admin = new LZA_Admin($this->css_processor);
        $this->editor = new LZA_Editor($this->css_processor);
        $this->frontend = new LZA_Frontend($this->css_processor);

        // Set up core hooks
        add_action('init', array($this, 'setup'));
    }

    /**
     * Setup core plugin functionality
     */
    public function setup() {
        // Make sure CSS directories exist
        $this->css_processor->ensure_css_directories();
        
        // Initialize each component
        $this->admin->init();
        $this->editor->init();
        $this->frontend->init();
        
        // Check if we need to initialize CSS files
        add_action('admin_init', array($this->css_processor, 'maybe_initialize_css_files'));
    }

    /**
     * Get path utility function
     *
     * @param string $path Relative path to get
     * @return string Absolute path
     */
    public static function get_path($path = '') {
        return LZA_CLASS_MANAGER_PATH . ltrim($path, '/');
    }

    /**
     * Get URL utility function
     *
     * @param string $path Relative path to get
     * @return string Full URL
     */
    public static function get_url($path = '') {
        return LZA_CLASS_MANAGER_URL . ltrim($path, '/');
    }
}
