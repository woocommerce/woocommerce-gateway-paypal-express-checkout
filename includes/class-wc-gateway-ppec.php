<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WC_Gateway_PPEC extends WC_Payment_Gateway {
	public function __construct() {

		$this->id                 = 'paypal_express_checkout';
		$this->has_fields         = true;
		$this->method_title       = __( 'PayPal Express Checkout', 'woocommerce-gateway-ppec' );
		$this->method_description = __( 'Process payments quickly and securely with PayPal.', 'woocommerce-gateway-ppec' );
		$this->supports[]         = 'refunds';

		$this->init_form_fields();
		$this->init_settings();

		$mark_size = $this->get_option( 'mark_size' );

		$this->icon = 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-' . $mark_size . '.png';
		$this->enabled = $this->enabled ? 'yes' : 'no';

		$this->set_payment_title();

		$this->setup_paypal_customer_details();

		// Hooks
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_sections_checkout' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
		add_action( 'woocommerce_update_options_general', array( $this, 'force_zero_decimal' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

	}

	/**
	 * Getting paypal customer details and setting it up within WC
	 */
	public function setup_paypal_customer_details() {

		// Do we need to auto-select this payment method?
		if ( is_admin() ) {
			return;
		}

		$session = WC()->session->get( 'paypal' );

		if ( null == $session || ! is_a( $session, 'WooCommerce_PayPal_Session_Data' )
		     || ! $session->checkout_completed || $session->expiry_time < time() || ! $session->payerID ) {

			return;

		}

		if ( $session->checkout_details && is_a( $session->checkout_details, 'PayPal_Checkout_Details' ) && ( is_checkout() || is_ajax() ) && self::$use_buyer_email ) {
			$this->buyer_email = $session->checkout_details->payer_details->email;
			$this->title .= ' - ' . esc_html( $this->buyer_email );
		}
		if ( ! $session->checkout_details->payer_details->billing_address ) {
			add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'before_checkout_billing_form' ) );
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
		if ( ! ( $session->using_ppc xor is_a( $this, 'PayPal_Credit_Gateway' ) ) ) {
			$this->chosen = true;
		} else {
			$this->chosen = false;
		}
	} // end function

	/**
	 * Loads all JS scripts for admin settings
	 *
	 * @access public
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return bool
	 */
	public function load_admin_scripts() {
		$current_screen = get_current_screen();

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'wc-ppec-admin-scripts', WC_PPEC_PLUGIN_URL . '/assets/js/wc-gateway-ppec-admin' . $suffix . '.js', array( 'jquery' ), WC_PPEC_VERSION, true );

		parse_str( $_SERVER['REQUEST_URI'] );

		if ( 'woocommerce_page_wc-settings' === $current_screen->id && 'wc_gateway_ppec' === $section ) {
			wp_enqueue_script( 'wc-ppec-admin-scripts' );
		}

		return true;
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
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$settings = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-gateway-ppec' ),
				'label'       => __( 'Enable PayPal Express Checkout', 'woocommerce-gateway-ppec' ),
				'type'        => 'checkbox',
				'description' => __( 'If this setting is enabled, buyers will be allowed to pay for their purchases using PayPal Express Checkout', 'woocommerce-gateway-ppec' ),
				'desc_tip'    => true,
				'default'     => 'no'
			),

			'enable_credit' => array(
				'title'       => __( 'PayPal Credit', 'woocommerce-gateway-ppec' ),
				'label'       => __( 'Enable PayPal Credit', 'woocommerce-gateway-ppec' ),
				'type'        => 'checkbox',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-ppec' ),
				'desc_tip'    => true,
				'default'     => 'no'
			),

			'environment' => array(
				'title'       => __( 'Environment', 'woocommerce-gateway-ppec' ),
				'type'        => 'select',
				'description' => '',
				'default'     => 'sandbox',
				'options'     => array(
					'live' => __( 'Live', 'woocommerce-gateway-ppec' ),
					'sandbox' => __( 'Sandbox', 'woocommerce-gateway-ppec' ),
				), ),

			'easy_setup' => array(
				'title'       => __( 'Easy Setup', 'woocommerce-gateway-ppec' ),
				'type'        => 'text',
				'class'       => 'wc-ppec-easy-setup',
			),

			'credentials_type' => array(
				'title'       => __( 'Credentials Type', 'woocommerce-gateway-ppec' ),
				'type'        => 'select',
				'description' => '',
				'default'     => 'signature',
				'options'     => array(
					'signature' => __( 'API Signature', 'woocommerce-gateway-ppec' ),
					'certificate'   => __( 'API Certificate', 'woocommerce-gateway-ppec' ),
				) ),

			'live_api_username' => array(
				'title'       => __( 'Live API username', 'woocommerce-gateway-ppec' ),
				'type'        => 'text',
				'default'     => ''
			),

			'live_api_password' => array(
				'title'       => __( 'Live API password', 'woocommerce-gateway-ppec' ),
				'type'        => 'password',
				'default'     => ''
			),

			'live_api_signature' => array(
				'title'       => __( 'Live Publishable Key', 'woocommerce-gateway-ppec' ),
				'type'        => 'text',
				'description' => '',
				'default'     => ''
			),

			'live_api_subject' => array(
				'title'       => __( 'Live Subject', 'woocommerce-gateway-ppec' ),
				'type'        => 'text',
				'default'     => ''
			),

			'live_api_certificate' => array(
				'title'       => __( 'Live API certificate ', 'woocommerce-gateway-ppec' ),
				'type'        => 'file_upload',
				'description' => __( 'Upload a new certificate: .', 'woocommerce-gateway-ppec' ),
				'default'     => ''
			),

			'sb_api_username' => array(
				'title'       => __( 'Sandbox API username', 'woocommerce-gateway-ppec' ),
				'type'        => 'text',
				'default'     => ''
			),

			'sb_api_password' => array(
				'title'       => __( 'Sandbox API password', 'woocommerce-gateway-ppec' ),
				'type'        => 'password',
				'default'     => ''
			),

			'sb_api_signature' => array(
				'title'       => __( 'Sandbox Publishable Key', 'woocommerce-gateway-ppec' ),
				'type'        => 'text',
				'description' => '',
				'default'     => ''
			),

			'sb_api_subject' => array(
				'title'       => __( 'Sandbox Subject', 'woocommerce-gateway-ppec' ),
				'type'        => 'text',
				'default'     => ''
			),

			'sb_api_certificate' => array(
				'title'       => __( 'Sandbox API certificate ', 'woocommerce-gateway-ppec' ),
				'type'        => 'file_upload',
				'description' => __( 'Upload a new certificate: .', 'woocommerce-gateway-ppec' ),
				'default'     => ''
			),

			'in_context_checkout' => array(
				'title'       => __( 'Enable in context checkout', 'woocommerce-gateway-ppec' ),
				'type'        => 'checkbox',
				'desc_tip'    => true,
				'default'     => 'no'
			),

			'button_size' => array(
				'title'       => __( 'Button Size', 'woocommerce-gateway-ppec' ),
				'type'        => 'select',
				'description' => '',
				'default'     => 'medium',
				'options'     => array(
					'small'  => __( 'Small', 'woocommerce-gateway-ppec' ),
					'medium' => __( 'Medium', 'woocommerce-gateway-ppec' ),
					'large'  => __( 'Large', 'woocommerce-gateway-ppec' ),
				) ),

			'mark_size' => array(
				'title'       => __( 'Mark Size', 'woocommerce-gateway-ppec' ),
				'type'        => 'select',
				'description' => '',
				'default'     => 'small',
				'options'     => array(
					'small'  => __( 'Small', 'woocommerce-gateway-ppec' ),
					'medium' => __( 'Medium', 'woocommerce-gateway-ppec' ),
					'large'  => __( 'Large', 'woocommerce-gateway-ppec' ),
				) ),

			'logo_image_url' => array(
				'title'       => __( 'Logo image url', 'woocommerce-gateway-ppec' ),
				'type'        => 'text',
				'default'     => ''
			),

			'payment_type' => array(
				'title'       => __( 'Payment Type', 'woocommerce-gateway-ppec' ),
				'type'        => 'select',
				'description' => '',
				'default'     => 'small',
				'options'     => array(
					'sale'          => __( 'Sale', 'woocommerce-gateway-ppec' ),
					'authorization' => __( 'Authorization', 'woocommerce-gateway-ppec' ),
					'order'         => __( 'Order', 'woocommerce-gateway-ppec' ),
				)
			),

			'guest_payments' => array(
				'title'       => __( 'Enable Guest Payments', 'woocommerce-gateway-ppec' ),
				'type'        => 'checkbox',
				'desc_tip'    => true,
				'default'     => 'no'
			),

			'instant_payments' => array(
				'title'       => __( 'Enable Instant Payments', 'woocommerce-gateway-ppec' ),
				'type'        => 'checkbox',
				'desc_tip'    => true,
				'default'     => 'no'
			),

			'zero_subtotal_behavior' => array(
				'title'       => __( 'Zero Sub Total Behaviour', 'woocommerce-gateway-ppec' ),
				'type'        => 'select',
				'description' => '',
				'default'     => 'modify_items',
				'options'     => array(
					'modify_items'                   => __( 'Modify line item prices and add a shipping discount', 'woocommerce-gateway-ppec' ),
					'omit_line_items'                 => __( "Don't send line items to PayPal", 'woocommerce-gateway-ppec' ),
					'pass_coupons_as_shipping_discount' => __( 'Send the coupons to PayPal as a shipping discount', 'woocommerce-gateway-ppec' ),
				) ),

			'subtotal_mismatch_behavior' => array(
				'title'       => __( 'Subtotal Mismatch Behavior', 'woocommerce-gateway-ppec' ),
				'type'        => 'select',
				'description' => '',
				'default'     => 'add_line_item',
				'options'     => array(
					'add_line_item'   => __( 'Add another line item', 'woocommerce-gateway-ppec' ),
					'drop_line_items' => __( 'Don\'t send line items to PayPal', 'woocommerce-gateway-ppec' ),
				) ),
		);

		/**
		 * Filter the PayPal Express Checkout settings
		 * @since 1.9.0
		 * @param array $settings
		 */
		$this->form_fields = apply_filters( 'wc_ppec_settings', $settings );

	}

	/**
	 * get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {

		$icon  = '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa.png' ) . '" alt="Visa" />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard.png' ) . '" alt="Mastercard" />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex.png' ) . '" alt="Amex" />';

		if ( 'USD' === get_woocommerce_currency() ) {
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover.png' ) . '" alt="Discover" />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb.png' ) . '" alt="JCB" />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( plugins_url( '/assets/images/diners.png', dirname( __FILE__ ) ) ) . '" alt="Diners" />';
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	public function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );
		$checkout = new WooCommerce_PayPal_Checkout();

		// Check the session.  Are we going to just complete an existing payment, or are we going to
		// send the user over PayPal to pay?

		$session = WC()->session->get( 'paypal' );
		if ( ! $session || ! is_a( $session, 'WooCommerce_PayPal_Session_Data' ) ||
				! $session->checkout_completed || $session->expiry_time < time() ||
				! $session->payerID ) {
			// Redirect them over to PayPal.
			try {
				$redirect_url = $checkout->startCheckoutFromCheckout( $order_id, 'paypal_credit' == $this->id );

				return array(
					'result' => 'success',
					'redirect' => $redirect_url
				);
			} catch( PayPal_API_Exception $e ) {
				$final_output = '<ul>';
				foreach ( $e->errors as $error ) {
					$final_output .= '<li>' . $error->maptoBuyerFriendlyError() . '</li>';
				}
				$final_output .= '</ul>';
				wc_add_notice( 'Payment error:' . $final_output, 'error' );
			}
		} else {
			// We have a token we can work with.  Just complete the payment now.
			try {
				$payment_details = $checkout->completePayment( $order_id, $session->token, $session->payerID );
				$transaction_id = $payment_details->payments[0]->transaction_id;

				// TODO: Handle things like eChecks, giropay, etc.
				$order = new WC_Order( $order_id );
				$order->payment_complete( $transaction_id );
				$order->add_order_note( sprintf( __( 'PayPal transaction completed; transaction ID = %s', 'woocommerce-gateway-ppec' ), $transaction_id ) );
				$order->reduce_order_stock();
				WC()->cart->empty_cart();
				unset( WC()->session->paypal );

				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			} catch( PayPal_Missing_Session_Exception $e ) {
				// For some reason, our session data is missing.  Generally, if we've made it this far, this shouldn't happen.
				wc_add_notice( __( 'Sorry, an error occurred while trying to process your payment.  Please try again.', 'woocommerce-gateway-ppec' ), 'error' );
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
						'redirect' => ''  );

				} else {
					$final_output = '<ul>';
					foreach ( $e->errors as $error ) {
						$final_output .= '<li>' . $error->maptoBuyerFriendlyError() . '</li>';
					}
					$final_output .= '</ul>';
					wc_add_notice( __( 'Payment error:', 'woocommerce-gateway-ppec' ) . $final_output, 'error' );
					return;
				}
			}
		}
	}

	private function get_certificate_info( $cert_string ) {
		if ( ! strlen( $cert_string ) ) {
			return __( 'No API certificate on file.', 'woocommerce-gateway-ppec' );
		}

		$cert = openssl_x509_read( $cert_string );
		if ( false !== $cert ) {
			$certinfo = openssl_x509_parse( $cert );
			if ( false !== $certinfo ) {
				$valid_until = $certinfo['validTo_time_t'];
				if ( $valid_until < time() ) {
					// Display in red if the cert is already expired
					$expires = '<span style="color: red;">' . __( 'expired on %s', 'woocommerce-gateway-ppec' ) . '</span>';
				} elseif ( $valid_until < ( time() - 2592000 ) ) {
					// Also display in red if the cert is going to expire in the next 30 days
					$expires = '<span style="color: red;">' . __( 'expires on %s', 'woocommerce-gateway-ppec' ) . '</span>';
				} else {
					// Otherwise just display a normal message
					$expires = __( 'expires on %s', 'woocommerce-gateway-ppec' );
				}

				$expires = sprintf( $out, date_i18n( get_option( 'date_format' ), $valid_until ) );
				$out = sprintf( __( 'Certificate belongs to API username %1$s; %2$s', 'woocommerce-gateway-ppec' ), $certinfo['subject']['CN'], $expires );
			} else {
				$out = __( 'The certificate on file is not valid.', 'woocommerce-gateway-ppec' );
			}
		} else {
			$out = __( 'The certificate on file is not valid.', 'woocommerce-gateway-ppec' );
		}

		return $out;
	}

	public function process_admin_options() {

		if ( ! $this->validate_credentials( 'live', $live_api_credentials ) ) {
				if ( array_key_exists( 'sb_api_certificate', $_FILES ) && array_key_exists( 'tmp_name', $_FILES['sb_api_certificate'] )
					&& array_key_exists( 'size', $_FILES['sb_api_certificate'] ) && $_FILES['sb_api_certificate']['size'] ) {
					$_POST['sb_api_cert_string'] = base64_encode( file_get_contents( $_FILES['sb_api_certificate']['tmp_name'] ) );
					unlink( $_FILES['sb_api_certificate']['tmp_name'] );
					unset( $_FILES['sb_api_certificate'] );
				}

			self::$process_admin_options_validation_error = true;
		}

		if ( ! $this->validate_credentials( 'sb', $sb_api_credentials ) ) {

			self::$process_admin_options_validation_error = true;

		}

		if( self::$process_admin_options_validation_error  ){

			return false;

		}

		// Validate the URL.
		$logo_image_url = trim( $_POST['logo_image_url'] );
		if ( ! empty( $logo_image_url ) && ! preg_match( '/https?:\/\/[a-zA-Z0-9][a-zA-Z0-9.-]+[a-zA-Z0-9](\/[a-zA-Z0-9.\/?&%#]*)?/', $logo_image_url ) ) {
			WC_Admin_Settings::add_error( __( 'Error: The logo image URL you provided is not valid.', 'woocommerce-gateway-ppec' ) );
			self::$process_admin_options_validation_error = true;
			return false;
		}

		if ( empty( $logo_image_url ) ) {
			$logo_image_url = false;
		}

		if ( $live_api_credentials ) {
			try {
				$live_account_enabled_for_billing_address = $this->test_for_billing_address_enabled( $live_api_credentials, 'live' );
			} catch( PayPal_API_Exception $ex ) {
				$this->display_warning( __( 'An error occurred while trying to determine which features are enabled on your live account.  You may not have access to all of the settings allowed by your PayPal account.  Please click "Save Changes" to try again.', 'woocommerce-gateway-ppec' ) );
			}
		}

		if ( $sb_api_credentials ) {
			try {
				$sb_account_enabled_for_billing_address = $this->test_for_billing_address_enabled( $sb_api_credentials, 'sandbox' );
			} catch( PayPal_API_Exception $ex ) {
				$this->display_warning( __( 'An error occurred while trying to determine which features are enabled on your sandbox account.  You may not have access to all of the settings allowed by your PayPal account.  Please click "Save Changes" to try again.', 'woocommerce-gateway-ppec' ) );
			}
		}

		// If the plugin is enabled, a valid set of credentials must be present.
		if ( $enabled ) {
			if ( ( 'live' == $environment && ! $live_api_credentials ) || ( 'sandbox' == $environment && ! $sb_api_credentials ) ) {
				WC_Admin_Settings::add_error( __( 'Error: You must supply a valid set of credentials before enabling the plugin.', 'woocommerce-gateway-ppec' ) );
				self::$process_admin_options_validation_error = true;
				return false;
			}
		}
	}

	function display_warning( $message ) {
		echo '<div class="error"><p>Warning: ' . $message . '</p></div>';
	}

	// Probe to see whether the merchant has the billing address feature enabled.  We do this
	// by running a SetExpressCheckout call with REQBILLINGADDRESS set to 1; if the merchant has
	// this feature enabled, the call will complete successfully; if they do not, the call will
	// fail with error code 11601.
	public function test_for_billing_address_enabled( $credentials, $environment = 'sandbox' ) {
		$api = new PayPal_API( $credentials, $environment );
		$req = array(
			'RETURNURL'         => 'https://localhost/',
			'CANCELURL'         => 'https://localhost/',
			'REQBILLINGADDRESS' => '1',
			'AMT'               => '1.00'
		);
		$result = $api->SetExpressCheckout( $req );

		if ( 'Success' != $result['ACK'] && 'SuccessWithWarning' != $result['ACK'] ) {
			$found_11601 = false;
			foreach ( $result as $index => $value ) {
				if ( preg_match( '/^L_ERRORCODE\d+$/', $index ) ) {
					if ( '11601' == $value ) {
						$found_11601 = true;
					}
				}
			}

			if ( $found_11601 ) {
				return false;
			} else {
				throw new PayPal_API_Exception( $result );
			}
		}

		return true;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order = new WC_Order( $order_id );
		if ( 0 == $amount || null == $amount ) {
			return new WP_Error( 'paypal_refund_error', __( 'Refund Error: You need to specify a refund amount.', 'woocommerce-gateway-ppec' ) );
		}

		// load up refundable_txns from Post Meta
		// loop through each transaction to compile list of txns that are able to be refunded
		// process refunds against each txn in the list until full amount of refund is reached
		// first loop through, try to find a transaction that equals the refund amount being requested
		$txnData = get_post_meta( $order_id, '_txnData', true );
		$didRefund = false;

		foreach ( $txnData['refundable_txns'] as $key => $value ) {
			$refundableAmount = $value['amount'] - $value['refunded_amount'];

			if ( $amount == $refundableAmount ) {
				if ( 0 == $value['refunded_amount'] ) {
					$refundType = 'Full';
				} else {
					$refundType = 'Partial';
				}
				$refundTransaction = new PayPal_Transaction( $value['txnID'], $settings );
				try {
					$refundTxnID = $refundTransaction->doRefund( $amount, $refundType, $reason, $order->get_order_currency() );
					$txnData['refundable_txns'][ $key ]['refunded_amount'] += $amount;
					$order->add_order_note( sprintf( $refundTxnID, __( 'PayPal refund completed; transaction ID = %s', 'woocommerce-gateway-ppec' ), $refundTxnID ) );
					update_post_meta( $order_id, '_txnData', $txnData );

					return true;

				} catch( PayPal_API_Exception $e ) {
					foreach ( $e->errors as $error ) {
						$final_output .= sprintf( __( 'Error: %1$s - %2$s', 'woocommerce-gateway-ppec' ), $error->error_code, $error->long_message );
					}

					return new WP_Error( 'paypal_refund_error', $final_output );
				}

			}

		}


		foreach ( $txnData['refundable_txns'] as $key => $value ) {
			$refundableAmount = $value['amount'] - $value['refunded_amount'];

			if ( $amount < $refundableAmount ) {
				$refundTransaction = new PayPal_Transaction( $value['txnID'], $settings );
				try {
					$refundTxnID = $refundTransaction->doRefund( $amount, 'Partial', $reason, $order->get_order_currency() );
					$txnData['refundable_txns'][ $key ]['refunded_amount'] += $amount;
					$order->add_order_note( sprintf( __( 'PayPal refund completed; transaction ID = %s', 'woocommerce-gateway-ppec' ), $refundTxnID ) );
					update_post_meta( $order_id, '_txnData', $txnData );

					return true;

				} catch( PayPal_API_Exception $e ) {
					foreach ( $e->errors as $error ) {
						$final_output .= sprintf( __( 'Error: %1$s - %2$s', 'woocommerce-gateway-ppec' ), $error->error_code, $error->long_message );
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
				return new WP_Error( 'paypal_refund_error', __( 'Refund Error: All transactions have been fully refunded. There is no amount left to refund', 'woocommerce-gateway-ppec' ) );
			} else {
				return new WP_Error( 'paypal_refund_error', sprintf( __( 'Refund Error: The requested refund amount is too large. The refund amount must be less than or equal to %s.', 'woocommerce-gateway-ppec' ), html_entity_decode( get_woocommerce_currency_symbol() ) . $totalRefundableAmount ) );
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
					$refundTransaction = new PayPal_Transaction( $value['txnID'], $settings );
					try {
						$refundTxnID = $refundTransaction->doRefund( $amount_to_refund, $refundType, $reason, $order->get_order_currency() );
						$total_to_refund -= $amount_to_refund;
						$txnData['refundable_txns'][ $key ]['refunded_amount'] += $amount_to_refund;
						$order->add_order_note( sprintf( __( 'PayPal refund completed; transaction ID = %s', 'woocommerce-gateway-ppec' ), $refundTxnID ) );
						update_post_meta( $order_id, '_txnData', $txnData );

						return true;
					} catch( PayPal_API_Exception $e ) {
						foreach ( $e->errors as $error ) {
							$final_output .= sprintf( __( 'Error: %1$s - %2$s', 'woocommerce-gateway-ppec' ), $error->error_code, $error->long_message );
						}

						return new WP_Error( 'paypal_refund_error', $final_output );
					}
				}
			}
		}
	}

	protected function set_payment_title() {
		$this->title = __( 'PayPal', 'woocommerce-gateway-ppec' );
	}

	/**
	 * Force zero decimal on specific currencies.
	 */
	public function force_zero_decimal() {

		if (  $this->currency_decimal_replace ) {
			update_option( 'woocommerce_price_num_decimals', 0 );
			update_option( 'wc_gateway_ppce_display_decimal_msg', true );
		}
	}

	/**
	 * Prevent PayPal Credit showing up in the admin, because it shares its settings
	 * with the PayPal Express Checkout class.
	 *
	 * @param array $sections List of sections in checkout
	 *
	 * @return array Sections in checkout
	 */
	public function filter_sections_checkout( $sections ) {
		unset( $sections['paypal_credit'] );
		return $sections;
	}

	/**
	 * All admin notices
	 */
	public function admin_notices(){

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

		if ( get_option( 'wc_gateway_ppce_display_decimal_msg', false ) ) {
			?>
			<div class="updated fade">
				<p>
					<strong><?php _e( 'NOTE: PayPal does not accept decimal places for the currency in which you are transacting.  The "Number of Decimals" option in WooCommerce has automatically been set to 0 for you.', 'woocommerce-gateway-ppec' ); ?></strong>
				</p>
			</div>
			<?php
			delete_option( 'wc_gateway_ppce_display_decimal_msg' );
		}

	}
}
