<?php

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

function woo_pp_start_checkout() {
	$checkout = new WooCommerce_PayPal_Checkout();

	try {
		$redirect_url = $checkout->startCheckoutFromCart();
		wp_safe_redirect( $redirect_url );
		exit;
	} catch( PayPal_API_Exception $e ) {
		$final_output = '';
		foreach ( $e->errors as $error ) {
			$final_output .= '<li>' . __( $error->mapToBuyerFriendlyError(), 'woo_pp' ) . '</li>';
		}
		wc_add_notice( __( 'Payment error:', 'woo_pp' ) . $final_output, 'error' );

		$redirect_url = WC()->cart->get_cart_url();
		$settings = new WC_Gateway_PPEC_Settings();
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
			wp_safe_redirect( $redirect_url );
			exit;
		}

	}
}
