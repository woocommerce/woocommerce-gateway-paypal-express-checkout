;(function ( $, window, document ) {
	'use strict';

	$( 'form.checkout' ).on( 'click', 'input[name="payment_method"]', function() {
		// Avoid toggling submit button if on confirmation screen
		if ( $( '#payment' ).find( '.wc-gateway-ppec-cancel' ).length ) {
			return;
		}

		var isPPEC       = $( this ).is( '#payment_method_ppec_paypal' );
		var togglePPEC   = isPPEC ? 'show' : 'hide';
		var toggleSubmit = isPPEC ? 'hide' : 'show';

		$( '#woo_pp_ec_button_checkout' ).animate( { opacity: togglePPEC, height: togglePPEC, padding: togglePPEC }, 230 );
		$( '#place_order' ).animate( { opacity: toggleSubmit, height: toggleSubmit, padding: toggleSubmit }, 230 );
	} );
})( jQuery, window, document );
