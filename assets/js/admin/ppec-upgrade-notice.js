;(function ( $, window, document ) {
	'use strict';

	// Check whether PayPal Payments is installed.
	let is_paypal_payments_installed = false,
		is_paypal_payments_active = false;

	const targetElement = $( 'tr[data-slug="woocommerce-paypal-payments"]' );
	if ( targetElement.length ) {
		is_paypal_payments_installed = true;

		if ( targetElement.hasClass( 'active' ) ) {
			is_paypal_payments_active = true;
		}

		// Dynamically update plugin activation link to handle plugin folder renames.
		let activation_url = $( targetElement ).find( 'span.activate a' ).attr( 'href' );
		$( 'a#ppec-activate-paypal-payments' ).attr( 'href', activation_url );
	}

	// Hide notice/buttons conditionally.
	if ( is_paypal_payments_installed && is_paypal_payments_active ) {
		$( 'tr#ppec-migrate-notice' ).hide();
	} else if ( is_paypal_payments_installed ) {
		$( 'a#ppec-install-paypal-payments' ).hide();
	} else {
		$( 'a#ppec-activate-paypal-payments' ).hide();
	}

	// Display buttons area
	$( '#ppec-migrate-notice .ppec-notice-buttons' ).removeClass( 'hidden' );

	// Handle delete event for PayPal Payments.
	$( document ).on( 'wp-plugin-delete-success', function( event, response ) {
		if ( is_paypal_payments_installed && 'woocommerce-paypal-payments' === response.slug ) {
			$( 'a#ppec-activate-paypal-payments' ).hide();
			$( 'a#ppec-install-paypal-payments' ).show();
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
