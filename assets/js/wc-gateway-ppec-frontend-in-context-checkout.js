;(function ( $, window, document ) {

	var showModalPopup = wc_ppec_context.show_modal;

	if( 'true' === showModalPopup  ){
		window.paypalCheckoutReady = function() {
			paypal.checkout.setup(
				wc_ppec_context.payer_id,
				{
					environment: wc_ppec_context.environment,
					button: 'woo_pp_ec_button',
					locale: wc_ppec_context.locale,
				}
			);
		}

		$( document ).on( 'click', '#woo_pp_ec_button', function( event ) {
			event.preventDefault();
			paypal.checkout.startFlow( wc_ppec_context.start_flow );
		} );
	}
})( jQuery, window, document );
