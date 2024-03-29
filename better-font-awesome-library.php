<?php
/**
 * Better Font Awesome Library
 *
 * A class to implement Font Awesome in WordPress.
 *
 * @see      jsDelivr CDN and API
 * @link     http://www.jsdelivr.com/
 * @link     https://github.com/jsdelivr/api
 *
 * @since    1.0.0
 *
 * @package  Better Font Awesome Library
 */

/**
 * @todo test in both pre and post TinyMCE V4 (make sure icons all appear in
 *       editor and front end)
 * @todo There may be a better way to do get_local_file_contents(), refer to:
 *       https://github.com/markjaquith/feedback/issues/33
 * @todo Icon menu icon not showing up in black studio widget - add attribute
 *       selector for admin CSS instead of exact ID selector. Not sure if this
 *       is still an issue?
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Better_Font_Awesome_Library' ) ) :
class Better_Font_Awesome_Library {

	/**
	 * Better Font Awesome Library slug.
	 *
	 * @since  1.0.0 
	 * 
	 * @var    string
	 */
	const SLUG = 'bfa';

	/**
	 * Better Font Awesome Library version slug.
	 *
	 * @since  1.0.0
	 * 
	 * @var    string
	 */
	const VERSION = '1.0.0';

	/**
	 * jsDelivr API URL for Font Awesome version info.
	 *
	 * @since  1.0.0
	 * 
	 * @var    string
	 */
	const JSDELIVR_API_URL = 'http://api.jsdelivr.com/v1/jsdelivr/libraries/fontawesome/?fields=versions,lastversion';
	
	/**
	 * Initialization args.
	 *
	 * @since  1.0.0 
	 * 
	 * @var    array
	 */
	private $args;

	/**
	 * Default args to use if any $arg isn't specified.
	 *
	 * @since  1.0.0
	 * 
	 * @var    array
	 */
	private $default_args = array(
		'version'                 => 'latest',
		'minified'                => true,
		'remove_existing_fa'      => false,
		'load_styles'             => true,
		'load_admin_styles'       => true,
		'load_shortcode'          => true,
		'load_tinymce_plugin'     => true,
	);

	/**
	 * Args for wp_remote_get() calls.
	 *
	 * @since  1.0.0
	 *
	 * @var    array
	 */
	private $wp_remote_get_args = array(
		'timeout' => 10
	);

	/**
	 * Array to hold the jsDelivr API data.
	 *
	 * @since  1.0.0
	 * 
	 * @var    string
	 */
	private $api_data = array();

	/**
	 * Version of Font Awesome being used.
	 *
	 * @since  1.0.0
	 * 
	 * @var    string
	 */
	private $font_awesome_version;

	/**
	 * Font Awesome stylesheet URL.
	 *
	 * @since  1.0.0
	 * 
	 * @var    string
	 */
	private $stylesheet_url;

	/**
	 * Font Awesome CSS.
	 *
	 * @since  1.0.0
	 * 
	 * @var    string
	 */
	private $css;

	/**
	 * Data associated with the local fallback version of Font Awesome.
	 *
	 * @since  1.0.0
	 * 
	 * @var    string
	 */
	private $fallback_data = array(
		'directory' => 'lib/fallback-font-awesome/',
		'path'      => '',
		'url'       => '',
		'version'   => '',
		'css'       => '',
	);

	/**
	 * Array of available Font Awesome icon slugs.
	 *
	 * @since  1.0.0
	 * 
	 * @var    string
	 */
	private $icons = array();

	/**
	 * Font Awesome prefix to be used ('icon' or 'fa').
	 *
	 * @since  1.0.0
	 * 
	 * @var    string
	 */
	private $prefix;

	/**
	 * Array to track errors and wp_remote_get() failures.
	 *
	 * @since  1.0.0
	 * 
	 * @var    array
	 */
	private $errors = array();

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 *
	 * @var    Better_Font_Awesome_Library
	 */
	private static $instance = null;

	/**
	 * Returns the instance of this class, and initializes
	 * the instance if it doesn't already exist.
	 *
	 * @since   1.0.0
	 *
	 * @return  Better_Font_Awesome_Library  The BFAL object.
	 */
	public static function get_instance( $args = array() ) {
		
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self( $args );
		}

		return self::$instance;

	}

	/**
	 * Better Font Awesome Library constructor.
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $args  Initialization arguments.
	 */
	private function __construct( $args = array() ) {

		// Get initialization args.
		$this->args = $args;

		// Load the library functionality.
		add_action( 'plugins_loaded', array( $this, 'load' ) );

	}

	/**
	 * Set up all plugin library functionality.
	 *
	 * @since  1.0.0
	 */
	public function load() {

		// Initialize library properties and actions as needed.
		$this->initialize( $this->args );

		// Use the jsDelivr API to fetch info on the jsDelivr Font Awesome CDN.
		$this->setup_api_data();

		// Set the version of Font Awesome to be used.
		$this->set_active_version();

		// Set the URL for the Font Awesome stylesheet.
		$this->set_stylesheet_url( $this->font_awesome_version );

		// Get stylesheet and generate list of available icons in Font Awesome stylesheet.
		$this->setup_stylesheet_data();

		/**
		 * Remove existing Font Awesome CSS and shortcodes if needed.
		 *
		 * Use priority 15 to ensure this is done after other plugin
		 * CSS/shortcodes are loaded. This must run before any other
		 * style/script/shortcode actions so it doesn't accidentally
		 * remove them.
		 */
		if ( $this->args['remove_existing_fa'] ) {
			
			add_action( 'wp_enqueue_scripts', array( $this, 'remove_font_awesome_css' ), 15 );
			add_action( 'init', array( $this, 'remove_icon_shortcode' ), 15 );

		}

		/**
		 * Load front-end scripts and styles.
		 *
		 * Use priority 15 to make sure styles/scripts load after other plugins.
		 */
		if ( $this->args['load_styles'] || $this->args['remove_existing_fa'] ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'register_font_awesome_css' ), 15 );
		}

		/**
		 * Load admin scripts and styles.
		 *
		 * Use priority 15 to make sure styles/scripts load after other plugins.
		 */
		if ( $this->args['load_admin_styles'] || $this->args['load_tinymce_plugin'] ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'register_font_awesome_css' ), 15 );
		}

		/**
		 * Add [icon] shortcode.
		 *
		 * Use priority 15 to ensure this is done after removing existing Font
		 * Awesome CSS and shortcodes.
		 */
		if ( $this->args['load_shortcode'] || $this->args['load_tinymce_plugin'] ) {
			add_action( 'init', array( $this, 'add_icon_shortcode' ), 15 );
		}

		// Add Font Awesome and/or custom CSS to the editor.
		add_action( 'init', array( $this, 'add_editor_styles' ) );

		// Load TinyMCE functionality.
		if ( $this->args['load_tinymce_plugin'] ) {
		
			add_action( 'admin_head', array( $this, 'add_tinymce_components' ) );
			add_action( 'admin_head', array( $this, 'output_admin_head_variables' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'register_custom_admin_css' ), 15 );

		}

		// Output any necessary admin notices.
		add_action( 'admin_notices', array( $this, 'do_admin_notice' ) );

	}

	/**
	 * Do necessary initialization actions.
	 *
	 * @since  1.0.0
	 */
	private function initialize( $args ) {

		// Parse the initialization args with the defaults.
		$this->parse_args( $args );

		// Set fallback stylesheet directory URL and path.
		$this->setup_fallback_data();

	}

	/**
	 * Parse the initialization args with the defaults and apply bfa_args filter.
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $args  Args used to initialize BFAL.
	 */
	private function parse_args( $args = array() ) {

		// Parse initialization args with defaults.
		$this->args = wp_parse_args( $args, $this->default_args );

		/**
		 * Filter the initialization args.
		 *
		 * @since  1.0.0
		 *
		 * @param  array  $this->args  BFAL initialization args.
		 */
		$this->args = apply_filters( 'bfa_init_args', $this->args );

		/**
		 * Filter the wp_remote_get args.
		 *
		 * @since  1.0.0
		 *
		 * @param  array  $this->wp_remote_get_args  BFAL wp_remote_get_args args.
		 */
		$this->wp_remote_get_args = apply_filters( 'bfa_wp_remote_get_args', $this->wp_remote_get_args );

	}

	/**
	 * Set up data for the local fallback version of Font Awesome.
	 *
	 * @since  1.0.0
	 */
	private function setup_fallback_data() {

		// Set fallback directory path.
		$directory_path = plugin_dir_path( __FILE__ ) . $this->fallback_data['directory'];

		/**
		 * Filter directory path.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $directory_path  The path to the fallback Font Awesome directory.
		 */
		$directory_path = trailingslashit( apply_filters( 'bfa_fallback_directory_path', $directory_path ) );

		// Set fallback path and URL.
		$this->fallback_data['path'] = $directory_path . 'css/font-awesome' . $this->get_min_suffix() . '.css';
		$this->fallback_data['url'] = plugins_url( $this->fallback_data['directory'] . 'css/font-awesome' . $this->get_min_suffix() . '.css', dirname( $directory_path ) );

		// Get the fallback version based on package.json.
		$fallback_json_file_path = $directory_path . 'package.json';
		$fallback_data = json_decode( $this->get_local_file_contents( $fallback_json_file_path ) );
		$this->fallback_data['version'] = $fallback_data->version;

		// Get the fallback CSS.
		$this->fallback_data['css'] = $this->get_fallback_css();

	}

	/**
	 * Set up data for all versions of Font Awesome available on the jsDelivr
	 * CDN.
	 *
	 * Uses the jsDelivr API.
	 *
	 * @since  1.0.0
	 */
	private function setup_api_data() {
		$this->api_data = $this->fetch_api_data( self::JSDELIVR_API_URL );
	}

	/**
	 * Fetch the jsDelivr API data.
	 *
	 * First check to see if the api-versions transient is set, and if not use
	 * the jsDelivr API to retrieve all available versions of Font Awesome.
	 *
	 * @since   1.0.0
	 *
	 * @return  array|WP_ERROR  Available CDN Font Awesome versions, or a
	 *                          WP_ERROR if the fetch fails.
	 */
	private function fetch_api_data( $url ) {

		if ( false === ( $response = get_transient( self::SLUG . '-api-versions' ) ) ) {
			
			$response = wp_remote_get( $url, $this->wp_remote_get_args );

			if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
				
				// Decode the API data and grab the versions info.
				$json_data = json_decode( wp_remote_retrieve_body( $response ) );
				$response = $json_data[0];

				/**
				 * Filter the API transient expiration.
				 *
				 * @since  1.0.0
				 *
				 * @param  int  Expiration for API transient.
				 */
				$transient_expiration = apply_filters( 'bfa_api_transient_expiration', 12 * HOUR_IN_SECONDS );

				// Set the API transient.
				set_transient( self::SLUG . '-api-versions', $response, $transient_expiration );

			} else {

				$this->set_error( 'api', $response->get_error_code(), $response->get_error_message() . " (URL: $url" );
				$response = '';

			}

		}

		return $response;

	}

	/**
	 * Set the version of Font Awesome to use.
	 *
	 * @since  1.0.0
	 */
	private function set_active_version() {

		if ( 'latest' == $this->args['version'] ) {
			$this->font_awesome_version = $this->get_latest_version();
		} else {
			$this->font_awesome_version = $this->args['version'];
		}

	}

	/**
	 * Get the latest available Font Awesome version.
	 *
	 * @since   1.0.0
	 *
	 * @return  string  Latest available Font Awesome version, either via the
	 *                  jsDelivr API data (if available), or a best guess based on transient and
	 *                  fallback version data.
	 */
	private function get_latest_version() {

		if ( $this->api_data_exists() ) {
			return $this->get_api_value( 'lastversion' );
		} else {
			return $this->guess_latest_version();
		}

	}

	/**
	 * Guess the latest Font Awesome version.
	 *
	 * Check both the transient Font Awesome CSS array and the locally-hosted
	 * version of Font Awesome to determine the latest listed version.
	 *
	 * @since   1.0.0
	 *
	 * @return  string  Latest transient or fallback version of Font Awesome CSS.
	 */
	private function guess_latest_version() {

		$css_transient_latest_version = $this->get_css_transient_latest_version();

		if ( version_compare( $css_transient_latest_version, $this->fallback_data['version'], '>' ) ) { 
			return $css_transient_latest_version;
		} else { 
			return $this->fallback_data['version'];
		}

	}

	/**
	 * Get the latest version saved in the CSS transient.
	 *
	 * @since   1.0.0
	 *
	 * @return  string  Latest version key in the CSS transient array.
	 *                  Return '0' if the CSS transient isn't set.
	 */
	private function get_css_transient_latest_version() {

		$transient_css_array = get_transient( self::SLUG . '-css' );
		
		if ( ! empty( $transient_css_array ) ) {
			return max( array_keys( $transient_css_array ) );
		} else {
			return '0';
		}

	}

	/**
	 * Determine the remote Font Awesome stylesheet URL based on the selected
	 * version.
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $version  Version of Font Awesome to use.
	 */
	private function set_stylesheet_url( $version ) {
		$this->stylesheet_url = '//cdn.jsdelivr.net/fontawesome/' . $version . '/css/font-awesome' . $this->get_min_suffix() . '.css';
	}

	/**
	 * Get stylesheet CSS and populate icons array.
	 *
	 * @since  1.0.0
	 */
	private function setup_stylesheet_data() {

		// Get the Font Awesome CSS.
		$this->css = $this->get_css( $this->stylesheet_url, $this->font_awesome_version );

		// Get the list of available icons from the Font Awesome CSS.
		$this->icons = $this->setup_icon_array( $this->css );

		// Set up prefix based on version ('fa' or 'icon').
		$this->prefix = $this->setup_prefix( $this->font_awesome_version );

	}

	/**
	 * Get the Font Awesome CSS.
	 *
	 * @since   1.0.0
	 *
	 * @param   string  $url       URL of the remote stylesheet.
	 * @param   string  $version   Version of Font Awesome to fetch.
	 *
	 * @return  string  $response  Font Awesome CSS, from either:
	 *                             1. transient,
	 *                             2. wp_remote_get(), or 
	 *                             3. fallback CSS.
	 */
	private function get_css( $url, $version ) {
		
		// First try getting the transient CSS.
		$response = $this->get_transient_css( $version );
		
		// Next, try fetching the CSS from the remote jsDelivr CDN.
		if ( ! $response ) {
			$response = $this->get_remote_css( $url, $version );
		}

		/**
		 * Use the local fallback if both the transient and wp_remote_get()
		 * methods fail.
		 */
		if ( is_wp_error( $response ) ) {

			// Log the CSS fetch error.
			$this->set_error( 'css', $response->get_error_code(), $response->get_error_message() . " (URL: $url)" );
			
			// Use the local fallback CSS.
			$response = $this->fallback_data['css'];

			// Update the version string to match the fallback version.
			$this->font_awesome_version = $this->fallback_data['version'];

			// Update the stylesheet URL to match the fallback version.
			$this->stylesheet_url = $this->fallback_data['url'];
		}

		return $response;

	}

	/**
	 * Get the transient copy of the CSS for the specified version.
	 *
	 * @since   1.0.0
	 *
	 * @param   string  $version  Font Awesome version to check.
	 *
	 * @return  string  		  Transient CSS if it exists, otherwise null.
	 */
	private function get_transient_css( $version ) {
		
		$transient_css_array = get_transient( self::SLUG . '-css' );
		return isset( $transient_css_array[ $version ] ) ? $transient_css_array[ $version ] : '';

	}

	/**
	 * Get the CSS from the remote jsDelivr CDN.
	 *
	 * @since   1.0.0
	 *
	 * @param   string           $url       URL for the remote stylesheet.
	 * @param   string           $version   Font Awesome version to fetch.
	 *
	 * @return  string|WP_ERROR  $response  Remote CSS, or WP_ERROR if
	 *                                      wp_remote_get() fails.
	 */
	private function get_remote_css( $url, $version ) {

		// Get the remote Font Awesome CSS.
		$url = set_url_scheme( $url );
		$response = wp_remote_get( $url, $this->wp_remote_get_args );
		
		/**
		 * If fetch was successful, return the remote CSS, and set the CSS
		 * transient for this version. Otherwise, return a WP_Error object.
		 */
		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			
			$response = wp_remote_retrieve_body( $response );
			$this->set_css_transient( $version, $response );

		} elseif ( is_wp_error( $response ) ) {
			$response = $response;
		} elseif ( isset( $response['response'] ) ) {
			$response = new WP_Error( $response['response']['code'], $response['response']['message'] . " (URL: $url)" );
		} else {
			$response = '';
		}

		return $response;

	}

	/**
	 * Set the CSS transient array.
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $version  Version of Font Awesome for which to set the
	 *                           transient.
	 * @param  string  $value    CSS for corresponding version of Font Awesome.
	 */
	private function set_css_transient( $version, $value ) {

		/**
		 * Get the transient array, which contains data for multiple Font
		 * Awesome version.
		 */
		$transient_css_array = get_transient( self::SLUG . '-css' );
		
		// Set the new value for the specified Font Awesome version.
		$transient_css_array[ $version ] = $value;

		/**
		 * Filter the CSS transient expiration.
		 *
		 * @since  1.0.0
		 *
		 * @param  int  Expiration for the CSS transient.
		 */
		$transient_expiration = apply_filters( 'bfa_css_transient_expiration', 30 * DAY_IN_SECONDS );
		
		// Set the new CSS array transient.
		set_transient( self::SLUG . '-css', $transient_css_array, $transient_expiration );

	}

	/**
	 * Get the CSS of the local fallback Font Awesome version.
	 *
	 * @since   1.0.0
	 *
	 * @return  string  Contents of the local fallback Font Awesome stylesheet.
	 */
	private function get_fallback_css() {
		return $this->get_local_file_contents( $this->fallback_data['path'] );
	}

	/**
	 * Get array of icons from the Font Awesome CSS.
	 *
	 * @since   1.0.0
	 *
	 * @param   string  $css  The Font Awesome CSS.
	 *
	 * @return  array   	  All available icon names (e.g. adjust, car, pencil).
	 */
	private function setup_icon_array( $css ) {
		
		$icons = array();

		// Get all CSS selectors that have a "content:" pseudo-element rule.
		preg_match_all( '/(\.[^}]*)\s*{\s*(content:)/s', $css, $matches );
		$selectors = $matches[1];

		// Select for all icon- and fa- selectors and split where there are commas.
		foreach ( $selectors as $selector ) {
			preg_match_all( '/\.(icon-|fa-)([^,]*)\s*:before/s', $selector, $matches );
			$clean_selectors = $matches[2];

			// Create an array of selectors (icon names).
			foreach ( $clean_selectors as $clean_selector )
				$icons[] = $clean_selector;
		}

		// Alphabetize the icons array.
		sort( $icons );

		/**
		 * Filter the array of available icons.
		 *
		 * @since   1.0.0
		 * 
		 * @param   array  $icons  Array of all available icons.
		 */
		$icons = apply_filters( 'bfa_icon_list', $icons );

		return $icons;

	}

	/**
	 * Get the Font Awesosome prefix ('fa' or 'icon').
	 *
	 * @since  1.0.0
	 *
	 * @param   string  $version  Font Awesome version being used.
	 *
	 * @return  string  $prefix  'fa' or 'icon', depending on the version.
	 */
	private function setup_prefix( $version ) {

		if ( 0 <= version_compare( $version, '4' ) ) {
			$prefix = 'fa';
		} else {
			$prefix = 'icon';
		}

		/**
		 * Filter the Font Awesome prefix.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $prefix  Font Awesome prefix ('icon' or 'fa').
		 */
		$prefix = apply_filters( 'bfa_prefix', $prefix );

		return $prefix;

	}

	/**
	 * Remove styles that include 'fontawesome' or 'font-awesome' in their slug.
	 *
	 * @since  1.0.0
	 */
	public function remove_font_awesome_css() {
		
		global $wp_styles;

		// Loop through all registered styles and remove any that appear to be Font Awesome.
		foreach ( $wp_styles->registered as $script => $details ) {
			
			if ( false !== strpos( $script, 'fontawesome' ) || false !== strpos( $script, 'font-awesome' ) ) {
				wp_dequeue_style( $script );
			}

		}

	}

	/**
	 * Remove [icon] shortcode.
	 *
	 * @since  1.0.0
	 */
	public function remove_icon_shortcode() {
		remove_shortcode( 'icon' );
	}

	/**
	 * Add [icon] shortcode.
	 *
	 * Usage:
	 * [icon name="flag" class="fw 2x spin" unprefixed_class="custom_class"]
	 *
	 * @since  1.0.0
	 */
	public function add_icon_shortcode() {
		add_shortcode( 'icon', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render [icon] shortcode.
	 *
	 * Usage:
	 * [icon name="flag" class="fw 2x spin" unprefixed_class="custom_class"]
	 *
	 * @param   array   $atts    Shortcode attributes.
	 * @return  string  $output  Icon HTML (e.g. <i class="fa fa-car"></i>).
	 */
	public function render_shortcode( $atts ) {
		
		extract( shortcode_atts( array(
					'name' => '',
					'class' => '',
					'unprefixed_class' => '',
					'title'     => '', /* For compatibility with other plugins */
					'size'      => '', /* For compatibility with other plugins */
					'space'     => '',
				), $atts )
		);

		/**
		 * Include for backwards compatibility with Font Awesome More Icons plugin.
		 *
		 * @see https://wordpress.org/plugins/font-awesome-more-icons/
		 */
		$title = $title ? 'title="' . $title . '" ' : '';
		$space = 'true' == $space ? '&nbsp;' : '';
		$size = $size ? ' '. $this->prefix . '-' . $size : '';

		// Remove "icon-" and "fa-" from name
		// This helps both:
		//  1. Incorrect shortcodes (when user includes full class name including prefix)
		//  2. Old shortcodes from other plugins that required prefixes
		
		/**
		 * Strip 'icon-' and 'fa-' from $name.
		 * 
		 * This corrects for:
		 * 	1. Incorrect shortcodes (when user includes full class name including prefix)
		 *  2. Old shortcodes from other plugins that required prefixes
		 */
		$name = str_replace( 'icon-', '', $name );
		$name = str_replace( 'fa-', '', $name );

		// Add the version-specific prefix back on to $name.
		$icon_name = $this->prefix . '-' . $name;

		// Remove "icon-" and "fa-" from the icon class.
		$class = str_replace( 'icon-', '', $class );
		$class = str_replace( 'fa-', '', $class );

		// Remove extra spaces from the icon class.
		$class = trim( $class );
		$class = preg_replace( '/\s{3,}/', ' ', $class );

		// Add the version-specific prefix back on to each class.
		$class = $class ? ' ' . $this->prefix . '-' . str_replace( ' ', ' ' . $this->prefix . '-', $class ) : '';

		// Add unprefixed classes.
		$class .= $unprefixed_class ? ' ' . $unprefixed_class : '';

		/**
		 * Filter the icon class.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $class  Classes attached to the icon.
		 */
		$class = apply_filters( 'bfa_icon_class', $class, $name );

		// Generate the HTML <i> icon element output.
		$output = sprintf( '<i class="%s %s" %s>%s</i>',
			$this->prefix,
			$icon_name . $class . $size,
			$title,
			$space
		);

		/**
		 * Filter the icon output.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $output  Icon output.
		 */
		return apply_filters( 'bfa_icon', $output );

	}

	/**
	 * Register and enqueue Font Awesome CSS.
	 */
	public function register_font_awesome_css() {

		wp_register_style( self::SLUG . '-font-awesome', $this->stylesheet_url, '', $this->font_awesome_version );
		wp_enqueue_style( self::SLUG . '-font-awesome' );

	}

	/**
	 * Add Font Awesome CSS to TinyMCE.
	 *
	 * @since  1.0.0
	 */
	public function add_editor_styles() {
		add_editor_style( $this->stylesheet_url );
	}

	/**
	 * Add TinyMCE button functionality.
	 *
	 * @since  1.0.0
	 */
	function add_tinymce_components() {
		
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		if ( get_user_option( 'rich_editing' ) == 'true' ) {
			
			add_filter( 'mce_external_plugins', array( $this, 'register_tinymce_plugin' ) );
			add_filter( 'mce_buttons', array( $this, 'add_tinymce_buttons' ) );

		}

	}

	/**
	 * Load TinyMCE Font Awesome dropdown plugin.
	 *
	 * @since  1.0.0
	 */
	function register_tinymce_plugin( $plugin_array ) {
		
		global $tinymce_version;

		/**
		 * Register the correct TinyMCE plugin based on the version of TinyMCE
		 * being used.
		 */
		if ( version_compare( $tinymce_version, '4000', '>=' ) ) {
			$plugin_array['bfa_plugin'] = plugins_url( 'js/tinymce-icons.js', __FILE__ );
		} else {
			$plugin_array['bfa_plugin'] = plugins_url( 'js/tinymce-icons-old.js', __FILE__ );
		}

		return $plugin_array;

	}

	/**
     * Add the TinyMCE button(s).
     *
     * @since  1.0.0
     */
	function add_tinymce_buttons( $buttons ) {
		
		array_push( $buttons, 'bfaSelect' );
		return $buttons;

	}

	/**
	 * Add PHP variables in HTML <head> for use by TinyMCE JavaScript.
	 *
	 * @since  1.0.0
	 */
	function output_admin_head_variables() {
			
		$icon_list = implode( ",", $this->icons );
		?>
		<!-- Better Font Awesome PHP variables for use by TinyMCE JavaScript -->
		<script type='text/javascript'>
		var bfa_vars = {
		    'fa_prefix': '<?php echo $this->prefix; ?>',
		    'fa_icons': '<?php echo $icon_list; ?>',
		};
		</script>
		<!-- End Better Font Awesome PHP variables for use by TinyMCE JavaScript -->
	    <?php

	}

	/**
	 * Load admin CSS.
	 *
	 * @since  1.0.0
	 */
	public function register_custom_admin_css() {
		wp_enqueue_style( self::SLUG . '-admin-styles', plugins_url( 'css/admin-styles.css', __FILE__ ) );
	}

	/**
	 * Generate admin notices.
	 *
	 * @since  1.0.0
	 */
	public function do_admin_notice() { 

		if ( ! empty( $this->errors ) ) :
			?>
		    <div class="error">
		    	<p>
		    		<b><?php _e( 'Better Font Awesome', 'bfa' ); ?></b>
		    	</p>
	        	
	        	<!-- API Error -->
	        	<?php if ( is_wp_error ( $this->get_error('api') ) ) : ?>
		        	<p>
		        		<b><?php _e( 'API Error', 'bfa' ); ?></b><br />
		        		<?php 
		        		printf( __( 'The attempt to reach the jsDelivr API server failed with the following error: %s', 'bfa' ), 
		        			'<code>' . $this->get_error('api')->get_error_code() . ': ' . $this->get_error('api')->get_error_message() . '</code>'
		        		);
		        		?>
		        	</p>
		        <?php endif; ?>

				<!-- CSS Error -->
	        	<?php if ( is_wp_error ( $this->get_error('css') ) ) : ?>
		        	<p>
		        		<b><?php _e( 'Remote CSS Error', 'bfa' ); ?></b><br />
		        		<?php 
		        		printf( __( 'The attempt to fetch the remote Font Awesome stylesheet failed with the following error: %s %s The embedded fallback Font Awesome will be used instead (version: %s).', 'bfa' ), 
		        			'<code>' . $this->get_error('css')->get_error_code() . ': ' . $this->get_error('css')->get_error_message() . '</code>',
		        			'<br />',
		        			'<code>' . $this->font_awesome_version . '</code>'
		        		);
			        	?>
			        </p>
		        <?php endif; ?>

		        <!-- Solution Text -->
		        <p>
		        	<b><?php _e( 'Solution', 'bfa' ); ?></b><br />
			        <?php
			        printf( __( 'This may be the result of a temporary server or connectivity issue which will resolve shortly. However if the problem persists please file a support ticket on the %splugin forum%s, citing the errors listed above. ', 'bfa' ),
	                    '<a href="http://wordpress.org/support/plugin/better-font-awesome" target="_blank" title="Better Font Awesome support forum">',
	                    '</a>'
	                );
	                ?>
	            </p>
		    </div>
		    <?php
	    endif;
	}

	/*----------------------------------------------------------------------------*
	 * Helper Functions
	 *----------------------------------------------------------------------------*/

	/**
	 * Get the contents of a local file.
	 *
	 * @since   1.0.0
	 *
	 * @param   string  $file_path  Path to local file.
	 *
	 * @return  string  $contents   Content of local file.
	 */
	private function get_local_file_contents( $file_path ) {
		
		ob_start();
		include $file_path;
	    $contents = ob_get_clean();

	    return $contents;

	}

	/**
	 * Determine whether or not to use the .min suffix on Font Awesome
	 * stylesheet URLs.
	 *
	 * @since   1.0.0
	 *
	 * @return  string  '.min' if minification is specified, empty string if not.
	 */
	private function get_min_suffix() {
		return ( $this->args['minified'] ) ? '.min' : '';
	}

	/**
	 * Add an error to the $this->errors array.
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $error_type  Type of error (api, css, etc).
	 * @param  string  $code        Error code.
	 * @param  string  $message     Error message.
	 */	
	private function set_error( $error_type, $code, $message ) {
		$this->errors[ $error_type ] = new WP_Error( $code, $message );
	}

	/**
	 * Retrieve a library error.
	 *
	 * @since   1.0.0
	 *
	 * @param   string  $process  Slug of the process to check (e.g. 'api').
	 *
	 * @return  WP_ERROR          The error for the specified process.
	 */
	public function get_error( $process ) {
		return isset( $this->errors[ $process ] ) ? $this->errors[ $process ] : '';
	}

	/**
	 * Check if API version data has been retrieved.
	 *
	 * @since   1.0.0
	 *
	 * @return  boolean  Whether or not the API version info was successfully fetched.
	 */
	public function api_data_exists() {

		if ( $this->api_data ) {
			return true;
		} else {
			return false;	
		}

	}

	/**
	 * Get a specific API value.
	 *
	 * @since   1.0.0
	 *
	 * @param   string  $key    Array key of the API data to get.
	 *
	 * @return  mixed   $value  Value associated with specified key.
	 */
	public function get_api_value( $key ) {
		
		if ( $this->api_data ) {
			$value = $this->api_data->$key;
		} else {
			$value = '';
		}

		return $value;

	}

	/*----------------------------------------------------------------------------*
	 * Public User Functions
	 *----------------------------------------------------------------------------*/

	/**
	 * Get the version of Font Awesome currently in use.
	 *
	 * @since   1.0.0
	 *
	 * @return  string  Font Awesome version.
	 */
	public function get_version() {
		return $this->font_awesome_version;
	}

	/**
	 * Get the stylesheet URL.
	 *
	 * @since   1.0.0
	 *
	 * @return  string  Stylesheet URL.
	 */
	public function get_stylesheet_url() {
		return $this->stylesheet_url;
	}

	/**
	 * Get the array of available icons.
	 *
	 * @since   1.0.0
	 *
	 * @return  array  Available Font Awesome icons.
	 */
	public function get_icons() {
		return $this->icons;
	}

	/**
	 * Get the icon prefix ('fa' or 'icon').
	 *
	 * @since   1.0.0
	 *
	 * @return  string  Font Awesome prefix.
	 */
	public function get_prefix() {
		return $this->prefix;
	}

	/**
	 * Get version data for the remote jsDelivr CDN.
	 *
	 * @since   1.0.0
	 *
	 * @return  object  jsDelivr API data.
	 */
	public function get_api_data() {
		return $this->api_data;
	}

	/**
	 * Get errors.
	 *
	 * @since   1.0.0
	 *
	 * @return  array  All library errors that have occured.
	 */
	public function get_errors() {
		return $this->errors;
	}

}
endif;
