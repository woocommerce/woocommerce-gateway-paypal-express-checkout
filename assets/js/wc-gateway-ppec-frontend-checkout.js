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

	var c = xhr.responseText;
	if ( c.indexOf( '<!--WC_START-->' ) < 0 ) {
		return;
	}
	if( c.indexOf( '<!--WC_END-->' ) < 0 ) {
		return;
	}

	var d = jQuery.parseJSON( c.split( '<!--WC_START-->' )[1].split( '<!--WC_END-->' )[0] );
	if( !d ) {
		return;
	}
	if( 'success' != d.result ) {
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
