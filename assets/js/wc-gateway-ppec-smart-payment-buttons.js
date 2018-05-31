/* global wc_ppec_context */
;( function ( $, window, document ) {
	'use strict';

	var getFundingMethods = function( methods ) {
		if ( ! methods ) {
			return null;
		}

		var paypal_funding_methods = [];
		for ( var i = 0; i < methods.length; i++ ) {
			paypal_funding_methods.push( paypal.FUNDING[ methods[ i ] ] );
		}
		return paypal_funding_methods;
	}

	var render = function() {
		paypal.Button.render( {
			env: wc_ppec_context.environment,
			locale: wc_ppec_context.locale,
			commit: false,

			funding: {
				allowed: getFundingMethods( wc_ppec_context.allowed ),
				disallowed: getFundingMethods( wc_ppec_context.disallowed ),
			},

			style: {
				color: wc_ppec_context.button_color,
				shape: wc_ppec_context.button_shape,
				layout: wc_ppec_context.button_layout,
				size: wc_ppec_context.button_size,				
				label: wc_ppec_context.button_label,
				tagline: false,
			},

			validate: function( actions ) {
				$( '#woo_pp_ec_button_product' ).off( '.legacy' )
					.on( 'enable', actions.enable )
					.on( 'disable', actions.disable );
			},

			payment: function( data, actions ) {
				return new paypal.Promise( function( resolve, reject ) {
					if ( 'product' === wc_ppec_context.page ) {
						window.wc_ppec_generate_cart( resolve );
					} else {
						resolve();
					}
				} ).then( function() {
					return paypal.request( {
						method: 'post',
						url: wc_ppec_context.start_checkout_url,
						data: {
							'nonce': wc_ppec_context.start_checkout_nonce,
						},
					} ).then( function( data ) {
						return data.token;
					} );
				} );
			},

			onAuthorize: function( data, actions ) {
				return actions.redirect();
			},

		}, '#woo_pp_ec_button' + ( 'product' === wc_ppec_context.page ? '_product' : '' ) );
	};

	render();
	$( document.body ).on( 'updated_cart_totals', render );

} )( jQuery, window, document );
