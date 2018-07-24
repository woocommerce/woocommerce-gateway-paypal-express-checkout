jQuery(document).ready(function($) {
	$( '#order_review' ).on( 'change', 'input[name=payment_method]', function() {
		if ( $( '#payment_method_ppec_paypal' ).size() ) {
			$( 'body' ).trigger( 'update_checkout' );

			// TODO: Update billing fields on the UI
		}
	});
});
