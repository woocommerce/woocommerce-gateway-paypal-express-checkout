/**
 * globals jQuery, wc_ppec, window.
 *
 * This script only enqueued when buyer start checkout from checkout. In this
 * case buyer needs to fill billing fields then proceed to login through PayPal.
 */

var woo_pp_icc_started = false;
window.paypalCheckoutReady = function() {
	paypal.checkout.setup( wc_ppec.payer_id, {
		container: 'woo_pp_icc_container'
	} );
};

jQuery( "form.checkout" ).submit(function() {
	if ( jQuery( '#payment_method_ppec_paypal, #payment_method_ppec_paypal_credit' ).is( ':checked' ) ) {
		woo_pp_icc_started = true;
		paypal.checkout.initXO();
	}
} );

jQuery( document ).ajaxComplete( function( event, xhr, settings ) {
	if( ! woo_pp_icc_started ) {
		return;
	}

	var resp = jQuery.parseJSON( xhr.responseText );
	if ( ! resp ) {
		return;
	}

	if( 'success' !== resp.result ) {
		paypal.checkout.closeFlow();
		woo_pp_icc_started = false;
	}
} );

jQuery( document ).ajaxError(function() {
	if( woo_pp_icc_started ) {
		paypal.checkout.closeFlow();
		woo_pp_icc_started = false;
	}
} );

function woo_pp_checkout_callback( url ) {
	paypal.checkout.startFlow( decodeURI( url ) );
}
