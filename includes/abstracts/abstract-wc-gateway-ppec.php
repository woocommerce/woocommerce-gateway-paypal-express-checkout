<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WC_Gateway_PPEC
 */
abstract class WC_Gateway_PPEC extends WC_Payment_Gateway {

	protected $buyer_email = false;
	public static $use_buyer_email = true;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->has_fields  = false;
		$this->icon        = 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-small.png';
		$this->supports[]  = 'refunds';

		$this->method_title       = __( 'PayPal Express Checkout', 'woocommerce-gateway-paypal-express-checkout' );
		$this->method_description = __( 'Allow customers to conveniently checkout directly with PayPal.', 'woocommerce-gateway-paypal-express-checkout' );

		if ( empty( $_GET['woo-paypal-return'] ) ) {
			$this->order_button_text  = __( 'Continue to payment', 'woocommerce-gateway-paypal-express-checkout' );
		}

		wc_gateway_ppec()->ips->maybe_received_credentials();

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled      = $this->get_option( 'enabled', 'yes' );
		$this->button_size  = $this->get_option( 'button_size', 'large' );
		$this->environment  = $this->get_option( 'environment', 'live' );
		$this->mark_enabled = 'yes' === $this->get_option( 'mark_enabled', 'no' );
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );

		if ( 'live' === $this->environment ) {
			$this->api_username    = $this->get_option( 'api_username' );
			$this->api_password    = $this->get_option( 'api_password' );
			$this->api_signature   = $this->get_option( 'api_signature' );
			$this->api_certificate = $this->get_option( 'api_certificate' );
			$this->api_subject     = $this->get_option( 'api_subject' );
		} else {
			$this->api_username    = $this->get_option( 'sandbox_api_username' );
			$this->api_password    = $this->get_option( 'sandbox_api_password' );
			$this->api_signature   = $this->get_option( 'sandbox_api_signature' );
			$this->api_certificate = $this->get_option( 'sandbox_api_certificate' );
			$this->api_subject     = $this->get_option( 'sandbox_api_subject' );
		}

		$this->debug                      = 'yes' === $this->get_option( 'debug', 'no' );
		$this->invoice_prefix             = $this->get_option( 'invoice_prefix', 'WC-' );
		$this->instant_payments           = 'yes' === $this->get_option( 'instant_payments', 'no' );
		$this->require_billing            = 'yes' === $this->get_option( 'require_billing', 'no' );
		$this->paymentaction              = $this->get_option( 'paymentaction', 'sale' );
		$this->logo_image_url             = $this->get_option( 'logo_image_url' );
		$this->subtotal_mismatch_behavior = $this->get_option( 'subtotal_mismatch_behavior', 'add' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Do we need to auto-select this payment method?
		// TODO: Move this out to particular handler instead of gateway
		if ( ! is_admin() ) {
			$session = WC()->session->get( 'paypal' );
			if ( null != $session && is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) && $session->checkout_completed && $session->expiry_time >= time() && $session->payerID ) {
				if ( $session->checkout_details && is_a( $session->checkout_details, 'PayPal_Checkout_Details' ) && ( is_checkout() || is_ajax() ) && self::$use_buyer_email ) {
					$this->buyer_email = $session->checkout_details->payer_details->email;
					$this->title      .= ' - ' . esc_html( $this->buyer_email );
					$this->description = '';
				}

				$posted = array(
					'billing_first_name'  => $session->checkout_details->payer_details->first_name,
					'billing_last_name'   => $session->checkout_details->payer_details->last_name,
					'billing_email'       => $session->checkout_details->payer_details->email,
					'billing_phone'       => $session->checkout_details->payer_details->phone_number,
					'billing_country'     => $session->checkout_details->payer_details->country
				);

				if ( $session->shipping_required ) {
					if ( false === strpos( $session->checkout_details->payments[0]->shipping_address->getName(), ' ' ) ) {
						$posted['shipping_first_name'] = $session->checkout_details->payer_details->first_name;
						$posted['shipping_last_name']  = $session->checkout_details->payer_details->last_name;
						$posted['shipping_company']    = $session->checkout_details->payments[0]->shipping_address->getName();
					} else {
						$name = explode( ' ', $session->checkout_details->payments[0]->shipping_address->getName() );
						$posted['shipping_first_name'] = $name[0];
						array_shift( $name );
						$posted['shipping_last_name'] = implode( ' ', $name );
					}

					$posted = array_merge( $posted, array(
						'shipping_company'          => $session->checkout_details->payer_details->business_name,
						'shipping_address_1'        => $session->checkout_details->payments[0]->shipping_address->getStreet1(),
						'shipping_address_2'        => $session->checkout_details->payments[0]->shipping_address->getStreet2(),
						'shipping_city'             => $session->checkout_details->payments[0]->shipping_address->getCity(),
						'shipping_state'            => $session->checkout_details->payments[0]->shipping_address->getState(),
						'shipping_postcode'         => $session->checkout_details->payments[0]->shipping_address->getZip(),
						'shipping_country'          => $session->checkout_details->payments[0]->shipping_address->getCountry(),
						'ship_to_different_address' => true
					) );

				} else {
					$posted['ship_to_different_address'] = false;
				}

				$_POST = array_merge( $_POST, $posted );

				// Make sure the proper option is selected based on what the buyer picked
				if ( ! ( $session->using_ppc xor is_a( $this, 'WC_Gateway_PPEC_With_PayPal_Credit' ) ) ) {
					$this->chosen = true;
				} else {
					$this->chosen = false;
				}
			}
		}
	}

	public function before_checkout_billing_form( $checkout ) {
		$checkout->checkout_fields['billing'] = array(
			'billing_first_name' => $checkout->checkout_fields['billing']['billing_first_name'],
			'billing_last_name'  => $checkout->checkout_fields['billing']['billing_last_name'],
			'billing_country'    => $checkout->checkout_fields['billing']['billing_country'],
			'billing_email'      => $checkout->checkout_fields['billing']['billing_email'],
			'billing_phone'      => $checkout->checkout_fields['billing']['billing_phone']
		);
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = include( dirname( dirname( __FILE__ ) ) . '/settings/settings-ppec.php' );
	}

	public function process_payment( $order_id ) {

		$checkout = wc_gateway_ppec()->checkout;

		// Check the session.  Are we going to just complete an existing payment, or are we going to
		// send the user over PayPal to pay?

		$session = WC()->session->get( 'paypal' );
		if ( ! $session || ! is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) ||
				! $session->checkout_completed || $session->expiry_time < time() ||
				! $session->payerID ) {
			// Redirect them over to PayPal.
			try {
				$redirect_url = $checkout->start_checkout_from_checkout( $order_id, 'ppec_paypal_credit' === $this->id );

				return array(
					'result'   => 'success',
					'redirect' => $redirect_url,
				);
			} catch( PayPal_API_Exception $e ) {
				$final_output = '<ul>';
				foreach ( $e->errors as $error ) {
					$final_output .= '<li>' . $error->maptoBuyerFriendlyError() . '</li>';
				}
				$final_output .= '</ul>';
				wc_add_notice( __( 'Payment error:', 'woocommerce-gateway-paypal-express-checkout' )  . $final_output, 'error' );
			}
		} else {
			// We have a token we can work with.  Just complete the payment now.
			try {
				$payment_details = $checkout->completePayment( $order_id, $session->token, $session->payerID );
				$transaction_id = $payment_details->payments[0]->transaction_id;
				$payment_status = $payment_details->payments[0]->payment_status;
				$pending_reason = $payment_details->payments[0]->pending_reason;
				$order = wc_get_order( $order_id );

				if ( 'Pending' === $payment_status && 'authorization' === $pending_reason ) {
					update_post_meta( $order->id, '_ppec_charge_captured', 'no' );
					add_post_meta( $order->id, '_transaction_id', $transaction_id, true );

					// Mark as on-hold
					$order->update_status( 'on-hold', sprintf( __( 'PayPal Express Checkout charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-paypal-express-checkout' ), $transaction_id ) );

					$order->reduce_order_stock();

				} else {
					// TODO: Handle things like eChecks, giropay, etc.
					$order->payment_complete( $transaction_id );
					$order->add_order_note( sprintf( __( 'PayPal Express Checkout transaction completed; transaction ID = %s', 'woocommerce-gateway-paypal-express-checkout' ), $transaction_id ) );

					update_post_meta( $order->id, '_ppec_charge_captured', 'yes' );
				}

				unset( WC()->session->paypal );

				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			} catch( PayPal_Missing_Session_Exception $e ) {
				// For some reason, our session data is missing.  Generally, if we've made it this far, this shouldn't happen.
				wc_add_notice( __( 'Sorry, an error occurred while trying to process your payment.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
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
					// We're explicitly not loading settings here because we don't want in-context checkout
					// shown when we're redirecting back to PP for a funding source error.
					$session->checkout_completed = false;
					$session->leftFrom = 'order';
					$session->order_id = $order_id;
					WC()->session->paypal = $session;
					return array(
						'result' => 'success',
						'redirect' => wc_gateway_ppec()->settings->get_paypal_redirect_url( $session->token, true )
					);
				} else {
					$final_output = '<ul>';
					foreach ( $e->errors as $error ) {
						$final_output .= '<li>' . $error->maptoBuyerFriendlyError() . '</li>';
					}
					$final_output .= '</ul>';
					wc_add_notice( __( 'Payment error:', 'woocommerce-gateway-paypal-express-checkout' ) . $final_output, 'error' );
					return;
				}
			}
		}
	}

	/**
	 * Get info about uploaded certificate.
	 * @param  string $cert_string
	 * @return string
	 */
	private function get_certificate_info( $cert_string ) {
		if ( ! strlen( $cert_string ) ) {
			return __( 'No API certificate on file.', 'woocommerce-gateway-paypal-express-checkout' );
		}

		$cert = @openssl_x509_read( $cert_string );
		$out  = '';

		if ( false !== $cert ) {
			$certinfo = openssl_x509_parse( $cert );
			if ( false !== $certinfo ) {
				$valid_until = $certinfo['validTo_time_t'];
				if ( $valid_until < time() ) {
					// Display in red if the cert is already expired
					$expires = '<span style="color: red;">' . __( 'expired on %s', 'woocommerce-gateway-paypal-express-checkout' ) . '</span>';
				} elseif ( $valid_until < ( time() - 2592000 ) ) {
					// Also display in red if the cert is going to expire in the next 30 days
					$expires = '<span style="color: red;">' . __( 'expires on %s', 'woocommerce-gateway-paypal-express-checkout' ) . '</span>';
				} else {
					// Otherwise just display a normal message
					$expires = __( 'expires on %s', 'woocommerce-gateway-paypal-express-checkout' );
				}

				$expires = sprintf( $expires, date_i18n( get_option( 'date_format' ), $valid_until ) );
				$out = sprintf( __( 'Certificate belongs to API username %1$s; %2$s', 'woocommerce-gateway-paypal-express-checkout' ), $certinfo['subject']['CN'], $expires );
			} else {
				$out = __( 'The certificate on file is not valid.', 'woocommerce-gateway-paypal-express-checkout' );
			}
		}

		return $out;
	}

	/**
	 * Do some additonal validation before saving options via the API.
	 */
	public function process_admin_options() {
		// Validate logo
		$logo_image_url = wc_clean( $_POST['woocommerce_ppec_paypal_logo_image_url'] );

		if ( ! empty( $logo_image_url ) && ! preg_match( '/https?:\/\/[a-zA-Z0-9][a-zA-Z0-9.-]+[a-zA-Z0-9](\/[a-zA-Z0-9.\/?&%#]*)?/', $logo_image_url ) ) {
			WC_Admin_Settings::add_error( __( 'Error: The logo image URL you provided is not valid and cannot be used.', 'woocommerce-gateway-paypal-express-checkout' ) );
			unset( $_POST['woocommerce_ppec_paypal_logo_image_url'] );
		}

		// If a certificate has been uploaded, read the contents and save that string instead.
		if ( array_key_exists( 'woocommerce_ppec_paypal_api_certificate', $_FILES )
			&& array_key_exists( 'tmp_name', $_FILES['woocommerce_ppec_paypal_api_certificate'] )
			&& array_key_exists( 'size', $_FILES['woocommerce_ppec_paypal_api_certificate'] )
			&& $_FILES['woocommerce_ppec_paypal_api_certificate']['size'] ) {

			$_POST['woocommerce_ppec_paypal_api_certificate'] = base64_encode( file_get_contents( $_FILES['woocommerce_ppec_paypal_api_certificate']['tmp_name'] ) );
			unlink( $_FILES['woocommerce_ppec_paypal_api_certificate']['tmp_name'] );
			unset( $_FILES['woocommerce_ppec_paypal_api_certificate'] );
		} else {
			$_POST['woocommerce_ppec_paypal_api_certificate'] = $this->get_option( 'api_certificate' );
		}

		if ( array_key_exists( 'woocommerce_ppec_paypal_sandbox_api_certificate', $_FILES )
			&& array_key_exists( 'tmp_name', $_FILES['woocommerce_ppec_paypal_sandbox_api_certificate'] )
			&& array_key_exists( 'size', $_FILES['woocommerce_ppec_paypal_sandbox_api_certificate'] )
			&& $_FILES['woocommerce_ppec_paypal_sandbox_api_certificate']['size'] ) {

			$_POST['woocommerce_ppec_paypal_sandbox_api_certificate'] = base64_encode( file_get_contents( $_FILES['woocommerce_ppec_paypal_sandbox_api_certificate']['tmp_name'] ) );
			unlink( $_FILES['woocommerce_ppec_paypal_sandbox_api_certificate']['tmp_name'] );
			unset( $_FILES['woocommerce_ppec_paypal_sandbox_api_certificate'] );
		} else {
			$_POST['woocommerce_ppec_paypal_sandbox_api_certificate'] = $this->get_option( 'sandbox_api_certificate' );
		}

		parent::process_admin_options();

		// Validate credentials
		$this->validate_active_credentials();
	}

	/**
	 * Validate the provided credentials.
	 */
	protected function validate_active_credentials() {
		$settings = wc_gateway_ppec()->settings->load_settings( true );
		$creds    = $settings->get_active_api_credentials();

		if ( ! empty( $creds->get_username() ) ) {

			if ( empty( $creds->get_password() ) ) {
				WC_Admin_Settings::add_error( sprintf( __( 'Error: You must enter a %s API password.' ), __( $settings->get_environment(), 'woocommerce-gateway-paypal-express-checkout' ) ) );
				return false;
			}

			if ( is_a( $creds, 'WC_Gateway_PPEC_Client_Credential_Signature' ) && ! empty( $creds->get_signature() ) ) {

				try {

					$payer_id = wc_gateway_ppec()->client->test_api_credentials( $creds, $settings->get_environment() );

					if ( ! $payer_id ) {
						WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s credentials you provided are not valid.  Please double-check that you entered them correctly and try again.', 'woocommerce-gateway-paypal-express-checkout' ), __( $settings->get_environment(), 'woocommerce-gateway-paypal-express-checkout' ) ) );
						return false;
					}

				} catch( PayPal_API_Exception $ex ) {
					$this->display_warning( sprintf( __( 'An error occurred while trying to validate your %s API credentials.  Unable to verify that your API credentials are correct.', 'woocommerce-gateway-paypal-express-checkout' ), __( $settings->get_environment(), 'woocommerce-gateway-paypal-express-checkout' ) ) );
				}

			} elseif ( is_a( $creds, 'WC_Gateway_PPEC_Client_Credential_Certificate' ) && ! empty( $creds->get_certificate() ) ) {

				$cert = @openssl_x509_read( $creds->get_certificate() );

				if ( false === $cert ) {
					WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s API certificate is not valid.', 'woocommerce-gateway-paypal-express-checkout' ), __( $settings->get_environment(), 'woocommerce-gateway-paypal-express-checkout' ) ) );
					return false;
				}

				$cert_info   = openssl_x509_parse( $cert );
				$valid_until = $cert_info['validTo_time_t'];

				if ( $valid_until < time() ) {
					WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s API certificate has expired.', 'woocommerce-gateway-paypal-express-checkout' ), __( $settings->get_environment(), 'woocommerce-gateway-paypal-express-checkout' ) ) );
					return false;
				}

				if ( $cert_info['subject']['CN'] != $creds->get_username() ) {
					WC_Admin_Settings::add_error( __( 'Error: The API username does not match the name in the API certificate.  Make sure that you have the correct API certificate.', 'woocommerce-gateway-paypal-express-checkout' ) );
					return false;
				}

				try {

					$payer_id = wc_gateway_ppec()->client->test_api_credentials( $creds, $settings->get_environment() );

					if ( ! $payer_id ) {
						WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s credentials you provided are not valid.  Please double-check that you entered them correctly and try again.', 'woocommerce-gateway-paypal-express-checkout' ), __( $settings->get_environment(), 'woocommerce-gateway-paypal-express-checkout' ) ) );
						return false;
					}

				} catch( PayPal_API_Exception $ex ) {
					$this->display_warning( sprintf( __( 'An error occurred while trying to validate your %s API credentials.  Unable to verify that your API credentials are correct.', 'woocommerce-gateway-paypal-express-checkout' ), __( $settings->get_environment(), 'woocommerce-gateway-paypal-express-checkout' ) ) );
				}

			} else {

				WC_Admin_Settings::add_error( sprintf( __( 'Error: You must provide a %s API signature or certificate.', 'woocommerce-gateway-paypal-express-checkout' ), __( $settings->get_environment(), 'woocommerce-gateway-paypal-express-checkout' ) ) );
				return false;
			}
		}
	}

	/**
	 * Refunds.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( 0 == $amount || null == $amount ) {
			return new WP_Error( 'paypal_refund_error', __( 'Refund Error: You need to specify a refund amount.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		// load up refundable_txns from Post Meta
		// loop through each transaction to compile list of txns that are able to be refunded
		// process refunds against each txn in the list until full amount of refund is reached
		// first loop through, try to find a transaction that equals the refund amount being requested
		$txnData = get_post_meta( $order_id, '_woo_pp_txnData', true );
		$didRefund = false;

		foreach ( $txnData['refundable_txns'] as $key => $value ) {
			$refundableAmount = $value['amount'] - $value['refunded_amount'];


			if ( $amount == $refundableAmount ) {
				if ( 0 == $value['refunded_amount'] ) {
					$refundType = 'Full';
				} else {
					$refundType = 'Partial';
				}

				try {
					$refundTxnID = WC_Gateway_PPEC_Refund::refund_order( $order, $amount, $refundType, $reason, $order->get_order_currency() );
					$txnData['refundable_txns'][ $key ]['refunded_amount'] += $amount;
					$order->add_order_note( sprintf( $refundTxnID, __( 'PayPal refund completed; transaction ID = %s', 'woocommerce-gateway-paypal-express-checkout' ), $refundTxnID ) );
					update_post_meta( $order_id, '_woo_pp_txnData', $txnData );

					return true;

				} catch( PayPal_API_Exception $e ) {
					foreach ( $e->errors as $error ) {
						$final_output .= sprintf( __( 'Error: %1$s - %2$s', 'woocommerce-gateway-paypal-express-checkout' ), $error->error_code, $error->long_message );
					}

					return new WP_Error( 'paypal_refund_error', $final_output );
				}
			}
		}

		foreach ( $txnData['refundable_txns'] as $key => $value ) {
			$refundableAmount = $value['amount'] - $value['refunded_amount'];

			if ( $amount < $refundableAmount ) {

				try {
					$refundTxnID = WC_Gateway_PPEC_Refund::refund_order( $order, $amount, 'Partial', $reason, $order->get_order_currency() );
					$txnData['refundable_txns'][ $key ]['refunded_amount'] += $amount;
					$order->add_order_note( sprintf( __( 'PayPal refund completed; transaction ID = %s', 'woocommerce-gateway-paypal-express-checkout' ), $refundTxnID ) );
					update_post_meta( $order_id, '_woo_pp_txnData', $txnData );

					return true;

				} catch( PayPal_API_Exception $e ) {
					foreach ( $e->errors as $error ) {
						$final_output .= sprintf( __( 'Error: %1$s - %2$s', 'woocommerce-gateway-paypal-express-checkout' ), $error->error_code, $error->long_message );
					}

					return new WP_Error( 'paypal_refund_error', $final_output );
				}

			}
		}

		$totalRefundableAmount = 0;
		foreach ( $txnData['refundable_txns'] as $key => $value ) {
			$refundableAmount = $value['amount'] - $value['refunded_amount'];
			$totalRefundableAmount += $refundableAmount;
		}

		if ( $totalRefundableAmount < $amount ) {
			if ( 0 == $totalRefundableAmount ) {
				return new WP_Error( 'paypal_refund_error', __( 'Refund Error: All transactions have been fully refunded. There is no amount left to refund', 'woocommerce-gateway-paypal-express-checkout' ) );
			} else {
				return new WP_Error( 'paypal_refund_error', sprintf( __( 'Refund Error: The requested refund amount is too large. The refund amount must be less than or equal to %s.', 'woocommerce-gateway-paypal-express-checkout' ), html_entity_decode( get_woocommerce_currency_symbol() ) . $totalRefundableAmount ) );
			}
		} else {
			$total_to_refund = $amount;

			foreach ( $txnData['refundable_txns'] as $key => $value ) {
				$refundableAmount = $value['amount'] - $value['refunded_amount'];

				if ( $refundableAmount > $total_to_refund ) {
					$amount_to_refund = $total_to_refund;
				} else {
					$amount_to_refund = $refundableAmount;
				}

				if ( 0 < $amount_to_refund ) {
					if ( 0 == $value['refunded_amount'] && $amount_to_refund == $value['amount'] ) {
						$refundType = 'Full';
					} else {
						$refundType = 'Partial';
					}

					try {
						$refundTxnID = WC_Gateway_PPEC_Refund::refund_order( $order, $amount_to_refund, $refundType, $reason, $order->get_order_currency() );
						$total_to_refund -= $amount_to_refund;
						$txnData['refundable_txns'][ $key ]['refunded_amount'] += $amount_to_refund;
						$order->add_order_note( sprintf( __( 'PayPal refund completed; transaction ID = %s', 'woocommerce-gateway-paypal-express-checkout' ), $refundTxnID ) );
						update_post_meta( $order_id, '_woo_pp_txnData', $txnData );

						return true;
					} catch( PayPal_API_Exception $e ) {
						foreach ( $e->errors as $error ) {
							$final_output .= sprintf( __( 'Error: %1$s - %2$s', 'woocommerce-gateway-paypal-express-checkout' ), $error->error_code, $error->long_message );
						}

						return new WP_Error( 'paypal_refund_error', $final_output );
					}
				}
			}
		}
	}

	/**
	 * Get the transaction URL.
	 *
	 * @param  WC_Order $order
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		if ( 'sandbox' === $this->environment ) {
			$this->view_transaction_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
		} else {
			$this->view_transaction_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
		}
		return parent::get_transaction_url( $order );
	}

	/**
	 * Check if this gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		$client = wc_gateway_ppec()->client;

		if ( ! $client->get_payer_id() ) {
			return false;
		}

		return true;
	}
}
