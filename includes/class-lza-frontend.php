<?php
/**
 * Frontend functionality - handles loading styles on the site
 */
class LZA_Frontend {

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
	public function __construct( $css_processor ) {
		$this->css_processor = $css_processor;
	}

	/**
	 * Initialize frontend hooks
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
	}

	/**
	 * Enqueue frontend styles
	 */
	public function enqueue_frontend_styles() {
		$paths = $this->css_processor->get_css_paths();

		// First enqueue the root variables file if it exists (with no minification)
		if ( file_exists( $paths['root_vars_path'] ) ) {
			wp_enqueue_style(
				'lza-root-vars',
				$paths['root_vars_url'],
				array(),
				filemtime( $paths['root_vars_path'] )
			);
		}

		// Then enqueue the minified class styles
		if ( file_exists( $paths['custom_css_min_path'] ) ) {
			wp_enqueue_style(
				'lza-custom-classes',
				$paths['custom_css_min_url'],
				array( 'lza-root-vars' ),  // Make classes depend on vars
				filemtime( $paths['custom_css_min_path'] )
			);
		} elseif ( file_exists( $paths['custom_css_path'] ) ) {
			// Fall back to the non-minified version in uploads
			wp_enqueue_style(
				'lza-custom-classes',
				$paths['custom_css_url'],
				array( 'lza-root-vars' ),  // Make classes depend on vars
				filemtime( $paths['custom_css_path'] )
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
}
