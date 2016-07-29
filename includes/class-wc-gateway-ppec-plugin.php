<?php
/**
 * PayPal Express Checkout Plugin.
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

		// Updates
		if ( ! version_compare( $version, get_option( 'wc_ppec_version' ), '>' ) ) {
			$this->run_updater( $version );
		}
	}

	/**
	 * Handle updates.
	 * @param  [type] $new_version [description]
	 * @return [type]              [description]
	 */
	private function run_updater( $new_version ) {
		// Map old settings to settings API
		if ( get_option( 'pp_woo_enabled' ) ) {
			$settings_array                               = (array) get_option( 'woocommerce_ppec_paypal_settings', array() );
			$settings_array['enabled']                    = get_option( 'pp_woo_enabled' ) ? 'yes' : 'no';
			$settings_array['logo_image_url']             = get_option( 'pp_woo_logoImageUrl' );
			$settings_array['paymentAction']              = get_option( 'pp_woo_paymentAction' );
			$settings_array['subtotal_mismatch_behavior'] = 'addLineItem' === get_option( 'pp_woo_subtotalMismatchBehavior' ) ? 'add' : 'drop';
			$settings_array['environment']                = get_option( 'pp_woo_environment' );
			$settings_array['button_size']                = get_option( 'pp_woo_buttonSize' );
			$settings_array['instant_payments']           = get_option( 'pp_woo_blockEChecks' );
			$settings_array['require_billing']            = get_option( 'pp_woo_requireBillingAddress' );
			$settings_array['debug']                      = get_option( 'pp_woo_logging_enabled' ) ? 'yes' : 'no';

			$live    = get_option( 'woo_pp_liveApiCredentials' );
			$sandbox = get_option( 'sandboxApiCredentials' );

			if ( $live && is_a( $live, 'WC_Gateway_PPEC_Client_Credential' ) ) {
				$settings_array['api_username']    = $live->get_username();
				$settings_array['api_password']    = $live->get_password();
				$settings_array['api_signature']   = is_callable( array( $live, 'get_signature' ) ) ? $live->get_signature() : '';
				$settings_array['api_certificate'] = is_callable( array( $live, 'get_certificate' ) ) ? $live->get_certificate() : '';
				$settings_array['api_subject']     = $live->get_subject();
			}

			if ( $sandbox && is_a( $live, 'WC_Gateway_PPEC_Client_Credential' ) ) {
				$settings_array['sandbox_api_username']    = $sandbox->get_username();
				$settings_array['sandbox_api_password']    = $sandbox->get_password();
				$settings_array['sandbox_api_signature']   = is_callable( array( $sandbox, 'get_signature' ) ) ? $sandbox->get_signature() : '';
				$settings_array['sandbox_api_certificate'] = is_callable( array( $sandbox, 'get_certificate' ) ) ? $sandbox->get_certificate() : '';
				$settings_array['sandbox_api_subject']     = $sandbox->get_subject();
			}

			update_option( 'woocommerce_ppec_paypal_settings', $settings_array );
			delete_option( 'pp_woo_enabled' );
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
	}

	public function bootstrap() {
		try {
			if ( $this->_bootstrapped ) {
				throw new Exception( __( '%s in WooCommerce Gateway PayPal Express Checkout plugin can only be called once', 'woocommerce-gateway-paypal-express-checkout' ), self::ALREADY_BOOTSTRAPED );
			}

			$this->_check_dependencies();
			$this->_run();
			$this->_check_credentials();

			$this->_bootstrapped = true;
			delete_option( 'wc_gateway_ppce_bootstrap_warning_message' );
			delete_option( 'wc_gateway_ppce_prompt_to_connect' );
		} catch ( Exception $e ) {
			if ( in_array( $e->getCode(), array( self::ALREADY_BOOTSTRAPED, self::DEPENDENCIES_UNSATISFIED ) ) ) {

				update_option( 'wc_gateway_ppce_bootstrap_warning_message', $e->getMessage() );
			}

			if ( self::NOT_CONNECTED === $e->getCode() ) {
				update_option( 'wc_gateway_ppce_prompt_to_connect', $e->getMessage() );
			}

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

		$prompt_connect = get_option( 'wc_gateway_ppce_prompt_to_connect', '' );
		if ( ! empty( $prompt_connect ) ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php echo wp_kses( $prompt_connect, array( 'a' => array( 'href' => array() ) ) ); ?></strong>
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
			throw new Exception( __( 'WooCommerce Gateway PayPal Express Checkout requires WooCommerce to be activated', 'woocommerce-gateway-paypal-express-checkout' ), self::DEPENDENCIES_UNSATISFIED );
		}

		if ( version_compare( WC()->version, '2.5', '<' ) ) {
			throw new Exception( __( 'WooCommerce Gateway PayPal Express Checkout requires WooCommerce version 2.5 or greater', 'woocommerce-gateway-paypal-express-checkout' ), self::DEPENDENCIES_UNSATISFIED );
		}

		if ( ! function_exists( 'curl_init' ) ) {
			throw new Exception( __( 'WooCommerce Gateway PayPal Express Checkout requires cURL to be installed on your server', 'woocommerce-gateway-paypal-express-checkout' ), self::DEPENDENCIES_UNSATISFIED );
		}

		$openssl_warning = __( 'WooCommerce Gateway PayPal Express Checkout requires OpenSSL >= 1.0.1 to be installed on your server', 'woocommerce-gateway-paypal-express-checkout' );
		if ( ! defined( 'OPENSSL_VERSION_TEXT' ) ) {
			throw new Exception( $openssl_warning, self::DEPENDENCIES_UNSATISFIED );
		}

		preg_match( '/^OpenSSL ([\d.]+)/', OPENSSL_VERSION_TEXT, $matches );
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
		if ( ! is_a( $credential, 'WC_Gateway_PPEC_Client_Credential' ) ) {
			$setting_link = $this->get_admin_setting_link();
			throw new Exception( __( 'PayPal Express Checkout is almost ready. To get started, <a href="' . $setting_link . '">connect your PayPal account</a>.', 'woocommerce-gateway-paypal-express-checkout' ), self::NOT_CONNECTED );
		}
	}







		/**
		 * This function fills in the $credentials variable with the credentials
		 * the user filled in on the page, and returns true or false to indicate
		 * a success or error, respectively.
		 *
		 * Why not just return the credentials or false on failure? Because the user
		 * might not fill in the credentials at all, which isn't an error.  This way
		 * allows us to do it without returning an error because the user didn't fill
		 * in the credentials.
		 *
		 * @param string $environment Environment. Either 'live' or 'sandbox'
		 *
		 * @return WC_Gateway_PPEC_Client_Credential Credential object
		 */
		private function validate_credentials( $environment ) {
			$settings = wc_gateway_ppec()->settings->loadSettings();
			if ( 'sandbox' == $environment ) {
				$creds = $settings->sandboxApiCredentials;
			} else {
				$creds = $settings->liveApiCredentials;
			}

			$api_user  = trim( $_POST[ 'woo_pp_' . $environment . '_api_username' ] );
			$api_pass  = trim( $_POST[ 'woo_pp_' . $environment . '_api_password' ] );
			$api_style = trim( $_POST[ 'woo_pp_' . $environment . '_api_style' ] );

			$subject = trim( $_POST[ 'woo_pp_' . $environment . '_subject' ] );
			if ( empty( $subject ) ) {
				$subject = false;
			}

			$credential = false;
			if ( 'signature' === $api_style ) {
				$api_sig = trim( $_POST[ 'woo_pp_' . $environment . '_api_signature' ] );
			} elseif ( 'certificate' === $api_style ) {
				if ( array_key_exists( 'woo_pp_' . $environment . '_api_certificate', $_FILES )
					&& array_key_exists( 'tmp_name', $_FILES[ 'woo_pp_' . $environment . '_api_certificate' ] )
					&& array_key_exists( 'size', $_FILES[ 'woo_pp_' . $environment . '_api_certificate' ] )
					&& $_FILES[ 'woo_pp_' . $environment . '_api_certificate' ]['size'] ) {
					$api_cert = file_get_contents( $_FILES[ 'woo_pp_' . $environment . '_api_certificate' ]['tmp_name'] );
					$_POST[ 'woo_pp_' . $environment . '_api_cert_string' ] = base64_encode( $api_cert );
					unlink( $_FILES[ 'woo_pp_' . $environment . '_api_certificate' ]['tmp_name'] );
					unset( $_FILES[ 'woo_pp_' . $environment . '_api_certificate' ] );
				} elseif ( array_key_exists( 'woo_pp_' . $environment . '_api_cert_string', $_POST ) && ! empty( $_POST[ 'woo_pp_' . $environment . '_api_cert_string' ] ) ) {
					$api_cert = base64_decode( $_POST[ 'woo_pp_' . $environment . '_api_cert_string' ] );
				}
			} else {
				WC_Admin_Settings::add_error( sprintf( __( 'Error: You selected an invalid credential type for your %s API credentials.', 'woocommerce-gateway-paypal-express-checkout' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
				return false;
			}

			if ( ! empty( $api_user ) ) {
				if ( empty( $api_pass ) ) {
					WC_Admin_Settings::add_error( sprintf( __( 'Error: You must enter a %s API password.' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
					return false;
				}

				if ( 'signature' === $api_style ) {
					if ( ! empty( $api_sig ) ) {

						// Ok, test them out.
						$api_credentials = new WC_Gateway_PPEC_Client_Credential_Signature( $api_user, $api_pass, $api_sig, $subject );
						try {
							$payer_id = wc_gateway_ppec()->client->test_api_credentials( $api_credentials, $environment );
							if ( ! $payer_id ) {
								WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s credentials you provided are not valid.  Please double-check that you entered them correctly and try again.', 'woocommerce-gateway-paypal-express-checkout' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
								return false;
							}
							$api_credentials->set_payer_id( $payer_id );
						} catch( PayPal_API_Exception $ex ) {
							$this->display_warning( sprintf( __( 'An error occurred while trying to validate your %s API credentials.  Unable to verify that your API credentials are correct.', 'woocommerce-gateway-paypal-express-checkout' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
						}

						$credential = $api_credentials;

					} else {
						WC_Admin_Settings::add_error( sprintf( __( 'Error: You must provide a %s API signature.', 'woocommerce-gateway-paypal-express-checkout' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
						return false;
					}

				} else {
					if ( ! empty( $api_cert ) ) {
						$cert = openssl_x509_read( $api_cert );
						if ( false === $cert ) {
							WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s API certificate is not valid.', 'woocommerce-gateway-paypal-express-checkout' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
							self::$process_admin_options_validation_error = true;
							return false;
						}

						$cert_info = openssl_x509_parse( $cert );
						$valid_until = $cert_info['validTo_time_t'];
						if ( $valid_until < time() ) {
							WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s API certificate has expired.', 'woocommerce-gateway-paypal-express-checkout' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
							return false;
						}

						if ( $cert_info['subject']['CN'] != $api_user ) {
							WC_Admin_Settings::add_error( __( 'Error: The API username does not match the name in the API certificate.  Make sure that you have the correct API certificate.', 'woocommerce-gateway-paypal-express-checkout' ) );
							return false;
						}
					} else {
						// If we already have a cert on file, don't require one.
						if ( $creds && is_a( $creds, 'WC_Gateway_PPEC_Client_Credential_Certificate' ) ) {
							if ( ! $creds->get_certificate() ) {
								WC_Admin_Settings::add_error( sprintf( __( 'Error: You must provide a %s API certificate.', 'woocommerce-gateway-paypal-express-checkout' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
								return false;
							}
							$api_cert = $creds->get_certificate();
						} else {
							WC_Admin_Settings::add_error( sprintf( __( 'Error: You must provide a %s API certificate.', 'woocommerce-gateway-paypal-express-checkout' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
							return false;
						}
					}

					$api_credentials = new WC_Gateway_PPEC_Client_Credential_Certificate( $api_user, $api_pass, $api_cert, $subject );
					try {
						$payer_id = wc_gateway_ppec()->client->test_api_credentials( $api_credentials, $environment );
						if ( ! $payer_id ) {
							WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s credentials you provided are not valid.  Please double-check that you entered them correctly and try again.', 'woocommerce-gateway-paypal-express-checkout' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
							return false;
						}
						$api_credentials->set_payer_id( $payer_id );
					} catch( PayPal_API_Exception $ex ) {
						$this->display_warning( sprintf( __( 'An error occurred while trying to validate your %s API credentials.  Unable to verify that your API credentials are correct.', 'woocommerce-gateway-paypal-express-checkout' ), __( $environment, 'woocommerce-gateway-paypal-express-checkout' ) ) );
					}

					$credential = $api_credentials;
				}
			}

			return $credential;
		}














	/**
	 * Run the plugin.
	 */
	protected function _run() {
		require_once( $this->includes_path . 'functions.php' );
		$this->_load_handlers();
	}

	/**
	 * Callback for activation hook.
	 */
	public function activate() {
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
		// Client.
		require_once( $this->includes_path . 'abstracts/abstract-wc-gateway-ppec-client-credential.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-client-credential-certificate.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-client-credential-signature.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-client.php' );

		// Load handlers.
		require_once( $this->includes_path . 'class-wc-gateway-ppec-settings.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-gateway-loader.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-admin-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-checkout-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-cart-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ppec-ips-handler.php' );

		$this->settings = new WC_Gateway_PPEC_Settings();
		$this->settings->loadSettings();

		$this->gateway_loader = new WC_Gateway_PPEC_Gateway_Loader();
		$this->admin          = new WC_Gateway_PPEC_Admin_Handler();
		$this->checkout       = new WC_Gateway_PPEC_Checkout_Handler();
		$this->cart           = new WC_Gateway_PPEC_Cart_Handler();
		$this->ips            = new WC_Gateway_PPEC_IPS_Handler();

		$this->client = new WC_Gateway_PPEC_Client( $this->settings->get_active_api_credentials(), $this->settings->environment );
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
}
