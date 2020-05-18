<?php
/**
 * Plugin bootstrapper.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Admin_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_update_options_general', array( $this, 'force_zero_decimal' ) );
		add_action( 'admin_notices', array( $this, 'show_decimal_warning' ) );

		// defer this until for next release.
		// add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_checkout_sections' ) );

		add_action( 'woocommerce_order_status_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_authorization' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'cancel_authorization' ) );

		add_filter( 'woocommerce_order_actions', array( $this, 'add_capture_charge_order_action' ) );
		add_action( 'woocommerce_order_action_ppec_capture_charge', array( $this, 'maybe_capture_charge' ) );

		add_action( 'load-woocommerce_page_wc-settings', array( $this, 'maybe_redirect_to_ppec_settings' ) );
		add_action( 'load-woocommerce_page_wc-settings', array( $this, 'maybe_reset_api_credentials' ) );

		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'display_order_fee_and_payout' ) );
		add_action( 'admin_notices', array( $this, 'show_wc_version_warning' ) );
	}

	public function add_capture_charge_order_action( $actions ) {
		if ( ! isset( $_REQUEST['post'] ) ) {
			return $actions;
		}

		$order = wc_get_order( $_REQUEST['post'] );

		if ( empty( $order ) ) {
			return $actions;
		}

		$old_wc         = version_compare( WC_VERSION, '3.0', '<' );
		$order_id       = $old_wc ? $order->id : $order->get_id();
		$payment_method = $old_wc ? $order->payment_method : $order->get_payment_method();
		$paypal_status  = $old_wc ? get_post_meta( $order_id, '_paypal_status', true ) : $order->get_meta( '_paypal_status', true );

		// bail if the order wasn't paid for with this gateway
		if ( 'ppec_paypal' !== $payment_method || 'pending' !== $paypal_status ) {
			return $actions;
		}

		if ( ! is_array( $actions ) ) {
			$actions = array();
		}

		$actions['ppec_capture_charge'] = esc_html__( 'Capture Charge', 'woocommerce-gateway-paypal-express-checkout' );

		return $actions;
	}

	/**
	 * Force zero decimal on specific currencies.
	 */
	public function force_zero_decimal() {
		$settings = wc_gateway_ppec()->settings;
		if ( $settings->currency_has_decimal_restriction() ) {
			update_option( 'woocommerce_price_num_decimals', 0 );
			update_option( 'wc_gateway_ppce_display_decimal_msg', true );
		}
	}

	/**
	 * Show decimal warning.
	 */
	public function show_decimal_warning() {
		if ( get_option( 'wc_gateway_ppce_display_decimal_msg', false ) ) {
			?>
			<div class="updated fade">
				<p>
					<strong><?php _e( 'NOTE: PayPal does not accept decimal places for the currency in which you are transacting.  The "Number of Decimals" option in WooCommerce has automatically been set to 0 for you.', 'woocommerce-gateway-paypal-express-checkout' ); ?></strong>
				</p>
			</div>
			<?php
			delete_option( 'wc_gateway_ppce_display_decimal_msg' );
		}
	}

	/**
	 * Prevent PPEC Credit showing up in the admin, because it shares its settings
	 * with the PayPal Checkout class.
	 *
	 * @param array $sections List of sections in checkout
	 *
	 * @return array Sections in checkout
	 */
	public function filter_checkout_sections( $sections ) {

		$paypal_sections = array(
			'wc_gateway_ppec_with_paypal',
		);

		$card_sections = array(
			'wc_gateway_ppec_with_paypal_credit',
		);

		$current_section = isset( $_GET['section'] ) ? $_GET['section'] : '';

		// If the current section is a paypal section, remove the card section,
		// otherwise, remove the paypal section
		$sections_to_remove = in_array( $current_section, $paypal_sections ) ? $card_sections : $paypal_sections;

		// And, let's also remove simplify commerce from the sections if it is not enabled and it is not the
		// current section. (Note: The option will be empty if it has never been enabled)

		$simplify_commerce_options = get_option( 'woocommerce_simplify_commerce_settings', array() );
		if ( empty( $simplify_commerce_options ) || ( 'no' === $simplify_commerce_options['enabled'] ) ) {
			if ( 'wc_gateway_simplify_commerce' !== $current_section ) {
				$sections_to_remove[] = 'wc_gateway_simplify_commerce';
			}
			if ( 'wc_addons_gateway_simplify_commerce' !== $current_section ) {
				$sections_to_remove[] = 'wc_addons_gateway_simplify_commerce';
			}
		}

		foreach ( $sections_to_remove as $section_to_remove ) {
			unset( $sections[ $section_to_remove ] );
		}

		return $sections;

	}

	public function maybe_capture_charge( $order ) {
		if ( ! is_object( $order ) ) {
			$order = wc_get_order( $order );
		}

		$order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$this->capture_payment( $order_id );

		return true;
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @param int $order_id
	 */
	public function capture_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$old_wc         = version_compare( WC_VERSION, '3.0', '<' );
		$payment_method = $old_wc ? $order->payment_method : $order->get_payment_method();
		$transaction_id = get_post_meta( $order_id, '_transaction_id', true );

		if ( 'ppec_paypal' === $payment_method && $transaction_id ) {

			$trans_details = wc_gateway_ppec()->client->get_transaction_details( array( 'TRANSACTIONID' => $transaction_id ) );

			if ( $this->is_authorized_only( $trans_details ) ) {
				$order_total = $old_wc ? $order->order_total : $order->get_total();

				$params['AUTHORIZATIONID'] = $transaction_id;
				$params['AMT']             = floatval( $order_total );
				$params['CURRENCYCODE']    = $old_wc ? $order->order_currency : $order->get_currency();
				$params['COMPLETETYPE']    = 'Complete';

				$result = wc_gateway_ppec()->client->do_express_checkout_capture( $params );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to capture charge!', 'woocommerce-gateway-paypal-express-checkout' ) . ' ' . $result->get_error_message() );
				} else {
					update_post_meta( $order_id, '_paypal_status', ! empty( $trans_details['PAYMENTSTATUS'] ) ? $trans_details['PAYMENTSTATUS'] : 'completed' );

					if ( ! empty( $result['TRANSACTIONID'] ) ) {
						update_post_meta( $order_id, '_transaction_id', $result['TRANSACTIONID'] );
					}

					$order->add_order_note( sprintf( __( 'PayPal Checkout charge complete (Charge ID: %s)', 'woocommerce-gateway-paypal-express-checkout' ), $transaction_id ) );
				}
			}
		}
	}

	/**
	 * Checks to see if the transaction can be captured
	 *
	 * @param array $trans_details
	 */
	public function is_authorized_only( $trans_details = array() ) {
		if ( ! is_wp_error( $trans_details ) && ! empty( $trans_details ) ) {
			if ( 'Pending' === $trans_details['PAYMENTSTATUS'] && 'authorization' === $trans_details['PENDINGREASON'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Cancel authorization (if one is present)
	 *
	 * @param  int $order_id
	 */
	public function cancel_authorization( $order_id ) {
		$order = wc_get_order( $order_id );
		$old_wc = version_compare( WC_VERSION, '3.0', '<' );
		$payment_method = $old_wc ? $order->payment_method : $order->get_payment_method();

		if ( 'ppec_paypal' === $payment_method ) {

			$trans_id = get_post_meta( $order_id, '_transaction_id', true );
			$trans_details = wc_gateway_ppec()->client->get_transaction_details( array( 'TRANSACTIONID' => $trans_id ) );

			if ( $trans_id && $this->is_authorized_only( $trans_details ) ) {
				$params['AUTHORIZATIONID'] = $trans_id;

				$result = wc_gateway_ppec()->client->do_express_checkout_void( $params );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to void charge!', 'woocommerce-gateway-paypal-express-checkout' ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'PayPal Checkout charge voided (Charge ID: %s)', 'woocommerce-gateway-paypal-express-checkout' ), $trans_id ) );
				}
			}
		}
	}

	/**
	 * Get admin URL for this gateway setting.
	 *
	 * @deprecated
	 *
	 * @return string URL
	 */
	public function gateway_admin_url( $gateway_class ) {
		_deprecated_function( 'WC_Gateway_PPEC_Admin_Handler::gateway_admin_url', '1.0.4', 'wc_gateway_ppec()->get_admin_setting_link' );

		return wc_gateway_ppec()->get_admin_setting_link();
	}

	/**
	 * Maybe redirect to wc_gateway_ppec_with_paypal from PayPal standard
	 * checkout settings.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_ppec_settings() {
		if ( ! wc_gateway_ppec()->settings->enabled ) {
			return;
		}

		if ( empty( $_GET['tab'] ) || empty( $_GET['section'] ) ) {
			return;
		}

		if ( 'checkout' === $_GET['tab'] && 'wc_gateway_paypal' === $_GET['section'] ) {
			$redirect = add_query_arg( array( 'section' => 'wc_gateway_ppec_with_paypal' ) );
			wp_safe_redirect( $redirect );
		}
	}

	/**
	 * Reset API credentials if merchant clicked the reset credential link.
	 *
	 * When API credentials empty, the connect button will be displayed again,
	 * allowing merchant to reconnect with other account.
	 *
	 * When WooCommerce Branding is active, this handler may not be invoked as
	 * screen ID may evaluates to something else.
	 *
	 * @since 1.2.0
	 */
	public function maybe_reset_api_credentials() {
		if ( empty( $_GET['reset_ppec_api_credentials'] ) ) {
			return;
		}

		if ( empty( $_GET['reset_nonce'] ) || ! wp_verify_nonce( $_GET['reset_nonce'], 'reset_ppec_api_credentials' ) ) {
			return;
		}

		$settings = wc_gateway_ppec()->settings;
		$env      = $settings->_environment;
		if ( ! empty( $_GET['environment'] ) ) {
			$env = $_GET['environment'];
		}
		$prefix = 'sandbox' === $env ? 'sandbox_' : '';

		foreach ( array( 'api_username', 'api_password', 'api_signature', 'api_certificate' ) as $key ) {
			$key = $prefix . $key;
			$settings->{$key} = '';
		}

		// Save environment too as when it switches to another env and merchant
		// click the reset they'd expect to save the environment too.
		$settings->environment = 'sandbox' === $env ? 'sandbox' : 'live';

		$settings->save();

		wp_safe_redirect( wc_gateway_ppec()->get_admin_setting_link() );
	}

	/**
	 * Displays the PayPal fee and the net total of the transaction without the PayPal charges
	 *
	 * @since 1.6.6
	 *
	 * @param int $order_id
	 */
	public function display_order_fee_and_payout( $order_id ) {
		$order = wc_get_order( $order_id );

		$old_wc         = version_compare( WC_VERSION, '3.0', '<' );
		$payment_method = $old_wc ? $order->payment_method : $order->get_payment_method();
		$paypal_fee     = wc_gateway_ppec_get_transaction_fee( $order );
		$order_currency = $old_wc ? $order->order_currency : $order->get_currency();
		$order_total    = $old_wc ? $order->order_total : $order->get_total();

		if ( 'ppec_paypal' !== $payment_method || ! is_numeric( $paypal_fee ) ) {
			return;
		}

		$net = $order_total - $paypal_fee;

		?>

		<tr>
			<td class="label ppec-fee">
				<?php echo wc_help_tip( __( 'This represents the fee PayPal collects for the transaction.', 'woocommerce-gateway-paypal-express-checkout' ) ); ?>
				<?php esc_html_e( 'PayPal Fee:', 'woocommerce-gateway-paypal-express-checkout' ); ?>
			</td>
			<td width="1%"></td>
			<td class="total">
				-&nbsp;<?php echo wc_price( $paypal_fee, array( 'currency' => $order_currency ) ); ?>
			</td>
		</tr>
		<tr>
			<td class="label ppec-payout">
				<?php echo wc_help_tip( __( 'This represents the net total that will be credited to your PayPal account. This may be in a different currency than is set in your PayPal account.', 'woocommerce-gateway-paypal-express-checkout' ) ); ?>
				<?php esc_html_e( 'PayPal Payout:', 'woocommerce-gateway-paypal-express-checkout' ); ?>
			</td>
			<td width="1%"></td>
			<td class="total">
				<?php echo wc_price( $net, array( 'currency' => $order_currency ) ); ?>
			</td>
		</tr>

		<?php
	}

	/**
	 * Displays an admin notice for sites running a WC version pre 3.0.
	 * The WC minimum supported version will be increased to WC 3.0 in Q1 2020.
	 *
	 * @since 1.6.19
	 */
	public static function show_wc_version_warning() {

		if ( 'true' !== get_option( 'wc_ppec_display_wc_3_0_warning' ) ) {
			return;
		}

		// Check if the notice needs to be dismissed.
		$wc_updated = version_compare( WC_VERSION, '3.0', '>=' );
		$dismissed  = isset( $_GET['wc_ppec_hide_3_0_notice'], $_GET['_wc_ppec_notice_nonce'] ) && wp_verify_nonce( $_GET['_wc_ppec_notice_nonce'], 'wc_ppec_hide_wc_notice_nonce' );

		if ( $wc_updated || $dismissed ) {
			delete_option( 'wc_ppec_display_wc_3_0_warning' );
			return;
		}
		?>
		<div class="error">
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc_ppec_hide_3_0_notice', 'true' ), 'wc_ppec_hide_wc_notice_nonce', '_wc_ppec_notice_nonce' ) ); ?>" class="woocommerce-message-close notice-dismiss" style="position:relative;float:right;padding:9px 0px 9px 9px 9px;text-decoration:none;"></a>
			<p>
			<?php printf( __(
				'%1$sWarning!%2$s PayPal Checkout will drop support for WooCommerce %3$s in a soon to be released update. To continue using PayPal Checkout please %4$supdate to %1$sWooCommerce 3.0%2$s or greater%5$s.', 'woocommerce-gateway-paypal-express-checkout' ),
				'<strong>', '</strong>',
				WC_VERSION,
				'<a href="' . admin_url( 'plugins.php' ) . '">', '</a>'
			); ?>
			</p>
		</div>
		<?php
	}
}
