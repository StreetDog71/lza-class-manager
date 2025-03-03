<?php
/**
 * LZA Class Manager Diagnostics
 *
 * This file helps diagnose issues with the code editor.
 * Access it via: /wp-admin/tools.php?page=lza-class-manager&diagnostics=1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for diagnostic tools
 */
class LZA_Diagnostics {
	/**
	 * Run diagnostics
	 */
	public static function run() {
		// Only run if explicitly requested and user has permissions
		if ( ! isset( $_GET['diagnostics'] ) || 1 != $_GET['diagnostics'] || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Basic plugin info
		$info = array(
			'Plugin Path' => LZA_CLASS_MANAGER_PATH,
			'Plugin URL' => LZA_CLASS_MANAGER_URL,
			'Plugin Version' => LZA_CLASS_MANAGER_VERSION,
			'WordPress Version' => get_bloginfo( 'version' ),
			'PHP Version' => phpversion(),
		);

		// Check for critical files
		$files_to_check = array(
			'Plugin Main File' => LZA_CLASS_MANAGER_PATH . 'lza-class-manager.php',
			'Admin JS' => LZA_CLASS_MANAGER_PATH . 'js/admin.js',
			'Admin CSS' => LZA_CLASS_MANAGER_PATH . 'css/admin-styles.css',
			'Theme: Dracula' => LZA_CLASS_MANAGER_PATH . 'css/themes/dracula.css',
		);

		$file_status = array();
		foreach ( $files_to_check as $label => $path ) {
			$file_status[ $label ] = file_exists( $path ) ? 'Exists' : 'Missing';
		}

		// Check user preferences
		$user_id = get_current_user_id();
		$editor_theme = get_user_meta( $user_id, 'lza_editor_theme', true );

		$user_info = array(
			'User ID' => $user_id,
			'Editor Theme Preference' => $editor_theme ? $editor_theme : 'Not set (will use default)',
		);

		// Test WordPress CodeMirror availability
		$codemirror_test = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		$codemirror_status = is_array( $codemirror_test ) && ! empty( $codemirror_test ) ?
			'Available' : 'Not available (check if WordPress has CodeMirror)';

		// Display the diagnostics
		self::display_diagnostics( $info, $file_status, $user_info, $codemirror_status, $codemirror_test );

		// Don't continue with the regular page after showing diagnostics
		exit;
	}

	/**
	 * Display diagnostic information
	 */
	private static function display_diagnostics( $info, $file_status, $user_info, $codemirror_status, $codemirror_test ) {
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<title>LZA Class Manager Diagnostics</title>
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
					max-width: 800px;
					margin: 2rem auto;
					padding: 0 1rem;
					color: #333;
					line-height: 1.5;
				}
				h1 {
					color: #23282d;
					border-bottom: 1px solid #eee;
					padding-bottom: 0.5rem;
				}
				section {
					margin: 2rem 0;
				}
				h2 {
					font-size: 1.4rem;
					color: #23282d;
					margin-bottom: 1rem;
				}
				table {
					width: 100%;
					border-collapse: collapse;
				}
				th, td {
					text-align: left;
					padding: 0.5rem;
					border-bottom: 1px solid #eee;
				}
				pre {
					background: #f6f6f6;
					padding: 1rem;
					border: 1px solid #ddd;
					overflow: auto;
					max-height: 400px;
				}
				.success {
					color: #46b450;
				}
				.error {
					color: #dc3232;
				}
				.back-link {
					display: inline-block;
					margin-top: 1rem;
					background: #0073aa;
					color: white;
					padding: 0.5rem 1rem;
					text-decoration: none;
					border-radius: 3px;
				}
				.back-link:hover {
					background: #005987;
				}
			</style>
		</head>
		<body>
			<h1>LZA Class Manager Diagnostics</h1>
			
			<section>
				<h2>Plugin Information</h2>
				<table>
					<tbody>
						<?php foreach ( $info as $key => $value ) : ?>
						<tr>
							<th><?php echo esc_html( $key ); ?></th>
							<td><?php echo esc_html( $value ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
			
			<section>
				<h2>File System Check</h2>
				<table>
					<thead>
						<tr>
							<th>File</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $file_status as $file => $status ) : ?>
						<tr>
							<td><?php echo esc_html( $file ); ?></td>
							<td class="<?php echo $status === 'Exists' ? 'success' : 'error'; ?>">
								<?php echo esc_html( $status ); ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
			
			<section>
				<h2>User Information</h2>
				<table>
					<tbody>
						<?php foreach ( $user_info as $key => $value ) : ?>
						<tr>
							<th><?php echo esc_html( $key ); ?></th>
							<td><?php echo esc_html( $value ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
			
			<section>
				<h2>CodeMirror Status</h2>
				<p>CodeMirror: <span class="<?php echo $codemirror_status === 'Available' ? 'success' : 'error'; ?>"><?php echo esc_html( $codemirror_status ); ?></span></p>
				
				<h3>CodeMirror Settings Response</h3>
				<pre><?php echo esc_html( print_r( $codemirror_test, true ) ); ?></pre>
			</section>
			
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=lza-class-manager' ) ); ?>" class="back-link">Back to LZA Class Manager</a>
		</body>
		</html>
		<?php
	}
}

// Hook into plugin init
add_action( 'admin_init', array( 'LZA_Diagnostics', 'run' ) );

/**
 * Add diagnostics to footer
 */
function lza_class_manager_diagnostics() {
	// Only run in admin and when debug is on
	if ( ! is_admin() || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}

	?>
	<script>
	// Log Gutenberg environment details
	document.addEventListener('DOMContentLoaded', function() {
		console.log('LZA Class Manager Diagnostics:');
		
		// Check if we're in the block editor
		if (typeof wp === 'undefined') {
			console.log('- WordPress JS API not found');
			return;
		}
		
		// Check required JS modules
		const modules = [
			'blockEditor', 'blocks', 'components', 'compose', 
			'data', 'element', 'hooks', 'i18n', 'plugins'
		];
		
		modules.forEach(module => {
			console.log(`- wp.${module}: ${typeof wp[module] !== 'undefined' ? 'Available ✅' : 'Missing ❌'}`);
		});
		
		// Check if our plugin is loaded
		console.log(`- lzaClassManager global: ${typeof window.lzaClassManager !== 'undefined' ? 'Available ✅' : 'Missing ❌'}`);
		
		// Check for specific hooks
		if (typeof wp.hooks !== 'undefined') {
			const ourFilter = wp.hooks.hasFilter('editor.BlockEdit', 'lza-class-manager/with-class-manager');
			console.log(`- Our filter registered: ${ourFilter ? 'Yes ✅' : 'No ❌'}`);
		}
		
		// Check for React
		console.log(`- React available: ${typeof React !== 'undefined' ? 'Yes ✅' : 'No ❌'}`);
	});
	</script>
	<?php
}
add_action( 'admin_footer', 'lza_class_manager_diagnostics' );
