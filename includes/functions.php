<?php

function woo_pp_start_checkout() {
	$checkout = wc_gateway_ppec()->checkout;

	try {
		$redirect_url = $checkout->start_checkout_from_cart();
		wp_safe_redirect( $redirect_url );
		exit;
	} catch( PayPal_API_Exception $e ) {
		wc_add_notice( $e->getMessage(), 'error' );

		$redirect_url = wc_get_cart_url();
		$settings     = wc_gateway_ppec()->settings;
		$client       = wc_gateway_ppec()->client;

		if ( $settings->is_enabled() && $client->get_payer_id() ) {
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

/**
 * @deprecated
 */
function wc_gateway_ppec_format_paypal_api_exception( $errors ) {
	_deprecated_function( 'wc_gateway_ppec_format_paypal_api_exception', '1.2.0', '' );
}

/**
 * Log a message via WC_Logger.
 *
 * @param string $message Message to log
 */
function wc_gateway_ppec_log( $message ) {
	static $wc_ppec_logger;

	// No need to write to log file if logging is disabled.
	if ( ! wc_gateway_ppec()->settings->is_logging_enabled() ) {
		return false;
	}

	if ( ! isset( $wc_ppec_logger ) ) {
		$wc_ppec_logger = new WC_Logger();
	}

	$wc_ppec_logger->add( 'wc_gateway_ppec', $message );

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( $message );
	}
}

/**
 * Whether PayPal credit is supported.
 *
 * @since 1.5.0
 *
 * @return bool Returns true if PayPal credit is supported
 */
function wc_gateway_ppec_is_credit_supported() {
	$base = wc_get_base_location();
	return 'US' === $base['country'] && 'USD' === get_woocommerce_currency();
}

/**
 * Checks whether buyer is checking out with PayPal Credit.
 *
 * @since 1.2.0
 *
 * @return bool Returns true if buyer is checking out with PayPal Credit
 */
function wc_gateway_ppec_is_using_credit() {
	return ! empty( $_GET['use-ppc'] ) && 'true' === $_GET['use-ppc'];
}

const PPEC_FEE_META_NAME_OLD = 'PayPal Transaction Fee';
const PPEC_FEE_META_NAME_NEW = '_paypal_transaction_fee';

/**
 * Sets the PayPal Fee in the order metadata
 *
 * @since 1.6.6
 *
 * @param object $order Order to modify
 * @param string $fee Fee to save
 */
function wc_gateway_ppec_set_transaction_fee( $order, $fee ) {
	if ( empty( $fee ) ) {
		return;
	}
	$fee = wc_clean( $fee );
	if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		update_post_meta( $order->id, PPEC_FEE_META_NAME_NEW, $fee );
	} else {
		$order->update_meta_data( PPEC_FEE_META_NAME_NEW, $fee );
		$order->save_meta_data();
	}
}

/**
 * Gets the PayPal Fee from the order metadata, migrates if the fee was saved under a legacy key
 *
 * @since 1.6.6
 *
 * @param object $order Order to read
 * @return string Returns the fee or an empty string if the fee has not been set on the order
 */
function wc_gateway_ppec_get_transaction_fee( $order ) {
	$old_wc = version_compare( WC_VERSION, '3.0', '<' );

	//retrieve the fee using the new key
	if ( $old_wc ) {
		$fee = get_post_meta( $order->id, PPEC_FEE_META_NAME_NEW, true );
	} else {
		$fee = $order->get_meta( PPEC_FEE_META_NAME_NEW, true );
	}

	//if the fee was found, return
	if ( is_numeric( $fee ) ) {
		return $fee;
	}

	//attempt to retrieve the old meta, delete its old key, and migrate it to the new one
	if ( $old_wc ) {
		$fee = get_post_meta( $order->id, PPEC_FEE_META_NAME_OLD, true );
		delete_post_meta( $order->id, PPEC_FEE_META_NAME_OLD );
	} else {
		$fee = $order->get_meta( PPEC_FEE_META_NAME_OLD, true );
		$order->delete_meta_data( PPEC_FEE_META_NAME_OLD );
		$order->save_meta_data();
	}

	if ( is_numeric( $fee ) ) {
		wc_gateway_ppec_set_transaction_fee( $order, $fee );
	}

	return $fee;
}
