;(function ( $, window, document ) {
	'use strict';

	var $wc_ppec = {
		init: function() {
			window.paypalCheckoutReady = function() {				
				paypal.checkout.setup(
					wc_ppec_context.payer_id,
					{
						environment: wc_ppec_context.environment,
						button: ['woo_pp_ec_button', 'woo_pp_ppc_button'],
						locale: wc_ppec_context.locale,
						container: ['woo_pp_ec_button', 'woo_pp_ppc_button']
					}
				);
			}
		}
	}

	var costs_updated = false;

	$( '#woo_pp_ec_button' ).click( function( event ) {
		if ( costs_updated ) {
			costs_updated = false;

			return;
		}

		event.stopPropagation();

		var data = {
			'nonce':      wc_ppec_context.update_shipping_costs_nonce,
		};

		var href = $(this).attr( 'href' );

		$.ajax( {
			type:    'POST',
			data:    data,
			url:     wc_ppec_context.ajaxurl,
			success: function( response ) {
				costs_updated = true;
				$( '#woo_pp_ec_button' ).click();
			}
		} );
	} );

	if ( wc_ppec_context.show_modal ) {
		$wc_ppec.init();
	}
})( jQuery, window, document );
