<?php
/**
 * PayPal Checkout Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Plugin {

	const ALREADY_BOOTSTRAPED      = 1;
	const DEPENDENCIES_UNSATISFIED = 2;
	const NOT_CONNECTED            = 3;

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
			if ( ! in_array( $settings_array['button_size'], array( 'small', 'medium', 'large' ), true ) ) {
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

		$previous_version = get_option( 'wc_ppec_version' );

		// Check the the WC version on plugin update to determine if we need to display a warning.
		// The option was added in 1.6.19 so we only need to check stores updating from before that version. Updating from 1.6.19 or greater would already have it set.
		if ( version_compare( $previous_version, '1.6.19', '<' ) && version_compare( WC_VERSION, '3.0', '<' ) ) {
			update_option( 'wc_ppec_display_wc_3_0_warning', 'true' );
		}

		// Credit messaging is disabled by default for merchants upgrading from < 2.1.
		if ( $previous_version && version_compare( $previous_version, '2.1.0', '<' ) ) {
			$settings = get_option( 'woocommerce_ppec_paypal_settings', array() );

			if ( is_array( $settings ) ) {
				$settings['credit_message_enabled']                = 'no';
				$settings['single_product_credit_message_enabled'] = 'no';
				$settings['mark_credit_message_enabled']           = 'no';

				update_option( 'woocommerce_ppec_paypal_settings', $settings );
			}
		}

		if ( function_exists( 'add_woocommerce_inbox_variant' ) ) {
			add_woocommerce_inbox_variant();
		}

		update_option( 'wc_ppec_version', $new_version );
	}

	/**
	 * Maybe run the plugin.
	 */
	public function maybe_run() {
		register_activation_hook( $this->file, array( $this, 'activate' ) );

		add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
		add_filter( 'allowed_redirect_hosts', array( $this, 'whitelist_paypal_domains_for_redirect' ) );
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), array( $this, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		add_action( 'wp_ajax_ppec_dismiss_notice_message', array( $this, 'ajax_dismiss_notice' ) );

		// Upgrade notice.
		add_action( 'after_plugin_row_' . plugin_basename( $this->file ), array( $this, 'ppec_upgrade_notice' ), 0, 3 );
		add_action( 'wp_ajax_ppec_dismiss_ppec_upgrade_notice', array( $this, 'ppec_upgrade_notice_dismiss_ajax' ) );
	}

	public function bootstrap() {
		try {
			if ( $this->_bootstrapped ) {
				throw new Exception( esc_html__( 'bootstrap() in WooCommerce Gateway PayPal Checkout plugin can only be called once', 'woocommerce-gateway-paypal-express-checkout' ), self::ALREADY_BOOTSTRAPED );
			}

			$this->_check_dependencies();

			if ( $this->needs_update() ) {
				$this->run_updater( $this->version );
			}

			$this->_run();
			$this->_check_credentials();

			$this->_bootstrapped = true;
		} catch ( Exception $e ) {
			if ( in_array( $e->getCode(), array( self::ALREADY_BOOTSTRAPED, self::DEPENDENCIES_UNSATISFIED ) ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
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
			throw new Exception( esc_html__( 'WooCommerce Gateway PayPal Checkout requires WooCommerce to be activated', 'woocommerce-gateway-paypal-express-checkout' ), self::DEPENDENCIES_UNSATISFIED );
		}

		if ( version_compare( WC()->version, '3.2.0', '<' ) ) {
			throw new Exception( esc_html__( 'WooCommerce Gateway PayPal Checkout requires WooCommerce version 3.2.0 or greater', 'woocommerce-gateway-paypal-express-checkout' ), self::DEPENDENCIES_UNSATISFIED );
		}

		if ( ! function_exists( 'curl_init' ) ) {
			throw new Exception( esc_html__( 'WooCommerce Gateway PayPal Checkout requires cURL to be installed on your server', 'woocommerce-gateway-paypal-express-checkout' ), self::DEPENDENCIES_UNSATISFIED );
		}

		$openssl_warning = esc_html__( 'WooCommerce Gateway PayPal Checkout requires OpenSSL >= 1.0.1 to be installed on your server', 'woocommerce-gateway-paypal-express-checkout' );
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
			// Translators: placeholder is the URL of the gateway settings page.
			throw new Exception( sprintf(
				/* translators: 1: anchor tag 2: closing anchor tag */
				esc_html__( 'PayPal Checkout is almost ready. To get started, %1$sconnect your PayPal account%2$s.', 'woocommerce-gateway-paypal-express-checkout' )
			, '<a href="' . esc_url( $setting_link ) . '">', '</a>' ), self::NOT_CONNECTED );
		}
	}

	/**
	 * Run the plugin.
	 */
	protected function _run() {
		require_once $this->includes_path . 'functions.php';
		$this->_load_handlers();
	}

	/**
	 * Callback for activation hook.
	 */
	public function activate() {
		if ( ! isset( $this->settings ) ) {
			require_once $this->includes_path . 'class-wc-gateway-ppec-settings.php';
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
		require_once $this->includes_path . 'class-wc-gateway-ppec-settings.php';
		require_once $this->includes_path . 'class-wc-gateway-ppec-privacy.php';
		require_once $this->includes_path . 'class-wc-gateway-ppec-gateway-loader.php';
		require_once $this->includes_path . 'class-wc-gateway-ppec-admin-handler.php';
		require_once $this->includes_path . 'class-wc-gateway-ppec-checkout-handler.php';
		require_once $this->includes_path . 'class-wc-gateway-ppec-cart-handler.php';
		require_once $this->includes_path . 'class-wc-gateway-ppec-ips-handler.php';
		require_once $this->includes_path . 'abstracts/abstract-wc-gateway-ppec-paypal-request-handler.php';
		require_once $this->includes_path . 'class-wc-gateway-ppec-ipn-handler.php';

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
		require_once $this->includes_path . 'abstracts/abstract-wc-gateway-ppec-client-credential.php';
		require_once $this->includes_path . 'class-wc-gateway-ppec-client-credential-certificate.php';
		require_once $this->includes_path . 'class-wc-gateway-ppec-client-credential-signature.php';
		require_once $this->includes_path . 'class-wc-gateway-ppec-client.php';
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
			$setting_url    = $this->get_admin_setting_link();
			$plugin_links[] = '<a href="' . esc_url( $setting_url ) . '">' . esc_html__( 'Settings', 'woocommerce-gateway-paypal-express-checkout' ) . '</a>';
		}

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Plugin page links to support and documentation
	 *
	 * @since 2.0
	 * @param  array  $links List of plugin links.
	 * @param  string $file Current file.
	 * @return array
	 */
	public function plugin_row_meta( $links, $file ) {
		$row_meta = array();

		if ( false !== strpos( $file, plugin_basename( dirname( __DIR__ ) ) ) ) {
			$row_meta = array(
				'docs'    => sprintf( '<a href="%s" title="%s">%s</a>', esc_url( 'https://docs.woocommerce.com/document/paypal-express-checkout/' ), esc_attr__( 'View Documentation', 'woocommerce-gateway-paypal-express-checkout' ), esc_html__( 'Docs', 'woocommerce-gateway-paypal-express-checkout' ) ),
				'support' => sprintf( '<a href="%s" title="%s">%s</a>', esc_url( 'https://woocommerce.com/my-account/create-a-ticket?select=woocommerce-gateway-paypal-checkout' ), esc_attr__( 'Open a support request at WooCommerce.com', 'woocommerce-gateway-paypal-express-checkout' ), esc_html__( 'Support', 'woocommerce-gateway-paypal-express-checkout' ) ),
			);
		}

		return array_merge( $links, $row_meta );
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

	/**
	 * Displays notice to upgrade to PayPal Payments.
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array $plugin_data An array of plugin data.
	 * @param string $status Status filter currently applied to the plugin list.
	 */
	public function ppec_upgrade_notice( $plugin_file, $plugin_data, $status ) {
		if ( 'yes' === get_transient( 'ppec-upgrade-notice-dismissed' ) ) {
			return;
		}

		// Load styles & scripts required for the notice.
		wp_enqueue_style( 'ppec-upgrade-notice', plugin_dir_url( __DIR__ ) . '/assets/css/admin/ppec-upgrade-notice.css', array(), WC_GATEWAY_PPEC_VERSION );
		wp_enqueue_script( 'ppec-upgrade-notice-js', plugin_dir_url( __DIR__ ) . '/assets/js/admin/ppec-upgrade-notice.js', array(), WC_GATEWAY_PPEC_VERSION, false );

		// Load notice template.
		include_once $this->plugin_path . 'templates/paypal-payments-upgrade-notice.php';
	}

	public function ppec_upgrade_notice_dismiss_ajax() {
		check_ajax_referer( 'ppec-upgrade-notice-dismiss' );
		set_transient( 'ppec-upgrade-notice-dismissed', 'yes', MONTH_IN_SECONDS );
		wp_send_json_success();
	}

	/* Deprecated Functions */

	/**
	 * Shows an admin notice notifying store managers that support for non-spb
	 * on the checkout is being removed in 1.7.0
	 *
	 * @deprecated 1.7.0
	 */
	public function show_spb_notice() {
		_deprecated_function( __METHOD__, '1.7.0' );

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
		/* translators: 1: paragraph tag 2: closing paragraph tag 3: strong tag 4: closing string tag 5: anchor tag 6: closing anchor tag */
		$message = sprintf( esc_html__( '%1$sPayPal Checkout with new %3$sSmart Payment Buttons™%4$s gives your customers the power to pay the way they want without leaving your site.%2$s%1$sThe %3$sexisting buttons will be removed%4$s in the %3$snext release%4$s. Please upgrade to Smart Payment Buttons on the %5$sPayPal Checkout settings page%6$s.%2$s', 'woocommerce-gateway-paypal-express-checkout' ), '<p>', '</p>', '<strong>', '</strong>', '<a href="' . esc_url( $setting_link ) . '">', '</a>' );
		?>
		<div class="notice notice-error">
			<?php echo wp_kses( $message, array( 'a' => array( 'href' => array() ), 'strong' => array(), 'p' => array() ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?>
		</div>
		<?php
	}
}
