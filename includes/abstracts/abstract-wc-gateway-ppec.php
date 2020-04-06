<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Gateway_PPEC
 */
abstract class WC_Gateway_PPEC extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->has_fields         = false;
		$this->supports[]         = 'refunds';
		$this->method_title       = __( 'PayPal Checkout', 'woocommerce-gateway-paypal-express-checkout' );
		$this->method_description = __( 'Allow customers to conveniently checkout directly with PayPal.', 'woocommerce-gateway-paypal-express-checkout' );

		wc_gateway_ppec()->ips->maybe_received_credentials();

		$this->init_form_fields();
		$this->init_settings();

		// With 1.7.0, override the use_spb option pulled from the DB to the value set in WC_Gateway_PPEC_Settings
		$this->settings['use_spb'] = wc_gateway_ppec()->settings->use_spb;

		$this->title        = $this->method_title;
		$this->description  = '';
		$this->enabled      = $this->get_option( 'enabled', 'yes' );
		$this->button_size  = $this->get_option( 'button_size', 'large' );
		$this->environment  = $this->get_option( 'environment', 'live' );
		$this->mark_enabled = 'yes' === $this->get_option( 'mark_enabled', 'no' );

		if ( 'live' === $this->environment ) {
			$this->api_username    = $this->get_option( 'api_username' );
			$this->api_password    = $this->get_option( 'api_password' );
			$this->api_signature   = $this->get_option( 'api_signature' );
			$this->api_certificate = $this->get_option( 'api_certificate' );
			$this->api_subject     = $this->get_option( 'api_subject' );

			$this->api_client_id   = $this->get_option( 'api_client_id' );
			$this->api_secret      = $this->get_option( 'api_secret' );
		} else {
			$this->api_username    = $this->get_option( 'sandbox_api_username' );
			$this->api_password    = $this->get_option( 'sandbox_api_password' );
			$this->api_signature   = $this->get_option( 'sandbox_api_signature' );
			$this->api_certificate = $this->get_option( 'sandbox_api_certificate' );
			$this->api_subject     = $this->get_option( 'sandbox_api_subject' );

			$this->api_client_id   = $this->get_option( 'sandbox_api_client_id' );
			$this->api_secret      = $this->get_option( 'sandbox_api_secret' );
		}

		$this->debug                      = 'yes' === $this->get_option( 'debug', 'no' );
		$this->invoice_prefix             = $this->get_option( 'invoice_prefix', '' );
		$this->instant_payments           = 'yes' === $this->get_option( 'instant_payments', 'no' );
		$this->require_billing            = 'yes' === $this->get_option( 'require_billing', 'no' );
		$this->paymentaction              = $this->get_option( 'paymentaction', 'sale' );
		$this->subtotal_mismatch_behavior = $this->get_option( 'subtotal_mismatch_behavior', 'add' );
		$this->use_ppc                    = false;

		if ( empty( $_GET['woo-paypal-return'] ) && 'yes' !== $this->get_option( 'use_spb', 'yes' ) ) {
			$this->order_button_text = __( 'Continue to payment', 'woocommerce-gateway-paypal-express-checkout' );
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Change gateway name if session is active
		if ( ! is_admin() ) {
			if ( wc_gateway_ppec()->checkout->is_started_from_checkout_page() ) {
				$this->title        = $this->get_option( 'title' );
				$this->description  = $this->get_option( 'description' );
			}
		} else {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		add_filter( 'woocommerce_ajax_get_endpoint', array( $this, 'pass_return_args_to_ajax' ), 10, 2 );
	}

	/**
	 * Pass woo return args to AJAX endpoint when the checkout updates from the frontend
	 * so that the order button gets set correctly.
	 *
	 * @param  string $request Optional.
	 * @return string
	 */
	public function pass_return_args_to_ajax( $request ) {
		if ( isset( $_GET['woo-paypal-return'] ) ) {
			$request .= '&woo-paypal-return=1';
		}

		return $request;
	}

	/**
	 * Enqueues admin scripts.
	 *
	 * @since 1.5.2
	 */
	public function enqueue_scripts() {
		// Image upload.
		wp_enqueue_media();

		wp_enqueue_script( 'wc-gateway-ppec-settings', wc_gateway_ppec()->plugin_url . 'assets/js/wc-gateway-ppec-settings.js', array( 'jquery' ), wc_gateway_ppec()->version, true );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = include( dirname( dirname( __FILE__ ) ) . '/settings/settings-ppec.php' );
	}

	/**
	 * Process payments.
	 *
	 * @param int $order_id Order ID
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$checkout = wc_gateway_ppec()->checkout;
		$order    = wc_get_order( $order_id );
		$session  = WC()->session->get( 'paypal' );

		// Redirect them over to PayPal if they have no current session (this
		// is for PayPal Mark).
		if ( $checkout->is_started_from_checkout_page() ) {
			try {
				return array(
					'result'   => 'success',
					'redirect' => $checkout->start_checkout_from_order( $order_id, $this->use_ppc ),
				);
			} catch ( PayPal_API_Exception $e ) {
				wc_add_notice( $e->getMessage(), 'error' );
			}
		} else {
			try {
				// Get details
				$checkout_details = $checkout->get_checkout_details( $session->token );

				$checkout_context = array(
					'order_id'   => $order_id,
				);
				if ( $checkout->needs_billing_agreement_creation( $checkout_context ) ) {
					$checkout->create_billing_agreement( $order, $checkout_details );
				}

				// Complete the payment now.
				$checkout->do_payment( $order, $session->token, $session->payer_id );

				// Clear Cart
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} catch ( PayPal_Missing_Session_Exception $e ) {

				// For some reason, our session data is missing. Generally,
				// if we've made it this far, this shouldn't happen.
				wc_add_notice( __( 'Sorry, an error occurred while trying to process your payment. Please try again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
			} catch ( PayPal_API_Exception $e ) {

				// Did we get a 10486 or 10422 back from PayPal?  If so, this
				// means we need to send the buyer back over to PayPal to have
				// them pick out a new funding method.
				$error_codes = wp_list_pluck( $e->errors, 'error_code' );

				if ( in_array( '10486', $error_codes ) || in_array( '10422', $error_codes ) ) {
					$session->checkout_completed = false;
					$session->source             = 'order';
					$session->order_id           = $order_id;
					WC()->session->set( 'paypal', $session );

					return array(
						'result'   => 'success',
						'redirect' => wc_gateway_ppec()->settings->get_paypal_redirect_url( $session->token, true ),
					);
				} else {
					do_action( 'wc_gateway_ppec_process_payment_error', $e, $order );
					wc_add_notice( $e->getMessage(), 'error' );
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

		$cert = @openssl_x509_read( $cert_string ); // @codingStandardsIgnoreLine
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

		// Validate credentials.
		$this->validate_active_credentials();
	}

	/**
	 * Validate the provided credentials.
	 */
	protected function validate_active_credentials() {
		$settings = wc_gateway_ppec()->settings->load( true );
		$creds    = $settings->get_active_api_credentials();

		$username = $creds->get_username();
		$password = $creds->get_password();

		if ( ! empty( $username ) ) {

			if ( empty( $password ) ) {
				WC_Admin_Settings::add_error( __( 'Error: You must enter API password.', 'woocommerce-gateway-paypal-express-checkout' ) );
				return false;
			}

			if ( is_a( $creds, 'WC_Gateway_PPEC_Client_Credential_Signature' ) && $creds->get_signature() ) {

				try {

					$payer_id = wc_gateway_ppec()->client->test_api_credentials( $creds, $settings->get_environment() );

					if ( ! $payer_id ) {
						WC_Admin_Settings::add_error( __( 'Error: The API credentials you provided are not valid.  Please double-check that you entered them correctly and try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
						return false;
					}
				} catch ( PayPal_API_Exception $ex ) {

					WC_Admin_Settings::add_error( __( 'An error occurred while trying to validate your API credentials.  Unable to verify that your API credentials are correct.', 'woocommerce-gateway-paypal-express-checkout' ) );
				}
			} elseif ( is_a( $creds, 'WC_Gateway_PPEC_Client_Credential_Certificate' ) && $creds->get_certificate() ) {

				$cert = @openssl_x509_read( $creds->get_certificate() ); // @codingStandardsIgnoreLine

				if ( false === $cert ) {
					WC_Admin_Settings::add_error( __( 'Error: The API certificate is not valid.', 'woocommerce-gateway-paypal-express-checkout' ) );
					return false;
				}

				$cert_info   = openssl_x509_parse( $cert );
				$valid_until = $cert_info['validTo_time_t'];

				if ( $valid_until < time() ) {
					WC_Admin_Settings::add_error( __( 'Error: The API certificate has expired.', 'woocommerce-gateway-paypal-express-checkout' ) );
					return false;
				}

				if ( $cert_info['subject']['CN'] != $creds->get_username() ) {
					WC_Admin_Settings::add_error( __( 'Error: The API username does not match the name in the API certificate.  Make sure that you have the correct API certificate.', 'woocommerce-gateway-paypal-express-checkout' ) );
					return false;
				}

				try {

					$payer_id = wc_gateway_ppec()->client->test_api_credentials( $creds, $settings->get_environment() );

					if ( ! $payer_id ) {
						WC_Admin_Settings::add_error( __( 'Error: The API credentials you provided are not valid.  Please double-check that you entered them correctly and try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
						return false;
					}
				} catch ( PayPal_API_Exception $ex ) {
					WC_Admin_Settings::add_error( __( 'An error occurred while trying to validate your API credentials.  Unable to verify that your API credentials are correct.', 'woocommerce-gateway-paypal-express-checkout' ) );
				}

			} else {

				WC_Admin_Settings::add_error( __( 'Error: You must provide API signature or certificate.', 'woocommerce-gateway-paypal-express-checkout' ) );
				return false;
			}

			$settings_array = (array) get_option( 'woocommerce_ppec_paypal_settings', array() );

			if ( 'yes' === $settings_array['require_billing'] ) {
				$is_account_enabled_for_billing_address = false;

				try {
					$is_account_enabled_for_billing_address = wc_gateway_ppec()->client->test_for_billing_address_enabled( $creds, $settings->get_environment() );
				} catch ( PayPal_API_Exception $ex ) {
					$is_account_enabled_for_billing_address = false;
				}

				if ( ! $is_account_enabled_for_billing_address ) {
					$settings_array['require_billing'] = 'no';
					update_option( 'woocommerce_ppec_paypal_settings', $settings_array );
					WC_Admin_Settings::add_error( __( 'The "require billing address" option is not enabled by your account and has been disabled.', 'woocommerce-gateway-paypal-express-checkout' ) );
				}
			}
		}
	}

	/**
	 * Process refund.
	 *
	 * @param int    $order_id Order ID
	 * @param float  $amount   Order amount
	 * @param string $reason   Refund reason
	 *
	 * @return boolean True or false based on success, or a WP_Error object.
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
		$old_wc = version_compare( WC_VERSION, '3.0', '<' );
		$txn_data = $old_wc ? get_post_meta( $order_id, '_woo_pp_txnData', true ) : $order->get_meta( '_woo_pp_txnData', true );
		$order_currency = $old_wc ? $order->order_currency : $order->get_currency();

		foreach ( $txn_data['refundable_txns'] as $key => $value ) {
			$refundable_amount = $value['amount'] - $value['refunded_amount'];

			if ( $amount == $refundable_amount ) {
				$refund_type = ( 0 == $value['refunded_amount'] ) ? 'Full' : 'Partial';

				try {
					$refund_txn_id = WC_Gateway_PPEC_Refund::refund_order( $order, $amount, $refund_type, $reason, $order_currency );
					$txn_data['refundable_txns'][ $key ]['refunded_amount'] += $amount;
					$order->add_order_note( sprintf( __( 'PayPal refund completed; transaction ID = %s', 'woocommerce-gateway-paypal-express-checkout' ), $refund_txn_id ) );
					if ( $old_wc ) {
						update_post_meta( $order_id, '_woo_pp_txnData', $txn_data );
					} else {
						$order->update_meta_data( '_woo_pp_txnData', $txn_data );
					}

					return true;

				} catch ( PayPal_API_Exception $e ) {
					return new WP_Error( 'paypal_refund_error', $e->getMessage() );
				}
			}
		}

		foreach ( $txn_data['refundable_txns'] as $key => $value ) {
			$refundable_amount = $value['amount'] - $value['refunded_amount'];

			if ( $amount < $refundable_amount ) {

				try {
					$refund_txn_id = WC_Gateway_PPEC_Refund::refund_order( $order, $amount, 'Partial', $reason, $order_currency );
					$txn_data['refundable_txns'][ $key ]['refunded_amount'] += $amount;
					$order->add_order_note( sprintf( __( 'PayPal refund completed; transaction ID = %s', 'woocommerce-gateway-paypal-express-checkout' ), $refund_txn_id ) );
					if ( $old_wc ) {
						update_post_meta( $order_id, '_woo_pp_txnData', $txn_data );
					} else {
						$order->update_meta_data( '_woo_pp_txnData', $txn_data );
					}

					return true;

				} catch ( PayPal_API_Exception $e ) {
					return new WP_Error( 'paypal_refund_error', $e->getMessage() );
				}

			}
		}

		$total_refundable_amount = 0;
		foreach ( $txn_data['refundable_txns'] as $key => $value ) {
			$refundable_amount = $value['amount'] - $value['refunded_amount'];
			$total_refundable_amount += $refundable_amount;
		}

		if ( $total_refundable_amount < $amount ) {
			if ( 0 == $total_refundable_amount ) {
				return new WP_Error( 'paypal_refund_error', __( 'Refund Error: All transactions have been fully refunded. There is no amount left to refund', 'woocommerce-gateway-paypal-express-checkout' ) );
			} else {
				return new WP_Error( 'paypal_refund_error', sprintf( __( 'Refund Error: The requested refund amount is too large. The refund amount must be less than or equal to %s.', 'woocommerce-gateway-paypal-express-checkout' ), html_entity_decode( get_woocommerce_currency_symbol() ) . $total_refundable_amount ) );
			}
		} else {
			$total_to_refund = $amount;

			foreach ( $txn_data['refundable_txns'] as $key => $value ) {
				$refundable_amount = $value['amount'] - $value['refunded_amount'];

				if ( $refundable_amount > $total_to_refund ) {
					$amount_to_refund = $total_to_refund;
				} else {
					$amount_to_refund = $refundable_amount;
				}

				if ( 0 < $amount_to_refund ) {
					$refund_type = 'Partial';
					if ( 0 == $value['refunded_amount'] && $amount_to_refund == $value['amount'] ) {
						$refund_type = 'Full';
					}

					try {
						$refund_txn_id = WC_Gateway_PPEC_Refund::refund_order( $order, $amount_to_refund, $refund_type, $reason, $order_currency );
						$total_to_refund -= $amount_to_refund;
						$txn_data['refundable_txns'][ $key ]['refunded_amount'] += $amount_to_refund;
						$order->add_order_note( sprintf( __( 'PayPal refund completed; transaction ID = %s', 'woocommerce-gateway-paypal-express-checkout' ), $refund_txn_id ) );
						if ( $old_wc ) {
							update_post_meta( $order_id, '_woo_pp_txnData', $txn_data );
						} else {
							$order->update_meta_data( '_woo_pp_txnData', $txn_data );
						}

						return true;
					} catch ( PayPal_API_Exception $e ) {
						return new WP_Error( 'paypal_refund_error', $e->getMessage() );
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
		return 'yes' === $this->enabled;
	}

	/**
	 * Generate Image HTML.
	 *
	 * @param  mixed $key
	 * @param  mixed $data
	 * @since  1.5.0
	 * @return string
	 */
	public function generate_image_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data  = wp_parse_args( $data, $defaults );
		$value = $this->get_option( $key );

		// Hide show add remove buttons.
		$maybe_hide_add_style    = '';
		$maybe_hide_remove_style = '';

		// For backwards compatibility (customers that already have set a url)
		$value_is_url            = filter_var( $value, FILTER_VALIDATE_URL ) !== false;

		if ( empty( $value ) || $value_is_url ) {
			$maybe_hide_remove_style = 'display: none;';
		} else {
			$maybe_hide_add_style = 'display: none;';
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); ?></label>
			</th>

			<td class="image-component-wrapper">
				<div class="image-preview-wrapper">
					<?php
					if ( ! $value_is_url ) {
						echo wp_get_attachment_image( $value, 'thumbnail' );
					} else {
						echo sprintf( __( 'Already using URL as image: %s', 'woocommerce-gateway-paypal-express-checkout' ), $value );
					}
					?>
				</div>

				<button
					class="button image_upload"
					data-field-id="<?php echo esc_attr( $field_key ); ?>"
					data-media-frame-title="<?php echo esc_attr( __( 'Select a image to upload', 'woocommerce-gateway-paypal-express-checkout' ) ); ?>"
					data-media-frame-button="<?php echo esc_attr( __( 'Use this image', 'woocommerce-gateway-paypal-express-checkout' ) ); ?>"
					data-add-image-text="<?php echo esc_attr( __( 'Add image', 'woocommerce-gateway-paypal-express-checkout' ) ); ?>"
					style="<?php echo esc_attr( $maybe_hide_add_style ); ?>"
				>
					<?php echo esc_html__( 'Add image', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</button>

				<button
					class="button image_remove"
					data-field-id="<?php echo esc_attr( $field_key ); ?>"
					style="<?php echo esc_attr( $maybe_hide_remove_style ); ?>"
				>
					<?php echo esc_html__( 'Remove image', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</button>

				<input type="hidden"
					name="<?php echo esc_attr( $field_key ); ?>"
					id="<?php echo esc_attr( $field_key ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
				/>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}
}
