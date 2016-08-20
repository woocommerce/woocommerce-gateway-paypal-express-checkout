<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_PPEC_Cart_Handler handles button display in the cart.
 *
 * @see https://developer.paypal.com/docs/classic/ipn/integration-guide/IPNImplementation/
 * @since 1.1.2
 */
class WC_Gateway_PPEC_IPN_Handler extends WC_Gateway_PPEC_PayPal_Request_Handler {

	/**
	 * Handle the IPN request.
	 */
	public function handle() {
		add_action( 'woocommerce_api_wc_gateway_ppec', array( $this, 'check_request' ) );
		add_action( 'woocommerce_paypal_express_checkout_valid_ipn_request', array( $this, 'handle_valid_ipn' ) );
	}

	/**
	 * Check request.
	 */
	public function check_request() {
		try {

			if ( empty( $_POST ) ) {
				throw new Exception( esc_html__( 'Empty POST data.', 'woocommerce-gateway-paypal-express-checkout' ) );
			}

			if ( $this->is_valid_ipn_request( $_POST ) ) {
				wc_gateway_ppec_log( 'IPN request is valid according to PayPal.' );
				do_action( 'woocommerce_paypal_express_checkout_valid_ipn_request', wp_unslash( $_POST ) );
				exit;
			} else {
				wc_gateway_ppec_log( 'IPN request is NOT valid according to PayPal.' );
				throw new Exception( esc_html__( 'Invalid IPN request.' , 'woocommerce-gateway-paypal-express-checkout' ) );
			}

		} catch ( Exception $e ) {

			wp_die( $e->getMessage(), esc_html__( 'PayPal IPN Request Failure', 'woocommerce-gateway-paypal-express-checkout' ), array( 'response' => 500 ) );

		}
	}

	/**
	 * Check with PayPal whether posted data is valid IPN request.
	 *
	 * @throws Exception
	 *
	 * @param array $posted_data Posted data
	 * @return bool True if posted_data is valid IPN request
	 */
	public function is_valid_ipn_request( array $posted_data ) {
		wc_gateway_ppec_log( sprintf( '%s: %s', __FUNCTION__, 'Checking IPN request validity' ) );

		$ipn_request = array(
			'cmd' => '_notify-validate',
		);
		$ipn_request += wp_unslash( $posted_data );

		$params = array(
			'body'        => $ipn_request,
			'timeout'     => 60,
			'httpversion' => '1.1',
			'compress'    => false,
			'decompress'  => false,
			'user-agent'  => get_class( $this->gateway ),
		);

		// Post back to PayPal to check validity of IPN request.
		$response = wp_safe_remote_post( $this->get_validator_url(), $params );

		wc_gateway_ppec_log( sprintf( '%s: %s: %s', __FUNCTION__, 'Verify IPN request', print_r( $params, true ) ) );
		wc_gateway_ppec_log( sprintf( '%s: %s: %s', __FUNCTION__, 'Response for the IPN request', print_r( $response, true ) ) );

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		return (
			$response['response']['code'] >= 200
			&&
			$response['response']['code'] < 300
			&&
			strstr( $response['body'], 'VERIFIED' )
		);
	}

	/**
	 * Handle valid IPN request.
	 *
	 * @param array $posted_data Posted data
	 */
	public function handle_valid_ipn( $posted_data ) {
		if ( ! empty( $posted_data['custom'] ) && ( $order = $this->get_paypal_order( $posted_data['custom'] ) ) ) {

			// Lowercase returned variables.
			$posted_data['payment_status'] = strtolower( $posted_data['payment_status'] );

			// Sandbox fix.
			if ( isset( $posted_data['test_ipn'] ) && 1 == $posted_data['test_ipn'] && 'pending' == $posted_data['payment_status'] ) {
				$posted_data['payment_status'] = 'completed';
			}

			wc_gateway_ppec_log( 'Found order #' . $order->id );
			wc_gateway_ppec_log( 'Payment status: ' . $posted_data['payment_status'] );

			if ( method_exists( $this, 'payment_status_' . $posted_data['payment_status'] ) ) {
				call_user_func( array( $this, 'payment_status_' . $posted_data['payment_status'] ), $order, $posted_data );
			}

		} else {
			wc_gateway_ppec_log( sprintf( '%s: %s', __FUNCTION__, 'No order data being passed' ) );
		}
	}

	/**
	 * Check for a valid transaction type.
	 *
	 * @param string $txn_type Transaction type
	 */
	protected function validate_transaction_type( $txn_type ) {
		$accepted_types = array( 'cart', 'instant', 'express_checkout', 'web_accept', 'masspay', 'send_money' );
		if ( ! in_array( strtolower( $txn_type ), $accepted_types ) ) {
			wc_gateway_ppec_log( 'Aborting, Invalid type:' . $txn_type );
			exit;
		}
	}

