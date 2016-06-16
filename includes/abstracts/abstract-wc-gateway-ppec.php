<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WC_Gateway_PPEC extends WC_Payment_Gateway {

	private static $process_admin_options_already_run = false;
	private static $process_admin_options_validation_error = false;

	protected $buyer_email = false;
	public static $use_buyer_email = true;

	public function __construct() {

		$this->has_fields  = false;
		$this->icon        = false;
		$this->title       = '';
		$this->description = '';
		$this->supports[]  = 'refunds';

		$this->method_title       = __( 'PayPal Express Checkout', 'woocommerce-gateway-paypal-express-checkout' );
		$this->method_description = __( 'Process payments quickly and securely with PayPal.', 'woocommerce-gateway-paypal-express-checkout' );

		wc_gateway_ppec()->ips->maybe_received_credentials();

		$this->init_form_fields();

		$settings = wc_gateway_ppec()->settings->loadSettings();


		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Do we need to auto-select this payment method?
		// TODO: Move this out to particular handler instead of gateway
		if ( ! is_admin() ) {
			$session = WC()->session->get( 'paypal' );
			if ( null != $session && is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) && $session->checkout_completed && $session->expiry_time >= time() && $session->payerID ) {
				if ( $session->checkout_details && is_a( $session->checkout_details, 'PayPal_Checkout_Details' ) && ( is_checkout() || is_ajax() ) && self::$use_buyer_email ) {
					$this->buyer_email = $session->checkout_details->payer_details->email;
					$this->title .= ' - ' . esc_html( $this->buyer_email );
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

	public function init_form_fields() {
		$this->form_fields = array();
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
				$settings     = wc_gateway_ppec()->settings->loadSettings();
				if ( $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->get_payer_id() ) {
					$redirect_url = 'javascript:woo_pp_checkout_callback("' . urlencode( $redirect_url ) . '");';
				}
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
					$settings = wc_gateway_ppec()->settings->loadSettings();

					$session->checkout_completed = false;
					$session->leftFrom = 'order';
					$session->order_id = $order_id;
					WC()->session->paypal = $session;
					return array(
						'result' => 'success',
						'redirect' => $settings->getPayPalRedirectUrl( $session->token, true )
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

	private function get_certificate_info( $cert_string ) {
		if ( ! strlen( $cert_string ) ) {
			return __( 'No API certificate on file.', 'woocommerce-gateway-paypal-express-checkout' );
		}

		$cert = openssl_x509_read( $cert_string );
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
		} else {
			$out = __( 'The certificate on file is not valid.', 'woocommerce-gateway-paypal-express-checkout' );
		}

		return $out;
	}

	// We want to be able to do some magic JavaScript stuff that WooCommerce's settings API won't let us do, so we're just going
	// to override how WooCommerce tells us it should be done.
	public function admin_options() {
		$enable_ips = wc_gateway_ppec()->ips->is_supported();

		$error_msgs = get_option( 'woo_pp_admin_error' );
		if ( $error_msgs ) {
			foreach ( $error_msgs as $error_msg ) {
				foreach ( $error_msg as $type => $message ) {
					if ( 'error' == $type ) {
						WC_Admin_Settings::add_error( 'Error: ' . $message );
					} elseif ( 'warning' == $type ) {
						$this->display_warning( $message );
					} elseif ( 'success' == $type ) {
						WC_Admin_Settings::add_message( $message );
					}
				}
			}

			WC_Admin_Settings::show_messages();
			delete_option( 'woo_pp_admin_error' );
		}

		$settings = wc_gateway_ppec()->settings->loadSettings();

		$enabled         = false;
		$logging_enabled = false;
		$ppc_enabled     = false;
		$icc_enabled     = false;

		$live_api_username = '';
		$sb_api_username   = '';
		$live_api_pass     = '';
		$live_api_sig      = '';
		$sb_api_pass       = '';
		$sb_api_sig        = '';
		$live_subject      = '';
		$sb_subject        = '';

		$live_style = 'signature';
		$sb_style   = 'signature';

		$live_cert = false;
		$sb_cert   = false;

		$live_cert_info = __( 'No API certificate on file', 'woocommerce-gateway-paypal-express-checkout' );
		$sb_cert_info   = __( 'No API certificate on file', 'woocommerce-gateway-paypal-express-checkout' );
		$environment    = 'sandbox';

		// If we're re-rending the page after a validation error, make sure that we show the data the user entered instead of just reverting
		// to what is stored in the database.
		if ( self::$process_admin_options_validation_error ) {
			// TODO: We should probably encrypt the cert in some manner instead of just Base64-encoding it
			if ( ! empty( $_POST['woo_pp_enabled'] ) && 'true' == $_POST['woo_pp_enabled'] ) {
				$enabled = true;
			}

			if ( ! empty( $_POST['woo_pp_ppc_enabled'] ) && 'true' == $_POST['woo_pp_ppc_enabled'] ) {
				$ppc_enabled = true;
			}

			if ( ! empty( $_POST['woo_pp_icc_enabled'] ) && 'true' == $_POST['woo_pp_icc_enabled'] ) {
				$icc_enabled = true;
			}

			if ( ! empty( $_POST['woo_pp_logging_enabled'] ) && 'true' == $_POST['woo_pp_logging_enabled'] ) {
				$logging_enabled = true;
			}

			if ( array_key_exists( 'woo_pp_environment', $_POST ) ) {
				if ( 'live' == $_POST['woo_pp_environment'] || 'sandbox' == $_POST['woo_pp_environment'] ) {
					$environment = $_POST['woo_pp_environment'];
				}
			}

			// Grab the live credentials.
			$live_api_username = $_POST['woo_pp_live_api_username'];
			$live_api_pass     = $_POST['woo_pp_live_api_password'];
			$live_subject      = $_POST['woo_pp_live_subject'     ];

			if ( array_key_exists( 'woo_pp_live_api_style', $_POST ) ) {
				if ( 'signature' == $_POST['woo_pp_live_api_style'] || 'certificate' == $_POST['woo_pp_live_api_style'] ) {
					$live_style = $_POST['woo_pp_live_api_style'];
				}
			}

			if ( 'signature' == $live_style ) {
				$live_api_sig = $_POST['woo_pp_live_api_signature'];
			} else {
				if ( array_key_exists( 'woo_pp_live_api_certificate', $_FILES ) && array_key_exists( 'tmp_name', $_FILES['woo_pp_live_api_certificate'] )
					&& array_key_exists( 'size', $_FILES['woo_pp_live_api_certificate'] ) && $_FILES['woo_pp_live_api_certificate']['size'] ) {
					$live_cert      = file_get_contents( $_FILES['woo_pp_live_api_certificate']['tmp_name'] );
					$live_cert_info = $this->get_certificate_info( $live_cert );
				} elseif ( array_key_exists( 'woo_pp_live_api_cert_string', $_POST ) ) {
					$live_cert      = base64_decode( $_POST['woo_pp_live_api_cert_string'] );
					$live_cert_info = $this->get_certificate_info( $live_cert );
				}
			}

			// Grab the sandbox credentials.
			$sb_api_username = $_POST['woo_pp_sandbox_api_username'];
			$sb_api_pass     = $_POST['woo_pp_sandbox_api_password'];
			$sb_subject      = $_POST['woo_pp_sandbox_subject'     ];

			if ( array_key_exists( 'woo_pp_sandbox_api_style', $_POST ) ) {
				if ( 'signature' == $_POST['woo_pp_sandbox_api_style'] || 'certificate' == $_POST['woo_pp_sandbox_api_style'] ) {
					$sb_style = $_POST['woo_pp_sandbox_api_style'];
				}
			}

			if ( 'signature' == $sb_style ) {
				$sb_api_sig = $_POST['woo_pp_sandbox_api_signature'];
			} else {
				if ( array_key_exists( 'woo_pp_sandbox_api_certificate', $_FILES ) && array_key_exists( 'tmp_name', $_FILES['woo_pp_sandbox_api_certificate'] )
					&& array_key_exists( 'size', $_FILES['woo_pp_sandbox_api_certificate'] ) && $_FILES['woo_pp_sandbox_api_certificate']['size'] ) {
					$sb_cert      = file_get_contents( $_FILES['woo_pp_sandbox_api_certificate']['tmp_name'] );
					$sb_cert_info = $this->get_certificate_info( $sb_cert );
				} elseif ( array_key_exists( 'woo_pp_sandbox_api_cert_string', $_POST ) ) {
					$sb_cert      = base64_decode( $_POST['woo_pp_sandbox_api_cert_string'] );
					$sb_cert_info = $this->get_certificate_info( $sb_cert );
				}
			}

			if ( ! empty( $_POST['woo_pp_allow_guest_checkout'] ) && 'true' == $_POST['woo_pp_allow_guest_checkout'] ) {
				$allow_guest_checkout = true;
			} else {
				$allow_guest_checkout = false;
			}

			if ( ! empty( $_POST['woo_pp_block_echecks'] ) && 'true' == $_POST['woo_pp_block_echecks'] ) {
				$block_echecks = true;
			} else {
				$block_echecks = false;
			}

			if ( ! empty( $_POST['woo_pp_req_billing_address'] ) && 'true' == $_POST['woo_pp_req_billing_address'] ) {
				$require_billing_address = true;
			} else {
				$require_billing_address = false;
			}

			$button_size                = $_POST['woo_pp_button_size'               ];
			$mark_size                  = $_POST['woo_pp_mark_size'                 ];
			$logo_image_url             = $_POST['woo_pp_logo_image_url'            ];
			$payment_action             = $_POST['woo_pp_payment_action'            ];
			$zero_subtotal_behavior     = $_POST['woo_pp_zero_subtotal_behavior'    ];
			$subtotal_mismatch_behavior = $_POST['woo_pp_subtotal_mismatch_behavior'];
		} else {

			if ( is_object( $settings->liveApiCredentials ) && is_a( $settings->liveApiCredentials, 'WC_Gateway_PPEC_Client_Credential' ) ) {
				$live_api_username = $settings->liveApiCredentials->get_username();
				$live_subject      = $settings->liveApiCredentials->get_subject();
				$live_api_pass     = $settings->liveApiCredentials->get_password();

				if ( is_a( $settings->liveApiCredentials, 'WC_Gateway_PPEC_Client_Credential_Signature' ) && $settings->liveApiCredentials->get_signature() ) {
					$live_api_sig = $settings->liveApiCredentials->get_signature();
				}
				if ( is_a( $settings->liveApiCredentials, 'WC_Gateway_PPEC_Client_Credential_Certificate' ) && $settings->liveApiCredentials->get_certificate() ) {
					$live_cert_info = $this->get_certificate_info( $settings->liveApiCredentials->get_certificate() );
					$live_style     = 'certificate';
				}
			}

			if ( is_object( $settings->sandboxApiCredentials ) && is_a( $settings->sandboxApiCredentials, 'WC_Gateway_PPEC_Client_Credential' ) ) {
				$sb_api_username = $settings->sandboxApiCredentials->get_username();
				$sb_subject      = $settings->sandboxApiCredentials->get_subject();
				$sb_api_pass     = $settings->sandboxApiCredentials->get_password();

				if ( is_a( $settings->sandboxApiCredentials, 'WC_Gateway_PPEC_Client_Credential_Signature' ) && $settings->sandboxApiCredentials->get_signature() ) {
					$sb_api_sig = $settings->sandboxApiCredentials->get_signature();
				}
				if ( is_a ( $settings->sandboxApiCredentials, 'WC_Gateway_PPEC_Client_Credential_Certificate' ) && $settings->sandboxApiCredentials->get_certificate() ) {
					$sb_style     = 'certificate';
					$sb_cert_info = $this->get_certificate_info( $settings->sandboxApiCredentials->get_certificate() );
				}
			}

			$enabled                    = $settings->enabled;
			$logging_enabled            = $settings->logging_enabled;
			$ppc_enabled                = $settings->ppcEnabled;
			$icc_enabled                = $settings->enableInContextCheckout;
			$environment                = $settings->environment;
			$button_size                = $settings->buttonSize;
			$mark_size                  = $settings->markSize;
			$logo_image_url             = $settings->logoImageUrl;
			$payment_action             = $settings->paymentAction;
			$allow_guest_checkout       = $settings->allowGuestCheckout;
			$block_echecks              = $settings->blockEChecks;
			$require_billing_address    = $settings->requireBillingAddress;
			$zero_subtotal_behavior     = $settings->zeroSubtotalBehavior;
			$subtotal_mismatch_behavior = $settings->subtotalMismatchBehavior;
		}

		$help_image_url = WC()->plugin_url() . '/assets/images/help.png';
		$ips_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_ppec_with_paypal&ips-signup=true' );
		add_thickbox();

		require_once( wc_gateway_ppec()->includes_path . 'views/admin-settings.php' );
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

	public function process_admin_options() {
		// For some reason, this function is being fired twice, so this bit of code is here to prevent that from happening.
		if ( self::$process_admin_options_already_run ) {
			return false;
		}

		self::$process_admin_options_already_run = true;

		$settings = wc_gateway_ppec()->settings->loadSettings();

		$environment = $_POST['woo_pp_environment'];

		if ( ! in_array( $environment, array( 'live', 'sandbox' ) ) ) {
			WC_Admin_Settings::add_error( __( 'Error: The environment you selected is not valid.', 'woocommerce-gateway-paypal-express-checkout' ) );
			return false;
		}

		$credential = $this->validate_credentials( $environment );
		if ( ! is_a( $credential, 'WC_Gateway_PPEC_Client_Credential' ) ) {
			if ( array_key_exists( 'woo_pp_sandbox_api_certificate', $_FILES )
				&& array_key_exists( 'tmp_name', $_FILES['woo_pp_sandbox_api_certificate'] )
				&& array_key_exists( 'size', $_FILES['woo_pp_sandbox_api_certificate'] )
				&& $_FILES['woo_pp_sandbox_api_certificate']['size'] ) {

				$_POST['woo_pp_sandbox_api_cert_string'] = base64_encode( file_get_contents( $_FILES['woo_pp_sandbox_api_certificate']['tmp_name'] ) );
				unlink( $_FILES['woo_pp_sandbox_api_certificate']['tmp_name'] );
				unset( $_FILES['woo_pp_sandbox_api_certificate'] );

			}

			WC_Admin_Settings::add_error( __( 'Error: You must supply a valid set of credentials before enabling the plugin.', 'woocommerce-gateway-paypal-express-checkout' ) );
			self::$process_admin_options_validation_error = true;
			return false;
		}

		// Validate the URL.
		$logo_image_url = trim( $_POST['woo_pp_logo_image_url'] );
		if ( ! empty( $logo_image_url ) && ! preg_match( '/https?:\/\/[a-zA-Z0-9][a-zA-Z0-9.-]+[a-zA-Z0-9](\/[a-zA-Z0-9.\/?&%#]*)?/', $logo_image_url ) ) {
			WC_Admin_Settings::add_error( __( 'Error: The logo image URL you provided is not valid.', 'woocommerce-gateway-paypal-express-checkout' ) );
			self::$process_admin_options_validation_error = true;
			return false;
		}

		if ( empty( $logo_image_url ) ) {
			$logo_image_url = false;
		}

		$enabled                                  = false;
		$logging_enabled                          = false;
		$ppc_enabled                              = false;
		$icc_enabled                              = false;
		$allow_guest_checkout                     = false;
		$block_echecks                            = false;
		$require_billing_address                  = false;
		$live_account_enabled_for_billing_address = false;
		$sb_account_enabled_for_billing_address   = false;

		if ( isset( $_POST['woo_pp_enabled'] ) && 'true' == $_POST['woo_pp_enabled'] ) {
			$enabled = true;
		}

		if ( isset( $_POST['woo_pp_ppc_enabled'] ) && 'true' == $_POST['woo_pp_ppc_enabled'] ) {
			$ppc_enabled = true;
		}

		if ( isset( $_POST['woo_pp_allow_guest_checkout'] ) && 'true' == $_POST['woo_pp_allow_guest_checkout'] ) {
			$allow_guest_checkout = true;
		}

		if ( isset( $_POST['woo_pp_block_echecks'] ) && 'true' == $_POST['woo_pp_block_echecks'] ) {
			$block_echecks = true;
		}

		if ( isset( $_POST['woo_pp_req_billing_address'] ) && 'true' == $_POST['woo_pp_req_billing_address'] ) {
			$require_billing_address = true;
		}

		if ( isset( $_POST['woo_pp_icc_enabled'] ) && 'true' == $_POST['woo_pp_icc_enabled'] ) {
			$icc_enabled = true;
		}

		if ( isset( $_POST['woo_pp_logging_enabled'] ) && 'true' == $_POST['woo_pp_logging_enabled'] ) {
			$logging_enabled = true;
		}

		$is_account_enabled_for_billing_address = false;
		try {
			$is_account_enabled_for_billing_address = wc_gateway_ppec()->client->test_for_billing_address_enabled( $credential, $environment );
		} catch( PayPal_API_Exception $ex ) {
			$this->display_warning( __( 'An error occurred while trying to determine which features are enabled on your live account.  You may not have access to all of the settings allowed by your PayPal account.  Please click "Save Changes" to try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		switch ( $environment ) {
			case 'live':
				$live_account_enabled_for_billing_address = $is_account_enabled_for_billing_address;
				break;
			case 'sandbox':
				$sb_account_enabled_for_billing_address = $is_account_enabled_for_billing_address;
				break;
		}

		// WC_Gateway_PPEC_Settings already has sanitizers for these values, so we don't need to check them.
		$button_size                = $_POST['woo_pp_button_size'];
		$mark_size                  = $_POST['woo_pp_mark_size'];
		$payment_action             = $_POST['woo_pp_payment_action'];
		$zero_subtotal_behavior     = $_POST['woo_pp_zero_subtotal_behavior'];
		$subtotal_mismatch_behavior = $_POST['woo_pp_subtotal_mismatch_behavior'];

		// Go ahead and save everything.
		$settings->enabled                               = $enabled;
		$settings->logging_enabled                       = $logging_enabled;
		$settings->ppcEnabled                            = $ppc_enabled;
		$settings->enableInContextCheckout               = $icc_enabled;
		$settings->buttonSize                            = $button_size;
		$settings->logoImageUrl                          = $logo_image_url;
		$settings->markSize                              = $mark_size;
		$settings->environment                           = $environment;
		$settings->liveApiCredentials                    = 'live' === $environment ? $credential : false;
		$settings->sandboxApiCredentials                 = 'sandbox' === $environment ? $credential : false;
		$settings->allowGuestCheckout                    = $allow_guest_checkout;
		$settings->blockEChecks                          = $block_echecks;
		$settings->requireBillingAddress                 = $require_billing_address;
		$settings->paymentAction                         = $payment_action;
		$settings->zeroSubtotalBehavior                  = $zero_subtotal_behavior;
		$settings->subtotalMismatchBehavior              = $subtotal_mismatch_behavior;
		$settings->liveAccountIsEnabledForBillingAddress = $live_account_enabled_for_billing_address;
		$settings->sbAccountIsEnabledForBillingAddress   = $sb_account_enabled_for_billing_address;

		$settings->saveSettings();
	}

	public function display_warning( $message ) {
		echo '<div class="error"><p>Warning: ' . $message . '</p></div>';
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$settings = wc_gateway_ppec()->settings->loadSettings();

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
		$settings = wc_gateway_ppec()->settings->loadSettings();
		if ( 'sandbox' === $settings->environment ) {
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
		$settings = wc_gateway_ppec()->settings->loadSettings();
		if ( ! $settings->enabled ) {
			return false;
		}

		$api_credentials = $settings->getActiveApiCredentials();
		if ( ! is_callable( array( $api_credentials, 'get_payer_id' ) ) ) {
			return false;
		}

		return true;
	}

}
