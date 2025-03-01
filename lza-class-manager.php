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
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        
        // Editor hooks
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        add_action('enqueue_block_assets', array($this, 'enqueue_block_editor_assets'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Register AJAX handler - add this here instead of inside admin_enqueue_scripts
        add_action('wp_ajax_lza_save_editor_theme', array($this, 'save_editor_theme'));
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_styles() {
        // Enqueue the user custom classes first
        wp_enqueue_style(
            'lza-custom-classes',
            LZA_CLASS_MANAGER_URL . 'css/custom-classes.css',
            array(),
            filemtime(LZA_CLASS_MANAGER_PATH . 'css/custom-classes.css')
        );
    }

    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets() {
        // Editor-only scripts
        $asset_file = include(LZA_CLASS_MANAGER_PATH . 'build/index.asset.php');
        
        // Enqueue plugin UI styles
        wp_enqueue_style(
            'lza-plugin-styles',
            LZA_CLASS_MANAGER_URL . 'css/plugin-styles.css',
            array(),
            filemtime(LZA_CLASS_MANAGER_PATH . 'css/plugin-styles.css')
        );
        
        wp_enqueue_script(
            'lza-class-manager',
            LZA_CLASS_MANAGER_URL . 'build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        // Pass CSS classes to JavaScript
        $this->localize_class_data();
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        // Enqueue custom classes
        wp_enqueue_style(
            'lza-custom-classes-editor',
            LZA_CLASS_MANAGER_URL . 'css/custom-classes.css',
            array(),
            filemtime(LZA_CLASS_MANAGER_PATH . 'css/custom-classes.css')
        );
        
        // Also enqueue editor-safe classes if they exist
        if (file_exists(LZA_CLASS_MANAGER_PATH . 'css/editor-safe-classes.css')) {
            wp_enqueue_style(
                'lza-editor-safe-classes',
                LZA_CLASS_MANAGER_URL . 'css/editor-safe-classes.css',
                array('lza-custom-classes-editor'),
                filemtime(LZA_CLASS_MANAGER_PATH . 'css/editor-safe-classes.css')
            );
        }
    }

    /**
     * Parse CSS file and pass class names to JavaScript
     */
    private function localize_class_data() {
        $css_content = file_get_contents(LZA_CLASS_MANAGER_PATH . 'css/custom-classes.css');
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
        
        // Get the file path
        $css_file_path = LZA_CLASS_MANAGER_PATH . 'css/custom-classes.css';
        
        // Ensure the css directory exists
        $css_dir = dirname($css_file_path);
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }
        
        // Make sure the file is writable
        if (!is_writable($css_file_path) && file_exists($css_file_path)) {
            // Try to make it writable
            @chmod($css_file_path, 0664);
            
            if (!is_writable($css_file_path)) {
                add_settings_error(
                    'lza_custom_css',
                    'file_permission_error',
                    'Could not save to CSS file. Check file permissions.',
                    'error'
                );
                return $input;
            }
        }
        
        // Save the CSS content to file
        $result = file_put_contents($css_file_path, $input);
        
        if ($result === false) {
            add_settings_error(
                'lza_custom_css',
                'file_save_error',
                'Failed to save CSS file. Check file permissions.',
                'error'
            );
        } else {
            // Generate editor-safe CSS after successful save
            $this->generate_editor_safe_css($input);
        }
        
        return $input;
    }
    
    /**
     * Generate editor-safe CSS from the custom CSS
     */
    private function generate_editor_safe_css($css_content) {
        // File path for the editor-safe CSS
        $editor_css_path = LZA_CLASS_MANAGER_PATH . 'css/editor-safe-classes.css';
        
        // Start with a clean slate
        $editor_css = "/* Editor-safe classes - Generated automatically */\n\n";
        
        // Extract class names and rules
        if (preg_match_all('/\.([a-zA-Z0-9_-]+)(?:\s*,\s*\.(?:[a-zA-Z0-9_-]+))*\s*{([^}]+)}/', $css_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Get the class name and rules
                $class_name = $match[1];
                $rules = $match[2];
                
                // Simply wrap with editor selectors, preserving the original rules exactly
                $editor_css .= ".editor-styles-wrapper .{$class_name},\n";
                $editor_css .= ".block-editor-block-list__block.{$class_name} {\n";
                $editor_css .= $rules; // Keep the rules exactly as they are
                $editor_css .= "}\n\n";
            }
        }
        
        // Handle media queries
        if (preg_match_all('/@media[^{]+{([^}]+)}/', $css_content, $media_matches)) {
            foreach ($media_matches[0] as $index => $media_query) {
                $media_content = $media_matches[1][$index];
                
                // Extract the media query condition
                preg_match('/@media([^{]+){/', $media_query, $condition_match);
                $condition = trim($condition_match[1]);
                
                // Start new media query block
                $editor_css .= "@media {$condition} {\n";
                
                // Find all class rules inside this media query
                if (preg_match_all('/\.([a-zA-Z0-9_-]+)(?:\s*,\s*\.(?:[a-zA-Z0-9_-]+))*\s*{([^}]+)}/', $media_content, $inner_matches, PREG_SET_ORDER)) {
                    foreach ($inner_matches as $inner_match) {
                        $class_name = $inner_match[1];
                        $rules = $inner_match[2];
                        
                        // Editor-safe selectors for classes in media query
                        $editor_css .= "  .editor-styles-wrapper .{$class_name},\n";
                        $editor_css .= "  .block-editor-block-list__block.{$class_name} {\n";
                        $editor_css .= $rules;
                        $editor_css .= "  }\n\n";
                    }
                }
                
                // Close the media query
                $editor_css .= "}\n\n";
            }
        }
        
        // Write the generated CSS to file
        file_put_contents($editor_css_path, $editor_css);
        
        // Log completion if in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LZA Class Manager: Generated editor-safe CSS');
        }
        
        return true;
    }
    
    /**
     * Get default CSS content
     */
    private function get_default_css() {
        $css_file_path = LZA_CLASS_MANAGER_PATH . 'css/custom-classes.css';
        
        if (file_exists($css_file_path)) {
            return file_get_contents($css_file_path);
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
                    $success_message = 'CSS saved successfully to file: css/custom-classes.css';
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
}

// Initialize the plugin
new LZA_Class_Manager();