	/**
	 * Check currency from IPN matches the order.
	 *
	 * @param WC_Order $order Order object
	 * @param string $currency Currency
	 */
	protected function validate_currency( $order, $currency ) {
		if ( $order->order_currency !== $currency ) {
			wc_gateway_ppec_log( 'Payment error: Currencies do not match (sent "' . $order->order_currency . '" | returned "' . $currency . '")' );
			// Put this order on-hold for manual checking.
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: PayPal currencies do not match (code %s).', 'woocommerce-gateway-paypal-express-checkout' ), $currency ) );
			exit;
		}
	}

	/**
	 * Check payment amount from IPN matches the order.
	 *
	 * @param WC_Order $order Order object
	 * @param int $amount Amount
	 */
	protected function validate_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, '.', '' ) != number_format( $amount, 2, '.', '' ) ) {
			wc_gateway_ppec_log( 'Payment error: Amounts do not match (gross ' . $amount . ')' );
			// Put this order on-hold for manual checking.
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: PayPal amounts do not match (gross %s).', 'woocommerce-gateway-paypal-express-checkout' ), $amount ) );
			exit;
		}
	}

	/**
	 * Handle a completed payment.
	 *
	 * @param WC_Order $order Order object
	 * @param array $posted_data Posted data
	 */
	protected function payment_status_completed( $order, $posted_data ) {
		if ( $order->has_status( array( 'processing', 'completed' ) ) ) {
			wc_gateway_ppec_log( 'Aborting, Order #' . $order->id . ' is already complete.' );
			exit;
		}

		$this->validate_transaction_type( $posted_data['txn_type'] );
		$this->validate_currency( $order, $posted_data['mc_currency'] );
		$this->validate_amount( $order, $posted_data['mc_gross'] );
		$this->save_paypal_meta_data( $order, $posted_data );

		if ( 'completed' === $posted_data['payment_status'] ) {
			$this->payment_complete( $order, ( ! empty( $posted_data['txn_id'] ) ? wc_clean( $posted_data['txn_id'] ) : '' ), __( 'IPN payment completed', 'woocommerce-gateway-paypal-express-checkout' ) );
			if ( ! empty( $posted_data['mc_fee'] ) ) {
				// Log paypal transaction fee.
				update_post_meta( $order->id, 'PayPal Transaction Fee', wc_clean( $posted_data['mc_fee'] ) );
			}
		} else {
			if ( 'authorization' === $posted_data['pending_reason'] ) {
				$this->payment_on_hold( $order, __( 'Payment authorized. Change payment status to processing or complete to capture funds.', 'woocommerce-gateway-paypal-express-checkout' ) );
			} else {
				$this->payment_on_hold( $order, sprintf( __( 'Payment pending (%s).', 'woocommerce-gateway-paypal-express-checkout' ), $posted_data['pending_reason'] ) );
			}
		}
	}

	/**
	 * Handle a pending payment.
	 *
	 * @param WC_Order $order Order object
	 * @param array $posted_data Posted data
	 */
	protected function payment_status_pending( $order, $posted_data ) {
		$this->payment_status_completed( $order, $posted_data );
	}

	/**
	 * Handle a failed payment.
	 *
	 * @param WC_Order $order Order object
	 * @param array $posted_data Posted data
	 */
	protected function payment_status_failed( $order, $posted_data ) {
		$order->update_status( 'failed', sprintf( __( 'Payment %s via IPN.', 'woocommerce-gateway-paypal-express-checkout' ), wc_clean( $posted_data['payment_status'] ) ) );
	}

	/**
	 * Handle a denied payment.
	 *
	 * @param WC_Order $order Order object
	 * @param array $posted_data Posted data
	 */
	protected function payment_status_denied( $order, $posted_data ) {
		$this->payment_status_failed( $order, $posted_data );
	}

	/**
	 * Handle an expired payment.
	 *
	 * @param WC_Order $order Order object
	 * @param array $posted_data Posted data
	 */
	protected function payment_status_expired( $order, $posted_data ) {
		$this->payment_status_failed( $order, $posted_data );
	}

	/**
	 * Handle a voided payment.
	 *
	 * @param WC_Order $order Order object
	 * @param array $posted_data Posted data
	 */
	protected function payment_status_voided( $order, $posted_data ) {
		$this->payment_status_failed( $order, $posted_data );
	}

	/**
	 * Handle a refunded order.
	 *
	 * @param WC_Order $order Order object
	 * @param array $posted_data Posted data
	 */
	protected function payment_status_refunded( $order, $posted_data ) {
		// Only handle full refunds, not partial.
		if ( $order->get_total() == ( $posted_data['mc_gross'] * -1 ) ) {
			// Mark order as refunded.
			$order->update_status( 'refunded', sprintf( __( 'Payment %s via IPN.', 'woocommerce-gateway-paypal-express-checkout' ), strtolower( $posted_data['payment_status'] ) ) );
			$this->send_ipn_email_notification(
				sprintf( __( 'Payment for order %s refunded', 'woocommerce-gateway-paypal-express-checkout' ), '<a class="link" href="' . esc_url( admin_url( 'post.php?post=' . $order->id . '&action=edit' ) ) . '">' . $order->get_order_number() . '</a>' ),
				sprintf( __( 'Order #%s has been marked as refunded - PayPal reason code: %s', 'woocommerce-gateway-paypal-express-checkout' ), $order->get_order_number(), $posted_data['reason_code'] )
			);
		}
	}

	/**
	 * Handle a reveral.
	 *
	 * @param WC_Order $order Order object
	 * @param array $posted_data Posted data
	 */
	protected function payment_status_reversed( $order, $posted_data ) {
		$order->update_status( 'on-hold', sprintf( __( 'Payment %s via IPN.', 'woocommerce-gateway-paypal-express-checkout' ), wc_clean( $posted_data['payment_status'] ) ) );
		$this->send_ipn_email_notification(
			sprintf( __( 'Payment for order %s reversed', 'woocommerce-gateway-paypal-express-checkout' ), '<a class="link" href="' . esc_url( admin_url( 'post.php?post=' . $order->id . '&action=edit' ) ) . '">' . $order->get_order_number() . '</a>' ),
			sprintf( __( 'Order #%s has been marked on-hold due to a reversal - PayPal reason code: %s', 'woocommerce-gateway-paypal-express-checkout' ), $order->get_order_number(), wc_clean( $posted_data['reason_code'] ) )
		);
	}

	/**
	 * Handle a cancelled reveral.
	 *
	 * @param WC_Order $order Order object
	 * @param array $posted_data Posted data
	 */
	protected function payment_status_canceled_reversal( $order, $posted_data ) {
		$this->send_ipn_email_notification(
			sprintf( __( 'Reversal cancelled for order #%s', 'woocommerce-gateway-paypal-express-checkout' ), $order->get_order_number() ),
			sprintf( __( 'Order #%s has had a reversal cancelled. Please check the status of payment and update the order status accordingly here: %s', 'woocommerce-gateway-paypal-express-checkout' ), $order->get_order_number(), esc_url( admin_url( 'post.php?post=' . $order->id . '&action=edit' ) ) )
		);
	}

	/**
	 * Save important data from the IPN to the order.
	 *
	 * @param WC_Order $order Order object
	 * @param array $posted_data Posted data
	 */
	protected function save_paypal_meta_data( $order, $posted_data ) {
		if ( ! empty( $posted_data['payer_email'] ) ) {
			update_post_meta( $order->id, 'Payer PayPal address', wc_clean( $posted_data['payer_email'] ) );
		}
		if ( ! empty( $posted_data['first_name'] ) ) {
			update_post_meta( $order->id, 'Payer first name', wc_clean( $posted_data['first_name'] ) );
		}
		if ( ! empty( $posted_data['last_name'] ) ) {
			update_post_meta( $order->id, 'Payer last name', wc_clean( $posted_data['last_name'] ) );
		}
		if ( ! empty( $posted_data['payment_type'] ) ) {
			update_post_meta( $order->id, 'Payment type', wc_clean( $posted_data['payment_type'] ) );
		}
		if ( ! empty( $posted_data['txn_id'] ) ) {
			update_post_meta( $order->id, '_transaction_id', wc_clean( $posted_data['txn_id'] ) );
		}
		if ( ! empty( $posted_data['payment_status'] ) ) {
			update_post_meta( $order->id, '_paypal_status', wc_clean( $posted_data['payment_status'] ) );
		}
	}

	/**
	 * Send a notification to the user handling orders.
	 *
	 * @param string $subject Email subject
	 * @param string $message Email message
	 */
	protected function send_ipn_email_notification( $subject, $message ) {
		$new_order_settings = get_option( 'woocommerce_new_order_settings', array() );
		$mailer             = WC()->mailer();
		$message            = $mailer->wrap_message( $subject, $message );
		$mailer->send( ! empty( $new_order_settings['recipient'] ) ? $new_order_settings['recipient'] : get_option( 'admin_email' ), strip_tags( $subject ), $message );
	}

	/**
	 * Get IPN request validator URL.
	 *
	 * @return string PayPal IPN request validator URL
	 */
	public function get_validator_url() {
		$url = 'https://www.paypal.com/cgi-bin/webscr';
		if ( 'sandbox' === $this->gateway->environment ) {
			$url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		}

		return $url;
	}
}
