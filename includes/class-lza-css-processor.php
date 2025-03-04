<?php
/**
 * CSS processing functionality
 */
class LZA_CSS_Processor {
	/**
	 * CSS file definitions - unified into a single property
	 *
	 * @var array<string, array<string, string>>
	 */
	private $css_files = array(
		'source_files' => array(
			'custom' => array(
				'source' => 'css/custom-classes.css',
				'dest' => 'lza-css/custom-classes.css',
				'dest_min' => 'lza-css/custom-classes.min.css',
			),
			'editor' => array(
				'source' => 'css/editor-safe-classes.css',
				'dest' => 'lza-css/editor-safe-classes.css',
			),
		),
	);

	/**
	 * Get CSS file paths - central function to determine paths for all CSS files
	 *
	 * @return array Array of CSS file paths and URLs
	 */
	public function get_css_paths() {
		// Get WordPress uploads directory info.
		$upload_dir = wp_upload_dir();

		// Define the plugin's CSS folder in uploads.
		$css_folder = 'lza-css';

		// Create the paths.
		$uploads_path = trailingslashit( $upload_dir['basedir'] ) . $css_folder;
		$uploads_url = trailingslashit( $upload_dir['baseurl'] ) . $css_folder;

		// Return array of all important paths and URLs.
		return array(
			'uploads_dir' => $uploads_path,
			'uploads_url' => $uploads_url,
			'custom_css_path' => trailingslashit( $uploads_path ) . 'custom-classes.css',
			'custom_css_url' => trailingslashit( $uploads_url ) . 'custom-classes.css',
			'custom_css_min_path' => trailingslashit( $uploads_path ) . 'custom-classes.min.css',
			'custom_css_min_url' => trailingslashit( $uploads_url ) . 'custom-classes.min.css',
			'root_vars_path' => trailingslashit( $uploads_path ) . 'root-vars.css',
			'root_vars_url' => trailingslashit( $uploads_url ) . 'root-vars.css',
			'editor_css_path' => trailingslashit( $uploads_path ) . 'editor-safe-classes.css',
			'editor_css_url' => trailingslashit( $uploads_url ) . 'editor-safe-classes.css',
			'plugin_custom_css' => LZA_CLASS_MANAGER_PATH . 'css/custom-classes.css',
			'plugin_editor_css' => LZA_CLASS_MANAGER_PATH . 'css/editor-safe-classes.css',
		);
	}

	/**
	 * Ensure CSS directories exist and are writable
	 *
	 * @return boolean True if directories are ready, false otherwise
	 */
	public function ensure_css_directories() {
		$paths = $this->get_css_paths();

		// Create the uploads CSS directory if it doesn't exist
		if ( ! file_exists( $paths['uploads_dir'] ) ) {
			if ( ! wp_mkdir_p( $paths['uploads_dir'] ) ) {
				error_log( 'LZA Class Manager: Failed to create directory ' . $paths['uploads_dir'] );
				$this->add_admin_notice( 'error', sprintf( 
					/* translators: %s: Directory path */
					__( 'LZA Class Manager could not create the CSS directory: %s. Please check your file permissions.', 'lza-class-manager' ), 
					'<code>' . esc_html( $paths['uploads_dir'] ) . '</code>'
				));
				return false;
			}

			// Add an index.php file for security
			file_put_contents( trailingslashit( $paths['uploads_dir'] ) . 'index.php', "<?php\n// Silence is golden." );
		}

		// Check if the directory is writable
		if ( ! is_writable( $paths['uploads_dir'] ) ) {
			error_log( 'LZA Class Manager: Directory is not writable - ' . $paths['uploads_dir'] );
			$this->add_admin_notice( 'error', sprintf(
				/* translators: %s: Directory path */
				__( 'LZA Class Manager cannot write to the CSS directory: %s. Please set the appropriate permissions (755 or 775) on this directory.', 'lza-class-manager' ),
				'<code>' . esc_html( $paths['uploads_dir'] ) . '</code>'
			));
			return false;
		}

		return true;
	}

