;(function ( $, window, document ) {
	'use strict';

	// Check whether PayPal Payments is installed.
	let is_paypal_payments_installed = false;
	if ( $( 'tr[data-slug="woocommerce-paypal-payments"]' ).length ) {
		is_paypal_payments_installed = true;
	}

	// Handle delete event for PayPal Payments.
	$( document ).on( 'wp-plugin-delete-success', function( event, response ) {
		if ( is_paypal_payments_installed && 'woocommerce-paypal-payments' === response.slug ) {
			is_paypal_payments_installed = false;

			// Change PPEC notice activation button id, text & link.
			const ppec_install_id   = $( '#ppec-activate-paypal-payments' ).data( 'install-id' );
			const ppec_install_text = $( '#ppec-activate-paypal-payments' ).data( 'install-text' );
			const ppec_install_link = $( '#ppec-activate-paypal-payments' ).data( 'install-link' );

			$( '#ppec-activate-paypal-payments' ).text( ppec_install_text );
			$( '#ppec-activate-paypal-payments' ).attr({
				href: ppec_install_link,
				id: ppec_install_id
			});
		}
	} );

	// Change button text when install link is clicked.
	$( document ).on( 'click', '#ppec-install-paypal-payments', function( e ) {
		e.preventDefault();
		$( this ).addClass( 'updating-message' ).text( 'Installing...' );
		const install_link = $( this ).attr('href');
		setTimeout( function(){
			window.location = install_link;
		}, 100 );
	});
})( jQuery, window, document );
