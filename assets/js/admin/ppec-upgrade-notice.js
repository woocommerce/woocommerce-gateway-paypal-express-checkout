;(function ( $, window, document ) {
	'use strict';

	// Plugin rows.
	let $notice_row = $( 'tr#ppec-migrate-notice' );
	let $ppec_row   = $notice_row.prev();
	let $ppcp_row   = $( 'tr[data-slug="woocommerce-paypal-payments"]' );

	$ppec_row.toggleClass( 'hide-border', true );

	// Check whether PayPal Payments is installed.
	let is_paypal_payments_installed = $ppcp_row.length > 0;
	let is_paypal_payments_active    = is_paypal_payments_installed && $ppcp_row.hasClass( 'active' );

	let updateUI = function() {
		// Dynamically update plugin activation link to handle plugin folder renames.
		if ( is_paypal_payments_installed > 0 ) {
			$notice_row.find( 'a#ppec-activate-paypal-payments' ).attr( 'href', $ppcp_row.find( 'span.activate a' ).attr( 'href' ) );
		}

		// Hide notice/buttons conditionally.
		$notice_row.find( 'a#ppec-install-paypal-payments' ).toggle( ! is_paypal_payments_installed );
		$notice_row.find( 'a#ppec-activate-paypal-payments' ).toggle( is_paypal_payments_installed && ! is_paypal_payments_active );

		// Display buttons area.
		$notice_row.find( '.ppec-notice-buttons' ).removeClass( 'hidden' );
	};

	// Handle delete event for PayPal Payments.
	$( document ).on( 'wp-plugin-delete-success', function( event, response ) {
		if ( 'woocommerce-paypal-payments' === response.slug ) {
			is_paypal_payments_installed = false;
			is_paypal_payments_active    = false;
			updateUI();
		}
	} );

	// Change button text when install link is clicked.
	$notice_row.find( '#ppec-install-paypal-payments' ).click( function( e ) {
		e.preventDefault();
		$( this ).addClass( 'updating-message' ).text( 'Installing...' );
		const install_link = $( this ).attr('href');
		setTimeout( function(){
			window.location = install_link;
		}, 50 );
	} );

	// Dismiss button.
	$( document).on( 'click', '#ppec-migrate-notice button.notice-dismiss', function( e ) {
		$.ajax(
			{
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'ppec_dismiss_ppec_upgrade_notice',
					_ajax_nonce: $notice_row.attr( 'data-dismiss-nonce' )
				},
				dataType: 'json',
				success: function( res ) {
					$ppec_row.removeClass( 'hide-border' );
				}
			}
		);
	} );

	updateUI();

})( jQuery, window, document );