	/**
	 * Add admin notice
	 *
	 * @param string $type    Notice type (error, warning, success, info)
	 * @param string $message Notice message
	 * @return void
	 */
	private function add_admin_notice( $type, $message ) {
		add_action( 'admin_notices', function() use ( $type, $message ) {
			?>
			<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
				<p><?php echo wp_kses_post( $message ); ?></p>
			</div>
			<?php
		});
	}

	/**
	 * Check if we need to initialize the CSS files on plugin activation or new installation
	 * Creates files in upload directory based on plugin source files
	 *
	 * @return bool Success status
	 */
	public function maybe_initialize_css_files() {
		$paths = $this->get_css_paths();

		// Make sure the directory exists
		if ( ! $this->ensure_css_directories() ) {
			error_log( 'LZA Class Manager: Failed to create CSS directory' );
			return false;
		}

		$success = true;

		// Process the custom CSS file
		if ( file_exists( $paths['plugin_custom_css'] ) ) {
			$css_content = file_get_contents( $paths['plugin_custom_css'] );

			// Extract root variables before creating any files
			$root_vars = $this->extract_root_variables( $css_content );

			// Save the main CSS file (including root vars)
			if ( ! $this->create_file( $paths['custom_css_path'], $css_content ) ) {
				error_log( 'LZA Class Manager: Failed to create file ' . $paths['custom_css_path'] );
				$success = false;
			}

			// Create minified version (WITHOUT root vars)
			$css_without_root = $this->remove_root_variables( $css_content );
			$minified_css = $this->minify_css( $css_without_root );
			if ( ! $this->create_file( $paths['custom_css_min_path'], $minified_css ) ) {
				error_log( 'LZA Class Manager: Failed to create file ' . $paths['custom_css_min_path'] );
				$success = false;
			}

			// Save root variables to separate file if they exist
			if ( ! empty( $root_vars ) ) {
				if ( ! $this->create_file( $paths['root_vars_path'], $root_vars ) ) {
					error_log( 'LZA Class Manager: Failed to create file ' . $paths['root_vars_path'] );
					$success = false;
				}
			}
		} else {
			error_log( 'LZA Class Manager: Source file not found - ' . $paths['plugin_custom_css'] );
			$success = false;
		}

		// Process the editor-safe CSS file
		if ( file_exists( $paths['plugin_editor_css'] ) ) {
			$editor_css_content = file_get_contents( $paths['plugin_editor_css'] );
			if ( ! $this->create_file( $paths['editor_css_path'], $editor_css_content ) ) {
				error_log( 'LZA Class Manager: Failed to create file ' . $paths['editor_css_path'] );
				$success = false;
			}
		} else {
			// If no source editor CSS file exists, generate it from custom CSS
			if ( file_exists( $paths['plugin_custom_css'] ) ) {
				$css_content = file_get_contents( $paths['plugin_custom_css'] );
				$this->generate_editor_safe_css( $css_content );
			} else {
				error_log( 'LZA Class Manager: Source editor file not found - ' . $paths['plugin_editor_css'] );
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Process and save CSS input
	 */
	public function process_css( $input ) {
		if ( empty( $input ) ) {
			return false;
		}

		// Get the file paths
		$paths = $this->get_css_paths();
		$css_file_path = $paths['custom_css_path'];
		$min_css_file_path = $paths['custom_css_min_path'];
		$root_vars_path = $paths['root_vars_path'];

		// Ensure the css directory exists
		$this->ensure_css_directories();

		// Make sure the directory is writable
		if ( ! is_writable( dirname( $css_file_path ) ) ) {
			return false;
		}

		// Extract root variables and save to separate file
		$root_vars = $this->extract_root_variables( $input );

		// Save the complete CSS content to main file (including root variables)
		$result = file_put_contents( $css_file_path, $input );

		if ( false === $result ) {
			return false;
		}

		// Save root variables to a separate file if they exist
		if ( ! empty( $root_vars ) ) {
			file_put_contents( $root_vars_path, $root_vars );
		}

		// Create minified version for frontend (EXCLUDING root vars)
		$css_without_root = $this->remove_root_variables( $input );
		$minified_css = $this->minify_css( $css_without_root );
		file_put_contents( $min_css_file_path, $minified_css );

		// Generate editor-safe CSS after successful save
		$this->generate_editor_safe_css( $input );

		return true;
	}

	/**
	 * Get default CSS content
	 */
	public function get_default_css() {
		$paths = $this->get_css_paths();

		// First check in uploads directory
		if ( file_exists( $paths['custom_css_path'] ) ) {
			$css = file_get_contents( $paths['custom_css_path'] );

			// If root vars exist in a separate file but not in the main CSS file,
			// re-integrate them for display in the editor
			if ( ! preg_match( '/:root\s*{/s', $css ) && file_exists( $paths['root_vars_path'] ) ) {
				$root_vars = file_get_contents( $paths['root_vars_path'] );
				if ( ! empty( $root_vars ) ) {
					$css = $root_vars . "\n\n" . $css;
				}
			}

			return $css;
		}

		// Then check in plugin directory
		if ( file_exists( $paths['plugin_custom_css'] ) ) {
			return file_get_contents( $paths['plugin_custom_css'] );
		}

		// Default content focuses only on classes, not selectors
		return "/* Add your custom classes here */\n\n" .
			   ".p-l {\n    padding: 1rem;\n}\n\n" .
			   ".p-xl {\n    padding: 3rem;\n}\n\n" .
			   ".bg-red {\n    background-color: red;\n}\n\n" .
			   ".text-white {\n    color: white;\n}\n";
	}

	/**
	 * Remove :root variables from CSS content
	 *
	 * @param string $css_content Full CSS content
	 * @return string CSS content without root variables
	 */
	public function remove_root_variables( $css_content ) {
		// Remove :root block completely for minification
		return preg_replace( '/:root\s*{[^}]+}\s*/s', '', $css_content );
	}

	/**
	 * Improved CSS Minification with better handling of CSS functions
	 *
	 * @param string $css The CSS to minify (already without :root variables)
	 * @return string Minified CSS
	 */
	public function minify_css( $css ) {
		// Store original length for comparison
		$original_length = strlen( $css );

		// Remove comments
		$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );

		// Create a function to protect CSS function expressions from minification
		$functions_to_protect = array(
			// CSS functions that should keep their internal spaces and structure
			'var',
			'calc',
			'clamp',
			'min',
			'max',
			'env',
			'cubic-bezier',
			'linear-gradient',
			'radial-gradient',
			'repeating-linear-gradient',
			'repeating-radial-gradient',
			'conic-gradient',
			'url',
		);

		// Replace function contents with placeholders before minification
		$pattern = '/(' . implode( '|', $functions_to_protect ) . ')\s*\(\s*([^()]*(?:\([^()]*\)[^()]*)*)\s*\)/i';
		$placeholders = array();
		$placeholder_index = 0;

		$css = preg_replace_callback(
			$pattern,
			static function ( $matches ) use ( &$placeholders, &$placeholder_index ) {
				$function_name = $matches[1];
				$function_content = $matches[2];
				$full_function = $function_name . '(' . $function_content . ')';

				// Create a unique placeholder
				$placeholder = "___FUNCTION_PLACEHOLDER_{$placeholder_index}___";
				$placeholder_index++;

				// Store the mapping
				$placeholders[ $placeholder ] = $full_function;

				return $placeholder;
			},
			$css
		);

		// Now proceed with normal minification since function contents are protected

		// Remove spaces around selectors, properties, and values
		$css = preg_replace( '/\s*([\{\};:,>+~])\s*/', '$1', $css );
		$css = preg_replace( '/\s+/', ' ', $css );

		// Remove spaces around brackets
		$css = str_replace( '{ ', '{', $css );
		$css = str_replace( ' }', '}', $css );
		$css = str_replace( '; ', ';', $css );
		$css = str_replace( ', ', ',', $css );
		$css = str_replace( ' {', '{', $css );
		$css = str_replace( '} ', '}', $css );
		$css = str_replace( ': ', ':', $css );

		// Remove trailing semicolons
		$css = str_replace( ';}', '}', $css );

		// Remove leading zeros from decimal values
		$css = preg_replace( '/([^0-9])0+\.([0-9]+)/', '$1.$2', $css );

		// Remove zero units
		$css = str_replace( array( '0px', '0em', '0rem', '0%' ), '0', $css );

		// Replace hex colors with short notation when possible
		$css = preg_replace( '/#([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3/i', '#$1$2$3', $css );

		// Restore all function placeholders
		foreach ( $placeholders as $placeholder => $original ) {
			$css = str_replace( $placeholder, $original, $css );
		}

		// Prepend a comment
		$minified_css = $css;

		// Only use the minified version if it's actually smaller
		if ( strlen( $minified_css ) < $original_length ) {
			return $minified_css;
		} else {
			return $css;
		}
	}

	/**
	 * Generate editor-safe CSS from the custom CSS
	 * Excludes media queries to prevent responsive styles from affecting the editor
	 * Now also handles root variables
	 */
	public function generate_editor_safe_css( $css_content ) {
		$paths = $this->get_css_paths();
		$editor_css_path = $paths['editor_css_path'];

		// Start with a clean slate
		$editor_css = '';

		// First, extract any :root variables and add them directly
		$root_variables = $this->extract_root_variables( $css_content );
		$editor_css .= $root_variables;

		// Process regular classes (outside media queries)
		$standard_classes = $this->extract_regular_classes( $css_content );
		$editor_css .= $standard_classes;

		 // Removed: Media query classes extraction
		 // This is intentional to keep editor styles simpler and avoid responsive styles in admin

		// Write the generated CSS to file
		file_put_contents( $editor_css_path, $editor_css );

		// Log completion if in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'LZA Class Manager: Generated editor-safe CSS in uploads directory' );
			error_log( 'LZA Class Manager: Root variables: ' . substr( $root_variables, 0, 200 ) . '...' );
			error_log( 'LZA Class Manager: Editor CSS content: ' . substr( $editor_css, 0, 500 ) . '...' );
		}

		return true;
	}

	/**
	 * Extract :root CSS variables
	 *
	 * @param string $css_content Full CSS content
	 * @return string CSS with root variables
	 */
	public function extract_root_variables( $css_content ) {
		$output = '';

		// Match the entire :root block
		if ( preg_match( '/:root\s*{([^}]+)}/s', $css_content, $match ) ) {
			// Keep the :root block exactly as is in original CSS
			$output .= ":root {\n" . $match[1] . "}\n\n";
		}

		return $output;
	}

	/**
	 * Extract regular classes (not in media queries)
	 *
	 * @param string $css_content Full CSS content
	 * @return string Editor-safe CSS for regular classes
	 */
	public function extract_regular_classes( $css_content ) {
		// Step 1: Remove comments to avoid confusion
		$css_content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css_content);
		
		// Step 2: Remove :root blocks which we handle separately
		$css_without_root = preg_replace('/:root\s*{[^}]+}/s', '', $css_content);
		
		// Step 3: Collect ALL media query blocks for complete removal
		$media_queries = array();
		$offset = 0;
		
		while (preg_match('/@media[^{]*{/i', $css_without_root, $matches, PREG_OFFSET_CAPTURE, $offset)) {
			$media_start_pos = $matches[0][1];
			$media_start = $matches[0][0];
			
			// Find the matching closing brace by counting opening and closing braces
			$brace_count = 1;
			$pos = $media_start_pos + strlen($media_start);
			$media_length = 0;
			
			while ($brace_count > 0 && $pos < strlen($css_without_root)) {
				$char = $css_without_root[$pos];
				if ($char === '{') {
					$brace_count++;
				} elseif ($char === '}') {
					$brace_count--;
				}
				$pos++;
				$media_length++;
				
				// Safety check to avoid infinite loops
				if ($media_length > 100000) {
					break;
				}
			}
			
			// Store the complete media query
			$media_query = substr($css_without_root, $media_start_pos, $media_length + strlen($media_start));
			$media_queries[] = $media_query;
			
			// Move past this media query for the next iteration
			$offset = $media_start_pos + $media_length + 1;
		}
		
		// Step 4: Extract classes from media queries to exclude them
		$media_query_classes = array();
		
		foreach ($media_queries as $media_query) {
			if (preg_match_all('/\.([a-zA-Z0-9_\-]+)(?:\s*,|\s*\{|\s*\.)/s', $media_query, $matches)) {
				if (isset($matches[1]) && is_array($matches[1])) {
					$media_query_classes = array_merge($media_query_classes, $matches[1]);
				}
			}
		}
		
		// Make sure class names are unique
		$media_query_classes = array_unique($media_query_classes);
		
		// Step 5: Remove all media queries from CSS for processing regular classes
		$css_without_media = $css_without_root;
		foreach ($media_queries as $media_query) {
			$css_without_media = str_replace($media_query, '', $css_without_media);
		}
		
		// Log media query classes we found for debugging
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('LZA Class Manager: Found ' . count($media_query_classes) . ' classes in media queries: ' . 
				implode(', ', array_slice($media_query_classes, 0, 30)) . (count($media_query_classes) > 30 ? '...' : ''));
		}
		
