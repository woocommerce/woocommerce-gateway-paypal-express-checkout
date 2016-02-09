<?php
/**
 * Plugin Name: PayPal (plugin project)
 */
/**
 * Copyright (c) 2015 PayPal, Inc.
 *
 * The name of the PayPal may not be used to endorse or promote products derived from this
 * software without specific prior written permission. THIS SOFTWARE IS PROVIDED ``AS IS'' AND
 * WITHOUT ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, WITHOUT LIMITATION, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

// class-paypal-credit-gateway.php includes everything else we need
require_once 'class-paypal-credit-gateway.php';

$wc_pp_checkout_error = false;

function woo_pp_activate() {
	// Enable some options that we recommend for all merchants
	add_option( 'pp_woo_allowGuestCheckout', serialize( true ) );
	add_option( 'pp_woo_ppc_enabled', serialize( true ) );

	// Schedule the creation of a public key/private key pair for Easy Signup.
	add_option( 'pp_woo_ipsPrivateKey', 'not_generated' );
	add_option( 'pp_woo_justActivated', true );

	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		// Because PayPal will not accept HUF, TWD, or JPY with any decimal places, we'll have to make sure that Woo uses 0 decimal places
		// if the merchant is using any of these three currencies.

		$settings = new WooCommerce_PayPal_Settings();
		$settings->loadSettings();

		if( $settings->enabled ) {
			$currency = get_woocommerce_currency();
			$decimals = absint( get_option( 'woocommerce_price_num_decimals', 2 ) );
			if ( 'HUF' == $currency || 'TWD' == $currency || 'JPY' == $currency ) {
				if ( $decimals != 0 ) {
					update_option( 'woocommerce_price_num_decimals', 0 );
					add_option( 'woo_pp_display_decimal_msg', true );
				}
			}
		}
	}
}

register_activation_hook( __FILE__, 'woo_pp_activate' );

// checks if WooCommerce is active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	function woo_pp_get_woo_version() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$plugins = get_plugins();
		$file = 'woocommerce/woocommerce.php';
		if ( isset( $plugins[ $file ] ) ) {
			$version_array = explode( '.', $plugins[ $file ]['Version'] );
			$version = floatval( $version_array[0] . '.' . $version_array[1] );
			return $version;
		} else {
			return false;
		}
	}

	function woo_pp_async_generate_private_key() {
		$nonce = uniqid();
		set_transient( 'pp_woo_ipsGenNonce', $nonce, 30 );
		$args = array( 
			'timeout' => 1,
			'blocking' => false
		);

		wp_remote_get( home_url( '/?start-ips-keygen=true&ips-gen-nonce=' . $nonce ), $args );
	}

	function woo_pp_generate_private_key() {
		ignore_user_abort( true );
		set_time_limit( 0 );

		if ( $_GET['ips-gen-nonce'] != get_transient( 'pp_woo_ipsGenNonce' ) ) {
			return;
		}

		delete_transient( 'pp_woo_ipsGenNonce' );

		update_option( 'pp_woo_ipsPrivateKey', 'generation_started' );
		$ssl_config = array(
			'digest_alg' => 'sha512',
			'private_key_bits' => 4096,
			'private_key_type' => OPENSSL_KEYTYPE_RSA
		);

		$private_key = openssl_pkey_new( $ssl_config );	
		if ( ! openssl_pkey_export( $private_key, $private_key_export ) ) {
			update_option( 'pp_woo_ipsPrivateKey', 'generation_failed' );
		} else {
			update_option( 'pp_woo_ipsPrivateKey', $private_key_export );
		}

	}

	function woo_pp_show_decimal_msg_warning() {
		$display = get_option( 'woo_pp_display_decimal_msg', false );
		if ( $display ) {
			echo '<div class="updated fade">';
			echo '<p><strong>' . __( 'NOTE: PayPal does not accept decimal places for the currency in which you are transacting.  The "Number of Decimals" option in WooCommerce has automatically been set to 0 for you.', 'woo_pp' ) . '</p></strong>';
			echo '</div>';
			delete_option( 'woo_pp_display_decimal_msg' );
		}
	}
	add_action( 'admin_notices', 'woo_pp_show_decimal_msg_warning' );

	function woo_pp_enqueue_scripts() {
		global $woocommerce;
		// Load up CSS for the two shortcut buttons
		if ( is_cart() ) {
			wp_register_style( 'woo_pp_css', plugins_url( 'content/css/paypal.css', __FILE__ ) );
			wp_enqueue_style( 'woo_pp_css' );
		}
		
		// If in-context checkout is enabled, add the PayPal JS to the page
		if( is_cart() || is_checkout() ) {
			$settings = new WooCommerce_PayPal_Settings();
			$settings->loadSettings();
			
			if( 'yes' == $settings->enabled && $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->payerID ) {
				if( is_cart() ) {
					wp_enqueue_script( 'paypal-checkout-js', 'https://www.paypalobjects.com/api/checkout.js', array(), false, true );
				} else {
					// On the checkout page, only load the JS if we plan on sending them over to PayPal
					$session = $woocommerce->session->paypal;
					if ( ! $session || ! is_a( $session, 'WooCommerce_PayPal_Session_Data' ) ||
							! $session->checkout_completed || $session->expiry_time < time() ||
							! $session->payerID ) {
						wp_enqueue_script( 'woo-pp-checkout-js', plugins_url( 'content/js/checkout.js', __FILE__ ), array( 'jquery' ), false, true );
						wp_enqueue_script( 'paypal-checkout-js', 'https://www.paypalobjects.com/api/checkout.js', array(), false, true );
					}
				}
			}
		}
	}
	add_action( 'wp_enqueue_scripts', 'woo_pp_enqueue_scripts' );

	function woo_pp_check_currency() {
		// Because PayPal will not accept HUF, TWD, or JPY with any decimal places, we'll have to make sure that Woo uses 0 decimal places
		// if the merchant is using any of these three currencies.

		// TODO: Check to see if EC is enabled first
		$currency = get_woocommerce_currency();
		$decimals = absint( get_option( 'woocommerce_price_num_decimals', 2 ) );
		if ( 'HUF' == $currency || 'TWD' == $currency || 'JPY' == $currency ) {
			if ( $decimals != 0 ) {
				update_option( 'woocommerce_price_num_decimals', 0 );
				WC_Admin_Settings::add_message( __( 'NOTE: PayPal does not accept decimal places for the currency in which you are transacting.  The "Number of Decimals" option has automatically been set to 0 for you.', 'woo_pp' ) );
			}
		}
	}
	add_action( 'woocommerce_update_options_general', 'woo_pp_check_currency' );

	function woo_pp_checkout_process() {
		global $woocommerce;

		$session = $woocommerce->session->paypal;
		if ( null != $session && is_a( $session, 'WooCommerce_PayPal_Session_Data' ) && $session->checkout_completed && $session->expiry_time >= time() && $session->payerID ) {
			if ( ! $session->checkout_details->payer_details->billing_address ) {
				$woocommerce->checkout()->checkout_fields['billing']['billing_address_1']['required'] = false;
				$woocommerce->checkout()->checkout_fields['billing']['billing_city'     ]['required'] = false;
				$woocommerce->checkout()->checkout_fields['billing']['billing_state'    ]['required'] = false;
				$woocommerce->checkout()->checkout_fields['billing']['billing_postcode' ]['required'] = false;
			}
		}
	}
	add_action( 'woocommerce_checkout_process', 'woo_pp_checkout_process' );

	function woo_pp_woo_init() {
		global $woocommerce;

		// If the plugin was just activated, generate a private/public key pair for use with Easy Setup.
		if ( get_option( 'pp_woo_justActivated' ) ) {
			delete_option( 'pp_woo_justActivated' );
			woo_pp_async_generate_private_key();
		}

		if ( 'true' == $_GET['start-ips-keygen'] ) {
			woo_pp_generate_private_key();
			exit;
		}

		// If the buyer clicked on the "Check Out with PayPal" button, we need to wait for the cart
		// totals to be available.  Unfortunately that doesn't happen until
		// woocommerce_before_cart_totals executes, and there is already output sent to the browser by
		// this point.  So, to get around this issue, we'll enable output buffering to prevent WP from
		// sending anything back to the browser.
		if ( 'true' == $_GET['startcheckout'] ) {
			ob_start();
		}

		// Also start buffering if we're on an admin page and the merchant is trying to use Easy Signup.
		if ( is_admin() && ( 'true' == $_GET['ips-signup'] || 'true' == $_GET['ips-return'] ) ) {
			ob_start();
		}

		if ( 'true' == $_GET['woo-paypal-return'] ) {
			// call get ec and do ec
			// Make sure we have our token and payer ID
			if ( array_key_exists( 'token', $_GET ) && array_key_exists( 'PayerID', $_GET ) &&
					! empty( $_GET['token'] ) && ! empty( $_GET['PayerID'] ) ) {
				$token = $_GET['token'];
				$payer_id = $_GET['PayerID'];
			} else {
				// If the token and payer ID aren't there, just ignore this request
				return;
			}

			$checkout = new WooCommerce_PayPal_Checkout();
			try {
				$checkout_details = $checkout->getCheckoutDetails( $token );
			} catch( PayPal_API_Exception $e ) {
				wc_add_notice( __( 'Sorry, an error occurred while trying to retrieve your information from PayPal.  Please try again.', 'woo_pp' ), 'error' );
				return;
			} catch( PayPal_Missing_Session_Exception $e ) {
				wc_add_notice( __( 'Your PayPal checkout session has expired.  Please check out again.', 'woo_pp' ), 'error' );
				return;
			}

			$session = $woocommerce->session->paypal;
			if ( ! $session || ! is_a( $session, 'WooCommerce_PayPal_Session_Data' ) ||
					$session->expiry_time < time() || $token != $session->token ) {
				wc_add_notice( __( 'Your PayPal checkout session has expired.  Please check out again.', 'woo_pp' ), 'error' );
				return;
			}

			$session->checkout_completed = true;
			$session->payerID = $payer_id;

			$woocommerce->session->paypal = $session;

			if ( $session->using_ppc ) {
				$woocommerce->session->chosen_payment_method = 'paypal_credit';
			} else {
				$woocommerce->session->chosen_payment_method = 'paypal';
			}

			if ( 'order' == $session->leftFrom && $session->order_id ) {
				// Try to complete the payment now.
				try {
					$order_id = $session->order_id;
					$checkout = new WooCommerce_PayPal_Checkout();
					$payment_details = $checkout->completePayment( $order_id, $session->token, $session->payerID );
					$transaction_id = $payment_details->payments[0]->transaction_id;

					// TODO: Handle things like eChecks, giropay, etc.
					$order = new WC_Order( $order_id );
					$order->payment_complete( $transaction_id );
					$order->add_order_note( sprintf( __( 'PayPal transaction completed; transaction ID = %s', 'woo_pp' ), $transaction_id ) );
					$order->reduce_order_stock();
					$woocommerce->cart->empty_cart();
					unset( $woocommerce->session->paypal );

					header( 'Location: ' . $order->get_checkout_order_received_url() );
					exit;
				} catch( PayPal_Missing_Session_Exception $e ) {
					// For some reason, our session data is missing.  Generally, if we've made it this far,
					// this shouldn't happen.
					wc_add_notice( __( 'Sorry, an error occurred while trying to process your payment.  Please try again.', 'woo_pp' ), 'error' );
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
						$settings = new WooCommerce_PayPal_Settings();
						$session->checkout_completed = false;
						$session->leftFrom = 'order';
						$session->order_id = $order_id;
						$woocommerce->session->paypal = $session;
						header( 'Location: ' . $settings->getPayPalRedirectUrl( $session->token, true ) );
						exit;
					} else {
						$final_output = '<ul>';
						foreach ( $e->errors as $error ) {
							// These strings are located in lib/class-exception.php
							$final_output .= '<li>' . __( $error->maptoBuyerFriendlyError(), 'woo_pp' ) . '</li>';
						}
						$final_output .= '</ul>';
						wc_add_notice( __( 'Payment error:', 'woo_pp' ) . $final_output, 'error' );
						return;
					}
				}
			}
		}

	}
	add_action( 'woocommerce_init', 'woo_pp_woo_init' );

	function woo_pp_payment_gateways( $methods ) {
		// If the buyer already went through the PP checkout, then filter out the option they didn't select.
		global $woocommerce;
		$session = $woocommerce->session->paypal;
		if ( ( is_checkout() || is_ajax() ) && $session && is_a( $session, 'WooCommerce_PayPal_Session_Data' ) &&
				$session->checkout_completed && $session->expiry_time >= time() &&
				$session->payerID ) {
			if ( $session->using_ppc ) {
				$methods[] = 'PayPal_Credit_Gateway';
			} else {
				$methods[] = 'PayPal_Express_Checkout_Gateway';
			}
		} else {
			$methods[] = 'PayPal_Express_Checkout_Gateway';
			$methods[] = 'PayPal_Credit_Gateway';
		}
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'woo_pp_payment_gateways' );

	// We don't want PayPal Credit showing up in the admin, because it shares its settings with the PayPal Express Checkout class
	function woo_pp_filter_payment_gateways( $sections ) {
		unset( $sections['paypal_credit_gateway'] );
		return $sections;
	}
	add_filter( 'woocommerce_get_sections_checkout', 'woo_pp_filter_payment_gateways' );

	function woo_pp_check_for_checkout() {
		//check for startcheckout=true
		//if there then call start_checkout() else do nothing so page loads as normal

		if ( 'true' == $_GET['startcheckout'] ) {
			$_GET['startcheckout'] = 'false'; // trying to prevent auto running checkout when back button is pressed from PayPal page
			woo_pp_start_checkout();
		}
	}
    add_action( 'woocommerce_before_cart_totals', 'woo_pp_check_for_checkout' );

	function woo_pp_start_checkout() {

		global $woocommerce;
		$checkout = new WooCommerce_PayPal_Checkout();

		try {
			$redirect_url = $checkout->startCheckoutFromCart();
			header( 'Location: ' . $redirect_url );
			exit;
		} catch( PayPal_API_Exception $e ) {
			$final_output = '';
			foreach ( $e->errors as $error ) {
				$final_output .= '<li>' . __( $error->mapToBuyerFriendlyError(), 'woo_pp' ) . '</li>';
			}
			wc_add_notice( __( 'Payment error:', 'woo_pp' ) . $final_output, 'error' );
			
			$redirect_url = $woocommerce->cart->get_cart_url();
			$settings = new WooCommerce_PayPal_Settings();
			$settings->loadSettings();

			if( 'yes' == $settings->enabled && $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->payerID ) {
				ob_end_clean();
				?>
				<script type="text/javascript">
					if( ( window.opener != null ) && ( window.opener !== window ) &&
							( typeof window.opener.paypal != "undefined" ) && 
							( typeof window.opener.paypal.checkout != "undefined" ) ) {
						window.opener.location.assign( "<?php echo $redirect_url; ?>" );
						window.close();
					} else {
						window.location.assign( "<?php echo $redirect_url; ?>" );
					}
				</script>
				<?php
				exit;
			} else {
				header( 'Location: ' . $redirect_url );
				exit;
			}

		}

	}

    function woo_pp_display_paypal_button() {

    	// get permalink & add startcheckout=true for button redirect
		global $woocommerce;
		$settings = new WooCommerce_PayPal_Settings();
		$settings->loadSettings();
		
		if( 'yes' != $settings->enabled ) {
			return;
		}

    	$redirect = get_permalink();
		if ( false !== strpos( $redirect, '?' ) ) {
			$redirect .= '&';
		} else {
			$redirect .= '?';
		}

		$redirect .= 'startcheckout=true';

		$size = $settings->buttonSize;

		$version = woo_pp_get_woo_version();
		if ( $version && 2.3 > $version ) {
			$class = 'woo_pp_cart_buttons_div';
			$inner_class = 'woo_pp_cart_buttons_inner_div';
		} else {
			$class = 'woo_pp_checkout_buttons_div';
			$inner_class = 'woo_pp_checkout_buttons_inner_div';
		}

		if( 'yes' == $settings->enabled && $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->payerID ) {
			$class .= ' paypal-button-hidden';
		}

		echo '<div class="' . $class . '">';
		echo '<div class="' . $inner_class . '">' . __( '- OR -', 'woo_pp' ) . '</div>';
		echo '<span style="float: right;"><a href="' . $redirect . '" id="woo_pp_ec_button"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/checkout-logo-' . $size . '.png" alt="' . __( 'Check out with PayPal', 'woo_pp' ) . '" style="width: auto; height: auto;"></a></span>';

		if ( $settings->ppcEnabled ) {
			echo '<span style="float: right; padding-right: 5px;">';
			echo '<a href="' . $redirect . '&use-ppc=true" id="woo_pp_ppc_button"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-' . $size . '.png" alt="' . __( 'Pay with PayPal Credit', 'woo_pp' ) . '" style="width: auto; height: auto;"></a>';
			echo '</span>';
		}

		echo '</div>';
		
		if( 'yes' == $settings->enabled && $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->payerID ) {
			$payer_id = $settings->getActiveApiCredentials()->payerID;
			?>
			<script type="text/javascript">
				window.paypalCheckoutReady = function() {
					paypal.checkout.setup( '<?php echo $payer_id; ?>', {
						button: [ 'woo_pp_ec_button','woo_pp_ppc_button' ]
					});
				}
			</script>
			<?php
		}
	}

	// Because the "Proceed to Checkout" button moved in 2.3...
	$version = woo_pp_get_woo_version();
	if ( $version && $version < 2.3 ) {
		add_action( 'woocommerce_proceed_to_checkout', 'woo_pp_display_paypal_button' );
	} else {
		add_action( 'woocommerce_after_cart_totals', 'woo_pp_display_paypal_button' );
	}
	
	function woo_pp_before_checkout_process() {
		// Turn off use of the buyer email in the payment method title so that it doesn't appear in emails
		PayPal_Express_Checkout_Gateway::$use_buyer_email = false;
	}
	add_action( 'woocommerce_before_checkout_process', 'woo_pp_before_checkout_process' );

	function woo_pp_after_checkout_form() {
		global $woocommerce;
		$settings = new WooCommerce_PayPal_Settings();
		$settings->loadSettings();
	
		if( 'yes' == $settings->enabled && $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->payerID ) {
			$session = $woocommerce->session->paypal;
			if ( ! $session || ! is_a( $session, 'WooCommerce_PayPal_Session_Data' ) ||
					! $session->checkout_completed || $session->expiry_time < time() ||
					! $session->payerID ) {
				$payer_id = $settings->getActiveApiCredentials()->payerID;
				// This div is necessary for PayPal to properly display its lightbox.  For some reason.
				echo '<div id="woo_pp_icc_container" style="display: none;"></div>';
			}
		}
	}
	add_action( 'woocommerce_after_checkout_form', 'woo_pp_after_checkout_form' );

}

