<?php
/**
 * Plugin Name:  Cherry Mailer
 * Plugin URI: http://www.cherryframework.com/
 * Description: ShortCode for MailChimp
 * Version: 1.0.0
 * Author: Cherry Team
 * Author URI: http://www.cherryframework.com/
 * Text Domain: cherry-portfolio
 *
 * @package Cherry_Mailer
 *
 * @since 1.0.0
 */

if ( ! class_exists( 'Cherry_Mailer_Shortcode' ) ) {
	// simple api class for MailChimp from https://github.com/drewm/mailchimp-api/blob/master/src/Drewm/MailChimp.php
	require_once( 'includes/mailchimp-api.php' );

	// shortcode frontend generation
	require_once( 'includes/cherry-mailer-data.php' );

	// cherry options page
	require_once( 'admin/includes/class-cherry-mailer-options/class-cherry-mailer-options.php' );

	/**
	 * Set constant path to the plugin directory.
	 *
	 * @since 1.0.0
	 */
	define( 'CHERRY_MAILER_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );

	/**
	 * Set constant path to the plugin URI.
	 *
	 * @since 1.0.0
	 */
	define( 'CHERRY_MAILER_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );

	/**
	 * Set constant version.
	 *
	 * @since 1.0.0
	 */
	define( 'CHERRY_MAILER_VERSION', '1.0.0' );

	/**
	 * Set constant slug.
	 *
	 * @since 1.0.0
	 */
	define( 'CHERRY_MAILER_SLUG', 'cherry-mailer' );

	/**
	 * Define plugin
	 *
	 * @package Cherry_Mailer
	 * @since  1.0.0
	 */
	class Cherry_Mailer_Shortcode {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var object
		 */
		private static $instance = null;

		/**
		 * Prefix name
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public static $name = 'mailer';

		/**
		 * Options list of plugin
		 *
		 * @since 1.0.0
		 * @var array
		 */
		public $options = array(
			'apikey'            => '',
			'list'              => '',
			'confirm'           => '',
			'popup_is'          => 'true',
			'placeholder'       => '',
			'button_text'       => '',
			'success_message'   => '',
			'fail_message'      => '',
			'warning_message'   => '',
		);

		/**
		 * Sets up our actions/filters.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {

			// Register shortcode on 'init'.
			add_action( 'init', array( $this, 'register_shortcode' ) );

			// Register shortcode and add it to the dialog.
			add_filter( 'cherry_shortcodes/data/shortcodes', array( $this, 'shortcodes' ) );
			add_filter( 'cherry_templater/data/shortcodes',  array( $this, 'shortcodes' ) );

			add_filter( 'cherry_templater_target_dirs', array( $this, 'add_target_dir' ), 11 );
			add_filter( 'cherry_templater_macros_buttons', array( $this, 'add_macros_buttons' ), 11, 2 );

			// Modify mailer shortcode to aloow it process team
			add_filter( 'cherry_shortcodes_add_mailer_macros', array( $this, 'extend_mailer_macros' ) );
			add_filter( 'cherry-shortcode-swiper-mailer-postdata', array( $this, 'add_mailer_data' ), 10, 3 );

			$this->data = Cherry_Mailer_Data::get_instance();

			// Create menu item
			add_action( 'admin_menu', array( &$this, 'admin_menu' ) );

			// Need for submit frontend form
			add_action( 'wp_ajax_mailersubscribe', array( &$this, 'subscriber_add' ) );
			add_action( 'wp_ajax_nopriv_mailersubscribe', array( &$this, 'subscriber_add' ) );

			// Need for save options
			add_action( 'wp_ajax_cherry_mailer_save_options', array( &$this, 'save_options' ) );
			add_action( 'wp_ajax_nopriv_cherry_mailer_save_options', array( &$this, 'save_options' ) );

			// Get options
			$this->get_options();

			// Need for generate shortcode view
			add_action( 'wp_ajax_cherry_mailer_generator_view', array( &$this, 'generator_view' ) );
			add_action( 'wp_ajax_nopriv_cherry_mailer_generator_view', array( &$this, 'generator_view' ) );

			// Style to filter for Cherry Framework
			add_filter( 'cherry_compiler_static_css', array( $this, 'add_style_to_compiler' ) );

			// Language include
			add_action( 'plugins_loaded', array( $this, 'include_languages' ) );

			// Plugin update
			add_action( 'plugins_loaded', array( $this, 'plugin_update' ) );

		}

		/**
		 * Return true if CherryFramework active.
		 *
		 * @return boolean
		 */
		public function is_cherry_framework() {

			if ( class_exists( 'Cherry_Framework' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Load languages
		 *
		 * @since 1.0.0
		 */
		public function include_languages() {
			load_plugin_textdomain( 'cherry-mailer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Plugin update
		 *
		 * @since 1.0.0
		 */
		public function plugin_update() {
			if ( is_admin() ) {
				require_once( CHERRY_MAILER_DIR . 'admin/includes/class-cherry-update/class-cherry-plugin-update.php' );
				$Cherry_Plugin_Update = new Cherry_Plugin_Update();
				$Cherry_Plugin_Update -> init( array(
					'version'			=> CHERRY_MAILER_VERSION,
					'slug'				=> CHERRY_MAILER_SLUG,
					'repository_name'	=> CHERRY_MAILER_SLUG,
				));
			}
		}

		/**
		 * Adds team template directory to shortcodes templater
		 *
		 * @since  1.0.0
		 * @param  array $target_dirs existing target dirs.
		 * @return array
		 */
		public function add_target_dir( $target_dirs ) {
			array_push( $target_dirs, plugin_dir_path( __FILE__ ).'/' );
			return $target_dirs;
		}

		/**
		 * Registers the [$this->name] shortcode.
		 *
		 * @since 1.0.0
		 */
		public function register_shortcode() {
			/**
			 * Filters a shortcode name.
			 *
			 * @since 1.0.0
			 * @param string $this->name Shortcode name.
			 */
			$tag = apply_filters( self::$name . '_shortcode_name', self::$name );
			add_shortcode( $tag, array( $this, 'do_shortcode' ) );

			$tag_alternative = apply_filters( self::$name . '_shortcode_name', 'cherry_' . self::$name );
			add_shortcode( $tag_alternative, array( $this, 'do_shortcode' ) );
		}

		/**
		 * Filter to modify original shortcodes data and add [$this->name] shortcode.
		 *
		 * @since  1.0.0
		 * @param  array $shortcodes Original plugin shortcodes.
		 * @return array             Modified array.
		 */
		public function shortcodes( $shortcodes ) {
			$this->get_options();
			$placeholder        = empty( $this->options['placeholder'] )        ? __( 'Enter your email', 'cherry-mailer' )          : $this->options['placeholder'];
			$button_text        = empty( $this->options['button_text'] )        ? __( 'Subscribe', 'cherry-mailer' )                 : $this->options['button_text'];
			$success_message    = empty( $this->options['success_message'] )    ? __( 'Subscribed successfully', 'cherry-mailer' )   : $this->options['success_message'];
			$fail_message       = empty( $this->options['fail_message'] )       ? __( 'Subscribed failed', 'cherry-mailer' )         : $this->options['fail_message'];
			$warning_message    = empty( $this->options['warning_message'] )    ? __( 'Email is incorect', 'cherry-mailer' )         : $this->options['warning_message'];
			$popup_is           = empty( $this->options['popup_is '] )          ? __( 'true', 'cherry-mailer' )                      : $this->options['popup_is '];
			$shortcodes[ self::$name ] = array(
				'name'  => __( 'Mailer', 'cherry-mailer' ), // Shortcode name.
				'desc'  => __( 'Mailer shortcode', 'cherry-mailer' ),
				'type'  => 'single', // Can be 'wrap' or 'single'. Example: [b]this is wrapped[/b], [this_is_single]
				'group' => 'other', // Can be 'content', 'box', 'media' or 'other'. Groups can be mixed
				'atts'  => array( // List of shortcode params (attributes).
					'button_text' => array(
						'name'    => __( 'Button', 'cherry-mailer' ),
						'desc'    => __( 'Enter button title', 'cherry-mailer' ),
						'default' => $button_text,
					),
					'placeholder' => array(
						'name'    => __( 'Placeholder', 'cherry-mailer' ),
						'desc'    => __( 'Enter placeholder for email input', 'cherry-mailer' ),
						'default' => $placeholder,
					),
					'success_message' => array(
						'name'    => __( 'Success message', 'cherry-mailer' ),
						'desc'    => __( 'Enter success message', 'cherry-mailer' ),
						'default' => $success_message,
					),
					'fail_message' => array(
						'name'    => __( 'Fail message', 'cherry-mailer' ),
						'desc'    => __( 'Enter fail message', 'cherry-mailer' ),
						'default' => $fail_message,
					),
					'warning_message' => array(
						'name'    => __( 'Warning message', 'cherry-mailer' ),
						'desc'    => __( 'Enter warning message', 'cherry-mailer' ),
						'default' => $warning_message,
					),
					'popup_is' => array(
						'type'   => 'select',
						'name'    => __( 'Type', 'cherry-mailer' ),
						'desc'    => __( 'Switch popup or content', 'cherry-mailer' ),
						'values' => array(
							'true' => 'popup',
							'false' => 'content',
						),
						'default' => $popup_is,
					),
					'template' => array(
						'type'   => 'select',
						'values' => array(
							'default.tmpl' => 'default.tmpl',
						),
						'default' => 'default.tmpl',
						'name'    => __( 'Template', 'cherry-team' ),
						'desc'    => __( 'Shortcode template', 'cherry-team' ),
					),
				),
				'icon'     => 'envelope', // Custom icon (font-awesome).
				'function' => array( $this, 'do_shortcode' ), // Name of shortcode function.
			);
			return $shortcodes;
		}

		/**
		 * Pass style handle to CSS compiler.
		 *
		 * @since 1.0.0
		 * @param array $handles CSS handles to compile.
		 * @return array $handles
		 */
		function add_style_to_compiler( $handles ) {
			$handles = array_merge(
				array( 'cherry-team' => plugins_url( 'assets/css/style.css', __FILE__ ) ),
				$handles
			);
			return $handles;
		}

		/**
		 * The shortcode function.
		 *
		 * @since  1.0.0
		 * @param  array  $atts      The user-inputted arguments.
		 * @param  string $content   The enclosed content (if the shortcode is used in its enclosing form).
		 * @param  string $shortcode The shortcode tag, useful for shared callback functions.
		 * @return string
		 */
		public function do_shortcode( $atts, $content = null, $shortcode = 'mailer' ) {

			// Custom styles
			wp_register_style( 'simple-subscribe-style', plugins_url( 'assets/css/style.css', __FILE__ ) );
			wp_enqueue_style( 'simple-subscribe-style' );

			// Magnific popup styles
			wp_register_style( 'magnific-popup', plugins_url( 'assets/css/magnific-popup.css', __FILE__ ) );
			wp_enqueue_style( 'magnific-popup' );

			// Magnific popup scripts
			wp_register_script( 'magnific-popup', plugins_url( 'assets/js/jquery.magnific-popup.min.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
			wp_enqueue_script( 'magnific-popup' );

			// Custom scripts
			wp_register_script( 'mailer-script', plugins_url( 'assets/js/script.min.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
			wp_localize_script( 'mailer-script', 'cherryMailerParam', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
			wp_enqueue_script( 'mailer-script' );

			// Set up the default arguments.
			$defaults = array(
				'button_text'       => __( 'Subscribe', 'cherry-mailer' ),
				'placeholder'    	=> __( 'Enter your email', 'cherry-mailer' ),
				'success_message'   => __( 'Subscribed successfully', 'cherry-mailer' ),
				'fail_message'     	=> __( 'Subscribed failed', 'cherry-mailer' ),
				'warning_message'   => __( 'Email is incorect', 'cherry-mailer' ),
				'popup_is'          => 'true',
				'template'       	=> 'default.tmpl',
				'col_xs'         	=> '12',
				'col_sm'         	=> '6',
				'col_md'         	=> '3',
				'col_lg'         	=> 'none',
				'class'          	=> '',
			);
			/**
			 * Parse the arguments.
			 *
			 * @link http://codex.wordpress.org/Function_Reference/shortcode_atts
			 */
			$atts = shortcode_atts( $defaults, $atts, $shortcode );

			return $this->data->the_mailer( $atts );
		}

		/**
		 * Add team shortcode macros buttons to templater
		 *
		 * @since  1.0.0
		 *
		 * @param  array  $macros_buttons current buttons array.
		 * @param  string $shortcode      shortcode name.
		 * @return array
		 */
		public function add_macros_buttons( $macros_buttons, $shortcode ) {
			if ( self::$name != $shortcode ) {
				return $macros_buttons;
			}
			$macros_buttons = array(
				'placeholder' => array(
					'id'    => 'cherry_placeholder',
					'value' => __( 'Placeholder', 'cherry-mailer' ),
					'open'  => '%%PLACEHOLDER%%',
					'close' => '',
				),
				'button_text' => array(
					'id'    => 'cherry_button_text',
					'value' => __( 'Button text', 'cherry-mailer' ),
					'open'  => '%%BUTTON_TEXT%%',
					'close' => '',
				),
				'success_message' => array(
					'id'    => 'cherry_success_message',
					'value' => __( 'Success message', 'cherry-mailer' ),
					'open'  => '%%SUCCESS_MESSAGE%%',
					'close' => '',
				),
				'fail_message' => array(
					'id'    => 'cherry_fail_message',
					'value' => __( 'Fail message', 'cherry-mailer' ),
					'open'  => '%%FAIL_MESSAGE%%',
					'close' => '',
				),
				'warning_message' => array(
					'id'    => 'cherry_warning_message',
					'value' => __( 'Warning message', 'cherry-mailer' ),
					'open'  => '%%WARNING_MESSAGE%%',
					'close' => '',
				),
			);
			return $macros_buttons;
		}

		/**
		 * Add team macros data to process it in mailer shortcode
		 *
		 * @since 1.0.0
		 *
		 * @param  array $postdata default data.
		 * @param  array $post_id  processed post ID.
		 * @param  array $atts     shortcode attributes.
		 * @return array
		 */
		public function add_mailer_data( $postdata, $post_id, $atts ) {
			require_once( '/includes/class-cherry-mailer-template-callbacks.php' );
			$callbacks = new Cherry_Mailer_Template_Callbacks( $atts );
			$postdata['placeholder']   		= $callbacks->get_placeholder();
			$postdata['button_text']  		= $callbacks->get_button_text();
			$postdata['success_message'] 	= $callbacks->get_success_message();
			$postdata['fail_message']   	= $callbacks->get_fail_message();
			$postdata['warning_message']    = $callbacks->get_warning_message();
			$postdata['popup_is']           = $callbacks->get_popup_is();
			return $postdata;
		}


		/**
		 * Process save
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function save_options() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'Access denied.' ) );
			}

			foreach ( $this->options as $option_key => $option_value ) {
				$this->options[ $option_key ] = ! empty( $_POST[ $option_key ] ) ? $_POST[ $option_key ] : '';
			}

			update_option( self::$name . '_options', $this->options );

			$check_apikey = $this->check_apikey();

			if ( ! empty( $check_apikey ) ) {
				$connect_status = 'success';
				$connect_message = __( 'CONNECT', 'cherry-mailer' );
			} else {
				$connect_status = 'danger';
				$connect_message = __( 'DISCONNECT', 'cherry-mailer' );
			}

			$answer = array(
				'type'                  => 'success',
				'message'               => __( 'Options have been saved', 'cherry-mailer' ),
				'connect_status'        => $connect_status,
				'connect_message'       => $connect_message,
			);
			wp_send_json( $answer );
		}

		/**
		 * Generate shortcode view
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function generator_view() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'Access denied.' ) );
			}

			$this->get_options();

			// Shortcode generator
			$base_url = trailingslashit( CHERRY_MAILER_URI ) . 'admin/includes/class-cherry-shortcode-generator/';
			$base_dir = trailingslashit( CHERRY_MAILER_DIR ) . 'admin/includes/class-cherry-shortcode-generator/';
			require_once( $base_dir . 'class-cherry-shortcode-generator.php' );

			add_filter( 'cherry_shortcode_generator_register', array( &$this, 'add_shortcode_to_generator' ), 10, 2 );

			new Cherry_Shortcode_Generator( $base_dir, $base_url, 'cherry-mailer' );

			do_action( 'cherry_shortcode_generator_buttons' );
			die();
		}

		/**
		 * Get plugin options
		 *
		 * @since 1.0.0
		 * @return void
		 */
		private function get_options() {

			$this->options = $this->get_plugin_options();

			/*
			If ( $this->is_cherry_framework() ) {
				$options = $this->get_cherry_options();
			} else {
				$options = $this->get_plugin_options();
			}

			if ( ! empty( $options ) ) {
				$this->options = $options;
			}
			*/
		}

		/**
		 * Get plugin options from Cherry
		 *
		 * @since 1.0.0
		 * @return array
		 */
		private function get_cherry_options() {
			if ( $this->is_cherry_framework() ) {
				foreach ( $this->options as $key => $value ) {
					$options[ $key ] = cherry_get_option( self::$name . '_' . $key );
				}
			}

			if ( empty( $options ) ) {
				return $this->options;
			}

			return $options;
		}

		/**
		 * Get plugin options from plugin
		 *
		 * @since 1.0.0
		 * @return array
		 */
		private function get_plugin_options() {
			$options = get_option( self::$name . '_options' );

			if ( empty( $options ) ) {
				$options = $this->options;
			} else {
				foreach ( $this->options as $key => $value ) {
					$options[ $key ] = ! empty( $options[ $key ] ) ? $options[ $key ] : '';
				}
			}
			return $options;
		}

		/**
		 * Create admin menu item
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function admin_menu() {
			add_menu_page( 'Cherry Mailer Options', 'Cherry Mailer', 'manage_options', 'cherry-mailer-options', array( &$this, 'options_page' ), 'dashicons-email-alt', 110 );
		}

		/**
		 * Admin options page
		 *
		 * @since 1.0.0
		 * @return string
		 */
		public function options_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'Access denied.' ) );
			}

			if ( ! isset( $_GET['page'] ) || 'cherry-mailer-options' !== $_GET['page'] ) {
				return;
			}

			// Custom styles
			wp_register_style( 'simple-subscribe-admin', plugins_url( 'assets/css/admin.css', __FILE__ ) );
			wp_enqueue_style( 'simple-subscribe-admin' );

			// Custom scripts
			wp_register_script( 'mailer-script-api', plugins_url( 'assets/js/cherry-api.min.js', __FILE__ ) );
			wp_localize_script( 'mailer-script-api', 'cherry_ajax', wp_create_nonce( 'cherry_ajax_nonce' ) );
			wp_localize_script( 'mailer-script-api', 'wp_load_style', null );
			wp_localize_script( 'mailer-script-api', 'wp_load_script', null );
			wp_enqueue_script( 'mailer-script-api' );

			wp_register_script( 'mailer-script-custom', plugins_url( 'assets/js/admin.min.js', __FILE__ ) );
			wp_localize_script( 'mailer-script-custom', 'cherryMailerParam', array(
					'ajaxurl'                       => admin_url( 'admin-ajax.php' ),
					'default_error_message'         => __( 'Error', 'cherry-mailer' ),
					'default_disconnect_message'    => __( 'DISCONNECT', 'cherry-mailer' ),
				)
			);
			wp_enqueue_script( 'mailer-script-custom' );

			$options = $this->get_plugin_options();

			// Shortcode generator
			$base_url = trailingslashit( CHERRY_MAILER_URI ) . 'admin/includes/class-cherry-shortcode-generator/';
			$base_dir = trailingslashit( CHERRY_MAILER_DIR ) . 'admin/includes/class-cherry-shortcode-generator/';
			require_once( $base_dir . 'class-cherry-shortcode-generator.php' );

			add_filter( 'cherry_shortcode_generator_register', array( $this, 'add_shortcode_to_generator' ), 10, 2 );

			new Cherry_Shortcode_Generator( $base_dir, $base_url, 'cherry-mailer' );

			wp_enqueue_style( 'bootstrap', plugins_url( 'assets/css/bootstrap.min.css', __FILE__ ) );

			// Include ui-elements
			include trailingslashit( CHERRY_MAILER_DIR ) . '/admin/lib/ui-elements/ui-text/ui-text.php';
			include trailingslashit( CHERRY_MAILER_DIR ) . '/admin/lib/ui-elements/ui-select/ui-select.php';
			include trailingslashit( CHERRY_MAILER_DIR ) . '/admin/lib/ui-elements/ui-switcher/ui-switcher.php';
			include trailingslashit( CHERRY_MAILER_DIR ) . '/admin/lib/ui-elements/ui-textarea/ui-textarea.php';
			include trailingslashit( CHERRY_MAILER_DIR ) . '/admin/lib/ui-elements/ui-tooltip/ui-tooltip.php';

			// Return html of options page
			return include_once 'views/options-page.php';
		}

		/**
		 * Add Mailer shortcode to generator
		 *
		 * @since 1.0.0
		 * @return array
		 */
		public function add_shortcode_to_generator() {
			$options = $this->get_plugin_options();
			$placeholder        = empty( $options['placeholder'] )        ? __( 'Enter your email', 'cherry-mailer' )          : $options['placeholder'];
			$popup_is           = empty( $options['popup_is'] )           ? __( 'Type', 'cherry-mailer' )                      : $options['popup_is'];
			$button_text        = empty( $options['button_text'] )        ? __( 'Subscribe', 'cherry-mailer' )                 : $options['button_text'];
			$success_message    = empty( $options['success_message'] )    ? __( 'Subscribed successfully', 'cherry-mailer' )   : $options['success_message'];
			$fail_message       = empty( $options['fail_message'] )       ? __( 'Subscribed failed', 'cherry-mailer' )         : $options['fail_message'];
			$warning_message    = empty( $options['warning_message'] )    ? __( 'Email is incorect', 'cherry-mailer' )         : $options['warning_message'];

			$shortcodes = array(
				'team' => array(
					'name' => __( 'Mailer', 'cherry-mailer' ),
					'slug' => 'cherry_mailer',
					'desc' => __( 'Cherry Mailer shortcode', 'cherry-mailer' ),
					'type' => 'single',
					'atts' => array(
						array(
							'name'  => 'placeholder',
							'id'    => 'placeholder',
							'type'  => 'text',
							'value' => $placeholder,
							'label' => __( 'Placeholder', 'cherry-team' ),
							'desc'  => __( 'Placeholder for email input', 'cherry-mailer' ),
						),
						array(
							'name'  => 'button_text',
							'id'    => 'button_text',
							'type'  => 'text',
							'value' => $button_text,
							'label' => __( 'Button', 'cherry-team' ),
							'desc'  => __( 'Enter button title', 'cherry-mailer' ),
						),
						array(
							'name'  => 'success_message',
							'id'    => 'success_message',
							'type'  => 'text',
							'value' => $success_message,
							'label' => __( 'Success message', 'cherry-team' ),
							'desc'  => __( 'Enter success message', 'cherry-mailer' ),
						),
						array(
							'name'  => 'fail_message',
							'id'    => 'fail_message',
							'type'  => 'text',
							'value' => $fail_message,
							'label' => __( 'Fail message', 'cherry-team' ),
							'desc'  => __( 'Enter fail message', 'cherry-mailer' ),
						),
						array(
							'name'  => 'warning_message',
							'id'    => 'warning_message',
							'type'  => 'text',
							'value' => $warning_message,
							'label' => __( 'Warning message', 'cherry-team' ),
							'desc'  => __( 'Enter warning message', 'cherry-mailer' ),
						),
						array(
							'name'  => 'popup_is',
							'id'    => 'popup_is',
							'type'  => 'select',
							'options' => array(
								'true' => 'popup',
								'false' => 'content',
							),
							'default' => 'true',
							'value'   => $popup_is,
							'label' => __( 'Type', 'cherry-team' ),
							'desc'  => __( 'Popup or content', 'cherry-mailer' ),
						),
					),
					'icon'      => 'envelope',
					'function'  => array( $this, 'do_shortcode' ), // Name of shortcode function.
				),
			);

			return $shortcodes;
		}

		/**
		 * Check MailChimp account
		 *
		 * @since 1.0.0
		 * @return bool
		 */
		private function check_apikey() {
			if ( empty( $this->options['apikey'] ) ) {
				return false;
			}

			$mailchimpAPI_obj = new MailChimp( $this->options['apikey'] );
			$result = $mailchimpAPI_obj->call( '/helper/ping', array(
				'apikey'    => $this->options['apikey'],
			), 20);

			if ( ! empty( $result['error'] ) || empty( $result['msg'] ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Add email to subscriber list
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function subscriber_add() {

			$this->get_options();
			/**
			 * Default fail response
			 */

			$return = array(
				'status'	=> 'failed',
			);

			$email = sanitize_email( $_POST['email'] );

			if ( is_email( $email ) && ! empty( $this->options['list'] ) && $this->check_apikey() ) {

				/**
				 * Call api
				 */

				$mailerAPI_obj = new MailChimp( $this->options['apikey'] );
				$result = $mailerAPI_obj->call( '/lists/subscribe', array(
					'id'	=> $this->options['list'],
					'email'	=> array(
						'email'    => $email,
						'euid'     => time() . rand( 1, 1000 ),
						'leid'     => time() . rand( 1, 1000 ),
					),
					'double_optin'	=> $this->options['confirm'],
				), 20);

				if ( ! empty( $result['leid'] ) ) {

					/**
					 * Success response
					 */

					$return = array(
						'status' => 'success',
					);
				}

				$return['result'] = $result;

			}

			// Send answer
			wp_send_json( $return );
		}

		/**
		 * Returns the instance.
		 *
		 * @since  1.0.0
		 * @return object
		 */
		public static function get_instance() {

			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}
	}

	Cherry_Mailer_Shortcode::get_instance();
}
