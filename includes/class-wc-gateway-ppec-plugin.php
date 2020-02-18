<?php
/**
 * PayPal Checkout Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Plugin {

	const ALREADY_BOOTSTRAPED = 1;
	const DEPENDENCIES_UNSATISFIED = 2;
	const NOT_CONNECTED = 3;

	/**
	 * Filepath of main plugin file.
	 *
	 * @var string
	 */
	public $file;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Absolute plugin path.
	 *
	 * @var string
	 */
	public $plugin_path;

	/**
	 * Absolute plugin URL.
	 *
	 * @var string
	 */
	public $plugin_url;

	/**
	 * Absolute path to plugin includes dir.
	 *
	 * @var string
	 */
	public $includes_path;

	/**
	 * Flag to indicate the plugin has been boostrapped.
	 *
	 * @var bool
	 */
	private $_bootstrapped = false;

	/**
	 * Instance of WC_Gateway_PPEC_Settings.
	 *
	 * @var WC_Gateway_PPEC_Settings
	 */
	public $settings;

	/**
	 * Constructor.
	 *
	 * @param string $file    Filepath of main plugin file
	 * @param string $version Plugin version
	 */
	public function __construct( $file, $version ) {
		$this->file    = $file;
		$this->version = $version;

		// Path.
		$this->plugin_path   = trailingslashit( plugin_dir_path( $this->file ) );
		$this->plugin_url    = trailingslashit( plugin_dir_url( $this->file ) );
		$this->includes_path = $this->plugin_path . trailingslashit( 'includes' );
	}

	/**
	 * Handle updates.
	 *
	 * @param string $new_version The plugin's new version.
	 */
	private function run_updater( $new_version ) {
		// Map old settings to settings API
		if ( get_option( 'pp_woo_enabled' ) ) {
			$settings_array                               = (array) get_option( 'woocommerce_ppec_paypal_settings', array() );
			$settings_array['enabled']                    = get_option( 'pp_woo_enabled' ) ? 'yes' : 'no';
			$settings_array['logo_image_url']             = get_option( 'pp_woo_logoImageUrl' );
			$settings_array['paymentAction']              = strtolower( get_option( 'pp_woo_paymentAction', 'sale' ) );
			$settings_array['subtotal_mismatch_behavior'] = 'addLineItem' === get_option( 'pp_woo_subtotalMismatchBehavior' ) ? 'add' : 'drop';
			$settings_array['environment']                = get_option( 'pp_woo_environment' );
			$settings_array['button_size']                = get_option( 'pp_woo_button_size' );
			$settings_array['instant_payments']           = get_option( 'pp_woo_blockEChecks' );
			$settings_array['require_billing']            = get_option( 'pp_woo_requireBillingAddress' );
			$settings_array['debug']                      = get_option( 'pp_woo_logging_enabled' ) ? 'yes' : 'no';

			// Make sure button size is correct.
			if ( ! in_array( $settings_array['button_size'], array( 'small', 'medium', 'large' ) ) ) {
				$settings_array['button_size'] = 'medium';
			}

			// Load client classes before `is_a` check on credentials instance.
			$this->_load_client();

			$live    = get_option( 'pp_woo_liveApiCredentials' );
			$sandbox = get_option( 'pp_woo_sandboxApiCredentials' );

			if ( $live && is_a( $live, 'WC_Gateway_PPEC_Client_Credential' ) ) {
				$settings_array['api_username']    = $live->get_username();
				$settings_array['api_password']    = $live->get_password();
				$settings_array['api_signature']   = is_callable( array( $live, 'get_signature' ) ) ? $live->get_signature() : '';
				$settings_array['api_certificate'] = is_callable( array( $live, 'get_certificate' ) ) ? $live->get_certificate() : '';
				$settings_array['api_subject']     = $live->get_subject();
			}

			if ( $sandbox && is_a( $sandbox, 'WC_Gateway_PPEC_Client_Credential' ) ) {
				$settings_array['sandbox_api_username']    = $sandbox->get_username();
				$settings_array['sandbox_api_password']    = $sandbox->get_password();
				$settings_array['sandbox_api_signature']   = is_callable( array( $sandbox, 'get_signature' ) ) ? $sandbox->get_signature() : '';
				$settings_array['sandbox_api_certificate'] = is_callable( array( $sandbox, 'get_certificate' ) ) ? $sandbox->get_certificate() : '';
				$settings_array['sandbox_api_subject']     = $sandbox->get_subject();
			}

			update_option( 'woocommerce_ppec_paypal_settings', $settings_array );
			delete_option( 'pp_woo_enabled' );
		}

		// Check the the WC version on plugin update to determine if we need to display a warning.
		// The option was added in 1.6.19 so we only need to check stores updating from before that version. Updating from 1.6.19 or greater would already have it set.
		if ( version_compare( get_option( 'wc_ppec_version' ), '1.6.19', '<' ) && version_compare( WC_VERSION, '3.0', '<' ) ) {
			update_option( 'wc_ppec_display_wc_3_0_warning', 'true' );
		}

		update_option( 'wc_ppec_version', $new_version );
	}

	/**
	 * Maybe run the plugin.
	 */
	public function maybe_run() {
		register_activation_hook( $this->file, array( $this, 'activate' ) );

		add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
		add_filter( 'allowed_redirect_hosts' , array( $this, 'whitelist_paypal_domains_for_redirect' ) );
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), array( $this, 'plugin_action_links' ) );
		add_action( 'wp_ajax_ppec_dismiss_notice_message', array( $this, 'ajax_dismiss_notice' ) );
	}

	public function bootstrap() {
		try {
			if ( $this->_bootstrapped ) {
				throw new Exception( __( '%s in WooCommerce Gateway PayPal Checkout plugin can only be called once', 'woocommerce-gateway-paypal-express-checkout' ), self::ALREADY_BOOTSTRAPED );
			}

			$this->_check_dependencies();

			if ( $this->needs_update() ) {
				$this->run_updater( $this->version );
			}

			$this->_run();
			$this->_check_credentials();

			$this->_bootstrapped = true;
		} catch ( Exception $e ) {
			if ( in_array( $e->getCode(), array( self::ALREADY_BOOTSTRAPED, self::DEPENDENCIES_UNSATISFIED ) ) ) {
				$this->bootstrap_warning_message = $e->getMessage();
			}

			if ( self::NOT_CONNECTED === $e->getCode() ) {
				$this->prompt_to_connect = $e->getMessage();
			}

			add_action( 'admin_notices', array( $this, 'show_bootstrap_warning' ) );
		}
	}

	public function show_bootstrap_warning() {
		$dependencies_message = isset( $this->bootstrap_warning_message ) ? $this->bootstrap_warning_message : null;
		if ( ! empty( $dependencies_message ) && 'yes' !== get_option( 'wc_gateway_ppec_bootstrap_warning_message_dismissed', 'no' ) ) {
			?>
			<div class="notice notice-warning is-dismissible ppec-dismiss-bootstrap-warning-message">
				<p>
					<strong><?php echo esc_html( $dependencies_message ); ?></strong>
				</p>
			</div>
			<script>
			( function( $ ) {
				$( '.ppec-dismiss-bootstrap-warning-message' ).on( 'click', '.notice-dismiss', function() {
					jQuery.post( "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>", {
						action: "ppec_dismiss_notice_message",
						dismiss_action: "ppec_dismiss_bootstrap_warning_message",
						nonce: "<?php echo esc_js( wp_create_nonce( 'ppec_dismiss_notice' ) ); ?>"
					} );
				} );
			} )( jQuery );
			</script>
			<?php
		}

		$prompt_connect = isset( $this->prompt_to_connect ) ? $this->prompt_to_connect : null;
		if ( ! empty( $prompt_connect ) && 'yes' !== get_option( 'wc_gateway_ppec_prompt_to_connect_message_dismissed', 'no' ) ) {
			?>
			<div class="notice notice-warning is-dismissible ppec-dismiss-prompt-to-connect-message">
				<p>
					<strong><?php echo wp_kses( $prompt_connect, array( 'a' => array( 'href' => array() ) ) ); ?></strong>
				</p>
			</div>
			<script>
			( function( $ ) {
				$( '.ppec-dismiss-prompt-to-connect-message' ).on( 'click', '.notice-dismiss', function() {
					jQuery.post( "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>", {
						action: "ppec_dismiss_notice_message",
						dismiss_action: "ppec_dismiss_prompt_to_connect",
						nonce: "<?php echo esc_js( wp_create_nonce( 'ppec_dismiss_notice' ) ); ?>"
					} );
				} );
			} )( jQuery );
			</script>
			<?php
		}
	}

	public function show_spb_notice() {
		// Should only show when PPEC is enabled but not in SPB mode.
		if ( 'yes' !== $this->settings->enabled || 'yes' === $this->settings->use_spb ) {
			return;
		}

		// Should only show on WooCommerce screens, the main dashboard, and on the plugins screen (as in WC_Admin_Notices).
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		if ( ! in_array( $screen_id, wc_get_screen_ids(), true ) && 'dashboard' !== $screen_id && 'plugins' !== $screen_id ) {
			return;
		}

		$setting_link = $this->get_admin_setting_link();
		$message = sprintf( __( '<p>PayPal Checkout with new <strong>Smart Payment Buttons™</strong> gives your customers the power to pay the way they want without leaving your site.</p><p>The <strong>existing buttons will be removed</strong> in the <strong>next release</strong>. Please upgrade to Smart Payment Buttons on the <a href="%s">PayPal Checkout settings page</a>.</p>', 'woocommerce-gateway-paypal-express-checkout' ), esc_url( $setting_link ) );
		?>
		<div class="notice notice-error">
			<?php echo wp_kses( $message, array( 'a' => array( 'href' => array() ), 'strong' => array(), 'p' => array() ) ); ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler for dismiss notice action.
	 *
	 * @since 1.4.7
	 * @version 1.4.7
	 */
	public function ajax_dismiss_notice() {
		if ( empty( $_POST['dismiss_action'] ) ) {
			return;
		}

		check_ajax_referer( 'ppec_dismiss_notice', 'nonce' );
		switch ( $_POST['dismiss_action'] ) {
			case 'ppec_dismiss_bootstrap_warning_message':
				update_option( 'wc_gateway_ppec_bootstrap_warning_message_dismissed', 'yes' );
				break;
			case 'ppec_dismiss_prompt_to_connect':
				update_option( 'wc_gateway_ppec_prompt_to_connect_message_dismissed', 'yes' );
				break;
		}
		wp_die();
	}

	/**
	 * Check dependencies.
	 *
	 * @throws Exception
	 */
	protected function _check_dependencies() {
		if ( ! function_exists( 'WC' ) ) {
			throw new Exception( __( 'WooCommerce Gateway PayPal Checkout requires WooCommerce to be activated', 'woocommerce-gateway-paypal-express-checkout' ), self::DEPENDENCIES_UNSATISFIED );
		}

		if ( version_compare( WC()->version, '2.5', '<' ) ) {
			throw new Exception( __( 'WooCommerce Gateway PayPal Checkout requires WooCommerce version 2.5 or greater', 'woocommerce-gateway-paypal-express-checkout' ), self::DEPENDENCIES_UNSATISFIED );
		}

		if ( ! function_exists( 'curl_init' ) ) {
			throw new Exception( __( 'WooCommerce Gateway PayPal Checkout requires cURL to be installed on your server', 'woocommerce-gateway-paypal-express-checkout' ), self::DEPENDENCIES_UNSATISFIED );
		}

		$openssl_warning = __( 'WooCommerce Gateway PayPal Checkout requires OpenSSL >= 1.0.1 to be installed on your server', 'woocommerce-gateway-paypal-express-checkout' );
		if ( ! defined( 'OPENSSL_VERSION_TEXT' ) ) {
			throw new Exception( $openssl_warning, self::DEPENDENCIES_UNSATISFIED );
		}

		preg_match( '/^(?:Libre|Open)SSL ([\d.]+)/', OPENSSL_VERSION_TEXT, $matches );
		if ( empty( $matches[1] ) ) {
			throw new Exception( $openssl_warning, self::DEPENDENCIES_UNSATISFIED );
		}

		if ( ! version_compare( $matches[1], '1.0.1', '>=' ) ) {
			throw new Exception( $openssl_warning, self::DEPENDENCIES_UNSATISFIED );
		}
	}

	/**
	 * Check credentials. If it's not client credential it means it's not set
	 * and will prompt admin to connect.
	 *
	 * @see https://github.com/woothemes/woocommerce-gateway-paypal-express-checkout/issues/112
	 *
	 * @throws Exception
	 */
	protected function _check_credentials() {
		$credential = $this->settings->get_active_api_credentials();
		if ( ! is_a( $credential, 'WC_Gateway_PPEC_Client_Credential' ) || '' === $credential->get_username() ) {
			$setting_link = $this->get_admin_setting_link();
			throw new Exception( sprintf( __( 'PayPal Checkout is almost ready. To get started, <a href="%s">connect your PayPal account</a>.', 'woocommerce-gateway-paypal-express-checkout' ), esc_url( $setting_link ) ), self::NOT_CONNECTED );
		}
	}

	/**
	 * Run the plugin.
	 */
	protected function _run() {
		require_once( $this->includes_path . 'functions.php' );
		$this->_load_handlers();
		add_action( 'admin_notices', array( $this, 'show_spb_notice' ) );
	}

	/**
	 * Callback for activation hook.
	 */
	public function activate() {
		if ( ! isset( $this->settings ) ) {
			require_once( $this->includes_path . 'class-wc-gateway-ppec-settings.php' );
			$settings = new WC_Gateway_PPEC_Settings();
		} else {
			$settings = $this->settings;
		}

		// Force zero decimal on specific currencies.
		if ( $settings->currency_has_decimal_restriction() ) {
			update_option( 'woocommerce_price_num_decimals', 0 );
			update_option( 'wc_gateway_ppce_display_decimal_msg', true );
		}
	}

	/**
	 * Load handlers.
	 */
	protected function _load_handlers() {
		// Client.
		$this->_load_client();

		// Load handlers.
		require_once( $this->includes_path . 'class-wc-gateway-ppec-privacy.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-settings.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-gateway-loader.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-admin-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-checkout-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-cart-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-ips-handler.php' );
		require_once( $this->includes_path . 'abstracts/abstract-wc-gateway-ppec-paypal-request-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-ipn-handler.php' );

		$this->settings       = new WC_Gateway_PPEC_Settings();
		$this->gateway_loader = new WC_Gateway_PPEC_Gateway_Loader();
		$this->admin          = new WC_Gateway_PPEC_Admin_Handler();
		$this->checkout       = new WC_Gateway_PPEC_Checkout_Handler();
		$this->cart           = new WC_Gateway_PPEC_Cart_Handler();
		$this->ips            = new WC_Gateway_PPEC_IPS_Handler();
		$this->client         = new WC_Gateway_PPEC_Client( $this->settings->get_active_api_credentials(), $this->settings->environment );
	}

	/**
	 * Load client.
	 *
	 * @since 1.1.0
	 */
	protected function _load_client() {
		require_once( $this->includes_path . 'abstracts/abstract-wc-gateway-ppec-client-credential.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-client-credential-certificate.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-client-credential-signature.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-client.php' );
	}

	/**
	 * Checks if the plugin needs to record an update.
	 *
	 * @return bool Whether the plugin needs to be updated.
	 */
	protected function needs_update() {
		return version_compare( $this->version, get_option( 'wc_ppec_version' ), '>' );
	}

	/**
	 * Link to settings screen.
	 */
	public function get_admin_setting_link() {
		if ( version_compare( WC()->version, '2.6', '>=' ) ) {
			$section_slug = 'ppec_paypal';
		} else {
			$section_slug = strtolower( 'WC_Gateway_PPEC_With_PayPal' );
		}
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
	}

	/**
	 * Allow PayPal domains for redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param array $domains Whitelisted domains for `wp_safe_redirect`
	 *
	 * @return array $domains Whitelisted domains for `wp_safe_redirect`
	 */
	public function whitelist_paypal_domains_for_redirect( $domains ) {
		$domains[] = 'www.paypal.com';
		$domains[] = 'paypal.com';
		$domains[] = 'www.sandbox.paypal.com';
		$domains[] = 'sandbox.paypal.com';
		return $domains;
	}

	/**
	 * Load localisation files.
	 *
	 * @since 1.1.2
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'woocommerce-gateway-paypal-express-checkout', false, plugin_basename( $this->plugin_path ) . '/languages' );
	}

	/**
	 * Add relevant links to plugins page.
	 *
	 * @since 1.2.0
	 *
	 * @param array $links Plugin action links
	 *
	 * @return array Plugin action links
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array();

		if ( function_exists( 'WC' ) ) {
			$setting_url = $this->get_admin_setting_link();
			$plugin_links[] = '<a href="' . esc_url( $setting_url ) . '">' . esc_html__( 'Settings', 'woocommerce-gateway-paypal-express-checkout' ) . '</a>';
		}

		$plugin_links[] = '<a href="https://docs.woocommerce.com/document/paypal-express-checkout/">' . esc_html__( 'Docs', 'woocommerce-gateway-paypal-express-checkout' ) . '</a>';

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Check if shipping is needed for PayPal. This only checks for virtual products (#286),
	 * but skips the check if there are no shipping methods enabled (#249).
	 *
	 * @since 1.4.1
	 * @version 1.4.1
	 *
	 * @return bool
	 */
	public static function needs_shipping() {
		$needs_shipping = false;

		if ( ! empty( WC()->cart->cart_contents ) ) {
			foreach ( WC()->cart->cart_contents as $cart_item_key => $values ) {
				if ( $values['data']->needs_shipping() ) {
					$needs_shipping = true;
					break;
				}
			}
		}

		return apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );
	}
}