		// Step 6: Extract regular classes and build output
		$output = '';
		
		if (preg_match_all('/\.([a-zA-Z0-9_\-]+)(?:\s*,\s*\.(?:[a-zA-Z0-9_\-]+))*\s*\{([^}]+)\}/s',
			$css_without_media,
			$matches,
			PREG_SET_ORDER
		)) {
			foreach ($matches as $match) {
				if (isset($match[1]) && isset($match[2])) {
					$class_name = $match[1];
					$rules = $match[2];
					
					// Skip this class if it was found inside a media query
					if (in_array($class_name, $media_query_classes, true)) {
						if (defined('WP_DEBUG') && WP_DEBUG) {
							error_log('LZA Class Manager: Skipping media query class: ' . $class_name);
						}
						continue;
					}
					
					// Add to editor-safe CSS output
					$output .= ".editor-styles-wrapper .{$class_name},\n";
					$output .= ".block-editor-block-list__block.{$class_name} {\n";
					$output .= $rules;
					$output .= "}\n\n";
				}
			}
		}
		
		return $output;
	}

	/**
	 * Extract class names from CSS content
	 *
	 * @param string $css_content CSS content
	 * @return array Array of class names
	 */
	public function extract_class_names( $css_content ) {
		$class_names = array();

		// Extract classes with a better regex
		if ( preg_match_all( '/\.([a-zA-Z0-9\-_]+)(?:\s*\{|\s*,|\s*\.)/', $css_content, $matches ) ) {
			// Clean up and filter class names
			if ( isset( $matches[1] ) && is_array( $matches[1] ) ) {
				$class_names = array_values(
					array_unique(
						array_filter(
							$matches[1],
							static function ( $name ) {
								return ! empty( $name ) && is_string( $name );
							}
						)
					)
				);
			}
		}

		return $class_names;
	}

	/**
	 * Format file size for display
	 *
	 * @param int $size File size in bytes
	 * @return string Formatted size with unit
	 */
	public function format_file_size( $size ) {
		$units = array( 'B', 'KB', 'MB' );
		$power = $size > 0 ? floor( log( $size, 1024 ) ) : 0;
		return number_format( $size / pow( 1024, $power ), 2 ) . ' ' . $units[ $power ];
	}

	/**
	 * Get file information HTML for display
	 *
	 * @return string HTML for file information panel
	 */
	public function get_file_info_html() {
		$paths = $this->get_css_paths();
		$min_css_file_path = $paths['custom_css_min_path'];
		$css_file_path = $paths['custom_css_path'];
		$root_vars_path = $paths['root_vars_path'];
		$file_info_html = '';

		// Build file information display
		if ( file_exists( $min_css_file_path ) && file_exists( $css_file_path ) ) {
			$min_size = filesize( $min_css_file_path );
			$orig_size = filesize( $css_file_path );
			$vars_size = file_exists( $root_vars_path ) ? filesize( $root_vars_path ) : 0;

			$savings = $orig_size - $min_size;
			$percent = ( $orig_size > 0 ) ? round( ( $savings / $orig_size ) * 100, 1 ) : 0;

			// Show file sizes
			if ( $min_size < $orig_size ) {
				$file_info_html .= '<p>
                    <span class="dashicons dashicons-media-archive"></span>
                    Minified classes: <strong>' . $this->format_file_size( $min_size ) . '</strong>
                    (Original: ' . $this->format_file_size( $orig_size ) . ') -
                    <strong>' . $percent . '% reduction</strong>
                </p>';
			} else {
				$file_info_html .= '<p>
                    <span class="dashicons dashicons-warning"></span>
                    Minified file is not smaller than original. Using optimized version.
                </p>';
			}

			// Add root variables file info if it exists
			if ( file_exists( $root_vars_path ) && $vars_size > 0 ) {
				$file_info_html .= '<p>
                    <span class="dashicons dashicons-admin-appearance"></span>
                    Root variables: <strong>' . $this->format_file_size( $vars_size ) . '</strong>
                    <small>(kept separate for better performance)</small>
                </p>';
			}

			// Add storage location
			$file_info_html .= '<p>
                <span class="dashicons dashicons-database"></span>
                CSS files stored in: <code>' .
				esc_html( str_replace( ABSPATH, '', $paths['uploads_dir'] ) ) .
				'</code>
            </p>';
		}

		return $file_info_html;
	}

	/**
	 * Creates a file with the given content
	 *
	 * @param string $path File path
	 * @param string $content File content
	 * @return bool True on success, false on failure
	 */
	private function create_file( $path, $content ) {
		global $wp_filesystem;

		// Initialize the WP filesystem if needed
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Try to use WP_Filesystem first
		if ( $wp_filesystem ) {
			return $wp_filesystem->put_contents( $path, $content, FS_CHMOD_FILE );
		} else {
			// Fallback to direct PHP file operations
			$file = @fopen( $path, 'wb' );
			if ( $file ) {
				$result = @fwrite( $file, $content );
				@fclose( $file );
				return false !== $result;
			}
			return false;
		}
	}

	/**
	 * Gets the URL for a CSS file
	 *
	 * @param string $key The file key (main, utilities, components)
	 * @return string The URL to the CSS file
	 */
	public function get_css_url( $key ) {
		if ( isset( $this->css_files['legacy'][ $key ] ) ) {
			$upload_dir = wp_upload_dir();
			return $upload_dir['baseurl'] . '/' . $this->css_files['legacy'][ $key ];
		}

		return '';
	}

	/**
	 * Gets the filesystem path for a CSS file
	 *
	 * @param string $key The file key (main, utilities, components)
	 * @return string The path to the CSS file
	 */
	public function get_css_path( $key ) {
		if ( isset( $this->css_files['legacy'][ $key ] ) ) {
			$upload_dir = wp_upload_dir();
			return $upload_dir['basedir'] . '/' . $this->css_files['legacy'][ $key ];
		}

		return '';
	}
}
