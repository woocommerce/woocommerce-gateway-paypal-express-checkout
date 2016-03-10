jQuery( document ).ready( function( $ ) {
	'use strict';

	// create namespace to avoid any possible conflicts
	$.wc_ppec_checkout = {
		init: function() {
			var wc_ppec_icc_started = false;

			window.paypalCheckoutReady = function() {
				paypal.checkout.setup( wc_ppec_var.payer_id, {
					container: 'wc_ppec_icc_container',
					environment: wc_ppec_var.environment
				});
			};

			$( '.woocommerce-checkout' ).submit( function() {
				if ( $( '#payment_method_paypal_express_checkout, #payment_method_paypal_credit' ).is( ':checked' ) ) {
					wc_ppec_icc_started = true;
					paypal.checkout.initXO();
				}
			});

			$( document ).ajaxComplete( function( event, xhr, settings ) {
				if ( ! wc_ppec_icc_started ) {
					return;
				}
				
				var c = xhr.responseText;
				
				if ( c.indexOf( '<!--WC_START-->' ) < 0 ) {
					return;
				}
				
				if( c.indexOf( '<!--WC_END-->' ) < 0 ) {
					return;
				}
				
				var d = $.parseJSON( c.split( '<!--WC_START-->' )[1].split( '<!--WC_END-->' )[0] );
				
				if( !d ) {
					return;
				}

				if ( 'success' != d.result ) {
					paypal.checkout.closeFlow();
					wc_ppec_icc_started = false;
				}
			});

			$( document ).ajaxError( function() {
				if ( wc_ppec_icc_started ) {
					paypal.checkout.closeFlow();
					wc_ppec_icc_started = false;
				}
			});
			
			function woo_pp_checkout_callback( url ) {
				paypal.checkout.startFlow( decodeURI( url ) );
			}
		}
	}; // close namespace

	$.wc_ppec_checkout.init();

// end document ready
});
