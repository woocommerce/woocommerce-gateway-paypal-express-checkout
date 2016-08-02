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

		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );

		add_filter( 'woocommerce_order_actions', array( $this, 'add_capture_charge_order_action' ) );
		add_action( 'woocommerce_order_action_ppec_capture_charge', array( $this, 'maybe_capture_charge' ) );

		add_action( 'load-woocommerce_page_wc-settings', array( $this, 'maybe_redirect_to_ppec_settings' ) );
	}

	public function add_capture_charge_order_action( $actions ) {
		if ( ! isset( $_REQUEST['post'] ) ) {
			return $actions;
		}

		$order = wc_get_order( $_REQUEST['post'] );

		// bail if the order wasn't paid for with this gateway
		if ( 'ppec_paypal' !== $order->payment_method || 'pending' !== get_post_meta( $order->id, '_paypal_status', true ) ) {
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
	 * with the PayPal Express Checkout class.
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
		if ( empty( $simplify_commerce_options ) || ( "no" === $simplify_commerce_options['enabled'] ) ) {
			if ( 'wc_gateway_simplify_commerce' !== $current_section ) {
				$sections_to_remove[] = 'wc_gateway_simplify_commerce';
			}
			if ( 'wc_addons_gateway_simplify_commerce' !== $current_section ) {
				$sections_to_remove[] = 'wc_addons_gateway_simplify_commerce';
			}
		}

		foreach( $sections_to_remove as $section_to_remove ) {
			unset( $sections[$section_to_remove] );
		}

		return $sections;

	}

	public function maybe_capture_charge( $order ) {
		if ( ! is_object( $order ) ) {
			$order = wc_get_order( $order );
		}

		$this->capture_payment( $order->id );

		return true;
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @param int $order_id
	 */
	public function capture_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'ppec_paypal' === $order->payment_method ) {
			$trans_id = get_post_meta( $order_id, '_transaction_id', true );
			$trans_details = wc_gateway_ppec()->client->get_transaction_details( array( 'TRANSACTIONID' => $trans_id ) );

			if ( $trans_id && $this->is_authorized_only( $trans_details ) ) {
				$params['AUTHORIZATIONID'] = $trans_id;
				$params['AMT'] = floatval( $order->order_total );
				$params['COMPLETETYPE'] = 'Complete';

				$result = wc_gateway_ppec()->client->do_express_checkout_capture( $params );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to capture charge!', 'woocommerce-gateway-paypal-express-checkout' ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'PayPal Express Checkout charge complete (Charge ID: %s)', 'woocommerce-gateway-paypal-express-checkout' ), $trans_id ) );
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
	 * Cancel authorization
	 *
	 * @param  int $order_id
	 */
	public function cancel_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'ppec_paypal' === $order->payment_method ) {
			$trans_id = get_post_meta( $order_id, '_transaction_id', true );
			$trans_details = wc_gateway_ppec()->client->get_transaction_details( array( 'TRANSACTIONID' => $trans_id ) );

			if ( $trans_id && $this->is_authorized_only( $trans_details ) ) {
				$params['AUTHORIZATIONID'] = $trans_id;

				$result = wc_gateway_ppec()->client->do_express_checkout_void( $params );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to void charge!', 'woocommerce-gateway-paypal-express-checkout' ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'PayPal Express Checkout charge voided (Charge ID: %s)', 'woocommerce-gateway-paypal-express-checkout' ), $trans_id) );
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
}
