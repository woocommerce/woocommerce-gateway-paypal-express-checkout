<?php
/**
 * PayPal Express Checkout Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Plugin {

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
	 * Maybe run the plugin.
	 */
	public function maybe_run() {
		register_activation_hook( $this->file, array( $this, 'activate' ) );

		add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), array( $this, 'plugin_action_links' ) );
	}

	public function bootstrap() {
		try {
			if ( $this->_bootstrapped ) {
				throw new Exception( __( '%s in WooCommerce Gateway PayPal Express Checkout plugin can only be called once', 'wc-gateway-ppce' ) );
			}

			$this->_check_dependencies();
			$this->_run();

			$this->_bootstrapped = true;
			delete_option( 'wc_gateway_ppce_bootstrap_warning_message' );
		} catch ( Exception $e ) {
			update_option( 'wc_gateway_ppce_bootstrap_warning_message', $e->getMessage() );
			add_action( 'admin_notices', array( $this, 'show_bootstrap_warning' ) );
		}
	}

	public function show_bootstrap_warning() {
		$dependencies_message = get_option( 'wc_gateway_ppce_bootstrap_warning_message', '' );
		if ( ! empty( $dependencies_message ) ) {
			?>
			<div class="error fade">
				<p>
					<strong><?php echo esc_html( $dependencies_message ); ?></strong>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Check dependencies.
	 *
	 * @throws Exception
	 */
	protected function _check_dependencies() {
		if ( ! function_exists( 'WC' ) ) {
			throw new Exception( __( 'WooCommerce Gateway PayPal Express Checkout requires WooCommerce to be activated', 'wc-gateway-ppce' ) );
		}

		if ( version_compare( WC()->version, '2.5', '<' ) ) {
			throw new Exception( __( 'WooCommerce Gateway PayPal Express Checkout requires WooCommerce version 2.5 or greater', 'wc-gateway-ppce' ) );
		}
	}

	/**
	 * Run the plugin.
	 */
	protected function _run() {
		require_once( $this->includes_path . 'functions.php' );
		$this->_load_handlers();

		// TODO: move this out to specific handler, not in this class.
		add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );
	}

	/**
	 * Callback for activation hook.
	 */
	public function activate() {
		// Enable some options that we recommend for all merchants.
		add_option( 'pp_woo_allowGuestCheckout', serialize( true ) );
		add_option( 'pp_woo_ppc_enabled', serialize( true ) );

		// Schedule the creation of a public key/private key pair for Easy Signup.
		add_option( 'pp_woo_ipsPrivateKey', 'not_generated' );
		add_option( 'pp_woo_justActivated', true );


		if ( ! isset( $this->setings ) ) {
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
		// Load handlers.
		require_once( $this->includes_path . 'class-wc-gateway-ppec-settings.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-gateway-loader.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-admin-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-checkout-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-cart-handler.php' );

		$this->settings       = new WC_Gateway_PPEC_Settings();
		$this->gateway_loader = new WC_Gateway_PPEC_Gateway_Loader();
		$this->admin          = new WC_Gateway_PPEC_Admin_Handler();
		$this->checkout       = new WC_Gateway_PPEC_Checkout_Handler();
		$this->cart           = new WC_Gateway_PPEC_Cart_Handler();
	}

	/**
	 * Adds plugin action links
	 *
	 * @since 1.0.0
	 */
	public function plugin_action_links( $links ) {

		$section_slug = strtolower( 'PayPal_Express_Checkout_Gateway' );

		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug ) . '">' . __( 'Settings', 'wc-gateway-ppce' ) . '</a>',
			'<a href="http://docs.woothemes.com/document/woocommerce-gateway-paypal-express-checkout/">' . __( 'Docs', 'wc-gateway-ppce' ) . '</a>',
			'<a href="http://support.woothemes.com/">' . __( 'Support', 'wc-gateway-ppce' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	/**
	 * @todo move this out to specific handler, not in this class.
	 */
	public function woocommerce_init() {
		// If the plugin was just activated, generate a private/public key pair for use with Easy Setup.
		if ( get_option( 'pp_woo_justActivated' ) ) {
			delete_option( 'pp_woo_justActivated' );
			woo_pp_async_generate_private_key();
		}

		if ( isset( $_GET['start-ips-keygen'] ) && 'true' == $_GET['start-ips-keygen'] ) {
			woo_pp_generate_private_key();
			exit;
		}

		// If the buyer clicked on the "Check Out with PayPal" button, we need to wait for the cart
		// totals to be available.  Unfortunately that doesn't happen until
		// woocommerce_before_cart_totals executes, and there is already output sent to the browser by
		// this point.  So, to get around this issue, we'll enable output buffering to prevent WP from
		// sending anything back to the browser.
		if ( isset( $_GET['startcheckout'] ) && 'true' == $_GET['startcheckout'] ) {
			ob_start();
		}

		// Also start buffering if we're on an admin page and the merchant is trying to use Easy Signup.
		$is_ips_signup = isset( $_GET['ips-signup'] ) && 'true' == $_GET['ips-signup'];
		$is_ips_return = isset( $_GET['ips-return'] ) && 'true' == $_GET['ips-return'];
		if ( is_admin() && ( $is_ips_signup || $is_ips_return ) ) {
			ob_start();
		}

		if ( isset( $_GET['woo-paypal-return'] ) && 'true' == $_GET['woo-paypal-return'] ) {
			// call get ec and do ec
			// Make sure we have our token and payer ID
			if ( array_key_exists( 'token', $_GET ) && array_key_exists( 'PayerID', $_GET ) &&
					! empty( $_GET['token'] ) && ! empty( $_GET['PayerID'] ) ) {
				$token = $_GET['token'];
				$payer_id = $_GET['PayerID'];
			} else {
				// If the token and payer ID aren't there, just ignore this request
				return;
			}

			$checkout = new WooCommerce_PayPal_Checkout();
			try {
				$checkout_details = $checkout->getCheckoutDetails( $token );
			} catch( PayPal_API_Exception $e ) {
				wc_add_notice( __( 'Sorry, an error occurred while trying to retrieve your information from PayPal.  Please try again.', 'woo_pp' ), 'error' );
				return;
			} catch( PayPal_Missing_Session_Exception $e ) {
				wc_add_notice( __( 'Your PayPal checkout session has expired.  Please check out again.', 'woo_pp' ), 'error' );
				return;
			}

			$session = WC()->session->paypal;
			if ( ! $session || ! is_a( $session, 'WooCommerce_PayPal_Session_Data' ) ||
					$session->expiry_time < time() || $token != $session->token ) {
				wc_add_notice( __( 'Your PayPal checkout session has expired.  Please check out again.', 'woo_pp' ), 'error' );
				return;
			}

			$session->checkout_completed = true;
			$session->payerID = $payer_id;

			WC()->session->paypal = $session;

			if ( $session->using_ppc ) {
				WC()->session->chosen_payment_method = 'paypal_credit';
			} else {
				WC()->session->chosen_payment_method = 'paypal';
			}

			if ( 'order' == $session->leftFrom && $session->order_id ) {
				// Try to complete the payment now.
				try {
					$order_id = $session->order_id;
					$checkout = new WooCommerce_PayPal_Checkout();
					$payment_details = $checkout->completePayment( $order_id, $session->token, $session->payerID );
					$transaction_id = $payment_details->payments[0]->transaction_id;

					// TODO: Handle things like eChecks, giropay, etc.
					$order = new WC_Order( $order_id );
					$order->payment_complete( $transaction_id );
					$order->add_order_note( sprintf( __( 'PayPal transaction completed; transaction ID = %s', 'woo_pp' ), $transaction_id ) );
					$order->reduce_order_stock();
					WC()->cart->empty_cart();
					unset( WC()->session->paypal );

					wp_safe_redirect( $order->get_checkout_order_received_url() );
					exit;
				} catch( PayPal_Missing_Session_Exception $e ) {
					// For some reason, our session data is missing.  Generally, if we've made it this far,
					// this shouldn't happen.
					wc_add_notice( __( 'Sorry, an error occurred while trying to process your payment.  Please try again.', 'woo_pp' ), 'error' );
				} catch( PayPal_API_Exception $e ) {
					// Did we get a 10486 or 10422 back from PayPal?  If so, this means we need to send the buyer back over to
					// PayPal to have them pick out a new funding method.
					$need_to_redirect_back = false;
					foreach ( $e->errors as $error ) {
						if ( '10486' == $error->error_code || '10422' == $error->error_code ) {
							$need_to_redirect_back = true;
						}
					}

					if ( $need_to_redirect_back ) {
						$settings = new WC_Gateway_PPEC_Settings();
						$session->checkout_completed = false;
						$session->leftFrom = 'order';
						$session->order_id = $order_id;
						WC()->session->paypal = $session;
						wp_safe_redirect( $settings->getPayPalRedirectUrl( $session->token, true ) );
						exit;
					} else {
						$final_output = '<ul>';
						foreach ( $e->errors as $error ) {
							// These strings are located in lib/class-exception.php
							$final_output .= '<li>' . __( $error->maptoBuyerFriendlyError(), 'woo_pp' ) . '</li>';
						}
						$final_output .= '</ul>';
						wc_add_notice( __( 'Payment error:', 'woo_pp' ) . $final_output, 'error' );
						return;
					}
				}
			}
		}
	}
}
