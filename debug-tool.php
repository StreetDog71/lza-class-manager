<?php
/**
 * LZA Class Manager Debug Tool
 *
 * This file helps diagnose issues with WordPress CodeMirror integration.
 * To use, add ?debug_lza=1 to your admin URL.
 *
 * @package LZA\ClassManager
 */

declare(strict_types=1);
namespace LZA\ClassManager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for CodeMirror Debug Tools
 */
class CodeMirrorDebug {

	/**
	 * Initialize the debug tool
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'check_debug_flag' ) );
	}

	/**
	 * Check if debug was requested
	 *
	 * @return void
	 */
	public static function check_debug_flag(): void {
		if ( isset( $_GET['debug_lza'] ) && '1' === $_GET['debug_lza'] && current_user_can( 'manage_options' ) ) {
			add_action( 'admin_notices', array( self::class, 'show_debug_info' ) );
			add_action( 'admin_footer', array( self::class, 'add_debug_script' ) );
		}
	}

	/**
	 * Show debug information
	 *
	 * @return void
	 */
	public static function show_debug_info(): void {
		// Check if CodeMirror is available.
		$codemirror_available = class_exists( 'WP_Scripts' ) &&
							   wp_script_is( 'wp-codemirror', 'registered' );

		// Get the script registration.
		$scripts = wp_scripts();
		$codemirror_info = isset( $scripts->registered['wp-codemirror'] ) ?
			$scripts->registered['wp-codemirror'] : null;

		// Get standard test settings.
		$css_editor_test = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );

		?>
		<div class="notice notice-info">
			<h3>LZA Class Manager Debug Information</h3>

			<p><strong>WordPress Version:</strong> <?php echo esc_html( get_bloginfo( 'version' ) ); ?></p>
			<p><strong>CodeMirror Available:</strong> <?php echo $codemirror_available ? 'Yes' : 'No'; ?></p>

			<?php if ( $codemirror_info ) : ?>
			<p><strong>CodeMirror Source:</strong> <?php echo esc_html( $codemirror_info->src ); ?></p>
			<p><strong>CodeMirror Version:</strong> <?php echo isset( $codemirror_info->ver ) ? esc_html( $codemirror_info->ver ) : 'Unknown'; ?></p>
			<p><strong>CodeMirror Dependencies:</strong> <?php echo esc_html( implode( ', ', $codemirror_info->deps ) ); ?></p>
			<?php endif; ?>

			<p><strong>CSS Editor Test Results:</strong>
				<?php
				if ( is_array( $css_editor_test ) && ! empty( $css_editor_test ) ) {
					echo 'Success - CodeMirror settings returned';
				} else {
					echo 'Failed - CodeMirror settings not returned';
				}
				?>
			</p>

			<p><strong>Admin URL:</strong> <?php echo esc_html( admin_url( 'tools.php?page=lza-class-manager' ) ); ?></p>

			<p>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=lza-class-manager' ) ); ?>" class="button">Back to Class Manager</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Add debug JavaScript to test CodeMirror
	 *
	 * @return void
	 */
	public static function add_debug_script(): void {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			console.log('LZA Debug: WordPress object available:', typeof wp !== 'undefined');
			console.log('LZA Debug: CodeMirror object available:', typeof wp !== 'undefined' && typeof wp.CodeMirror !== 'undefined');
			console.log('LZA Debug: Code Editor available:', typeof wp !== 'undefined' && typeof wp.codeEditor !== 'undefined');

			if (typeof wp !== 'undefined' && typeof wp.codeEditor !== 'undefined') {
				// Test initializing a simple editor
				const testArea = document.createElement('textarea');
				testArea.id = 'lza-test-editor';
				testArea.textContent = '/* Test CSS */';
				document.body.appendChild(testArea);

				try {
					const testEditor = wp.codeEditor.initialize(testArea, {
						codemirror: {
							mode: 'css',
							lineNumbers: true
						}
					});
					console.log('LZA Debug: Test editor initialized successfully');
				} catch(e) {
					console.error('LZA Debug: Error initializing test editor:', e);
				}
			}
		});
		</script>
		<?php
	}
}

// Initialize debug tool.
// Using a function to avoid global namespace pollution.
add_action(
	'plugins_loaded',
	static function () {
		CodeMirrorDebug::init();
	}
);
