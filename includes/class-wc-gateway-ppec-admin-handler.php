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
		add_action( 'admin_notices', array( $this, 'maybe_show_unsupported_paypal_credit' ) );

		add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_checkout_sections' ) );

		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );

		add_filter( 'woocommerce_order_actions', array( $this, 'add_capture_charge_order_action' ) );
		add_action( 'woocommerce_order_action_ppec_capture_charge', array( $this, 'maybe_capture_charge' ) );
	}

	public function add_capture_charge_order_action() {
		if ( ! isset( $_REQUEST['post'] ) ) {
			return;
		}

		$order = wc_get_order( $_REQUEST['post'] );

		// bail if the order wasn't paid for with this gateway
		if ( 'ppec_paypal' !== $order->payment_method ) {
			return;
		}

		$trans_id = get_post_meta( $order->id, '_transaction_id', true );
		$captured = get_post_meta( $order->id, '_ppec_charge_captured', true );

		if ( 'yes' === $captured ) {
			return;
		}

		return array( 'ppec_capture_charge' => esc_html__( 'Capture Charge', 'woocommerce-gateway-paypal-express-checkout' ) );
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

	public function maybe_show_unsupported_paypal_credit() {
		$ppc_enabled = wc_gateway_ppec()->settings->ppcEnabled;
		if ( $ppc_enabled && 'US' !== WC()->countries->get_base_country() ) {
			?>
			<div class="notice notice-warning fade">
				<p>
					<strong><?php _e( 'NOTE: PayPal Credit is not supported on your base location.', 'woocommerce-gateway-paypal-express-checkout' ); ?></strong>
				</p>
			</div>
			<?php
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
			'wc_gateway_ppec_with_card',
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
			$captured = get_post_meta( $order_id, '_ppec_charge_captured', true );

			if ( $trans_id && $captured == 'no' ) {
				$params['AUTHORIZATIONID'] = $trans_id;
				$params['AMT'] = floatval( $order->order_total );
				$params['COMPLETETYPE'] = 'Complete';

				$result = wc_gateway_ppec()->client->do_express_checkout_capture( $params );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to capture charge!', 'woocommerce-gateway-paypal-express-checkout' ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'PayPal Express Checkout charge complete (Charge ID: %s)', 'woocommerce-gateway-paypal-express-checkout' ), $trans_id ) );

					update_post_meta( $order->id, '_ppec_charge_captured', 'yes' );
				}
			}
		}
	}

	/**
	 * Cancel pre-auth on refund/cancellation
	 *
	 * @param  int $order_id
	 */
	public function cancel_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'ppec_paypal' === $order->payment_method ) {
			$trans_id = get_post_meta( $order_id, '_transaction_id', true );
			$captured = get_post_meta( $order_id, '_ppec_charge_captured', true );

			if ( $trans_id && 'no' === $captured ) {
				$params['AUTHORIZATIONID'] = $trans_id;

				$result = wc_gateway_ppec()->client->do_express_checkout_void( $params );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to refund charge!', 'woocommerce-gateway-paypal-express-checkout' ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'PayPal Express Checkout charge voided (Charge ID: %s)', 'woocommerce-gateway-paypal-express-checkout' ), $trans_id) );
					delete_post_meta( $order->id, '_ppec_charge_captured' );
				}
			}
		}
	}
}
