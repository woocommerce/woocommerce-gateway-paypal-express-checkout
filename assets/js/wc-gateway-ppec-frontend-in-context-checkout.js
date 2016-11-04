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

	if ( wc_ppec_context.show_modal  ) {
		$wc_ppec.init();
	}
})( jQuery, window, document );
