<?php
/**
 * Core class for LZA Class Manager
 * 
 * Responsible for initializing and coordinating all plugin components
 */
class LZA_Core {
    /**
     * CSS Processor instance
     *
     * @var LZA_CSS_Processor
     */
    private $css_processor;
    
    /**
     * Admin instance
     *
     * @var LZA_Admin
     */
    private $admin;
    
    /**
     * Editor instance
     *
     * @var LZA_Editor
     */
    private $editor;
    
    /**
     * Frontend instance
     *
     * @var LZA_Frontend
     */
    private $frontend;
    
    /**
     * Initialize the plugin components
     */
    public function init() {
        // Initialize CSS processor first
        $this->css_processor = new LZA_CSS_Processor();
        
        // Initialize other components and pass the CSS processor
        $this->admin = new LZA_Admin($this->css_processor);
        $this->editor = new LZA_Editor($this->css_processor);
        $this->frontend = new LZA_Frontend($this->css_processor);
        
        // Initialize each component
        $this->admin->init();
        $this->editor->init();
        $this->frontend->init();
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
