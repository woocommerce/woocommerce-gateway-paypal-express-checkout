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

	var render = function( isMiniCart ) {
		var prefix        = isMiniCart ? 'mini_cart_' : '';
		var button_size   = wc_ppec_context[ prefix + 'button_size' ];
		var button_layout = wc_ppec_context[ prefix + 'button_layout' ];
		var button_label  = wc_ppec_context[ prefix + 'button_label' ];
		var allowed       = wc_ppec_context[ prefix + 'allowed_methods' ];
		var disallowed    = wc_ppec_context[ prefix + 'disallowed_methods' ];

		paypal.Button.render( {
			env: wc_ppec_context.environment,
			locale: wc_ppec_context.locale,
			commit: 'checkout' === wc_ppec_context.page,

			funding: {
				allowed: getFundingMethods( allowed ),
				disallowed: getFundingMethods( disallowed ),
			},

			style: {
				color: wc_ppec_context.button_color,
				shape: wc_ppec_context.button_shape,
				layout: button_layout,
				size: button_size,
				label: button_label,
				tagline: false,
			},

			validate: function( actions ) {
				$( '#woo_pp_ec_button_product' ).off( '.legacy' )
					.on( 'enable', actions.enable )
					.on( 'disable', actions.disable );
			},

			payment: function( data, actions ) {
				return new paypal.Promise( function( resolve, reject ) {
					if ( 'product' === wc_ppec_context.page && ! isMiniCart ) {
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
							'from_checkout': 'checkout' === wc_ppec_context.page ? 'yes' : 'no',
						},
					} ).then( function( data ) {
						return data.token;
					} );
				} );
			},

			onAuthorize: function( data, actions ) {
				if ( 'checkout' === wc_ppec_context.page ) {
					$( 'form.checkout' )
						.append( $( '<input type="hidden" name="paymentToken" /> ' ).attr( 'value', data.paymentToken ) )
						.append( $( '<input type="hidden" name="payerID" /> ' ).attr( 'value', data.payerID ) )
						.submit();
				} else {
					return actions.redirect();
				}
			},

		}, '#woo_pp_ec_button' + ( 'product' === wc_ppec_context.page && ! isMiniCart ? '_product' : '' ) );
	};

	if ( wc_ppec_context.page ) {
		render();
		$( document.body ).on( 'updated_cart_totals updated_checkout', function() {
			render();
		} );
	}
	$( document.body ).on( 'wc_fragments_loaded wc_fragments_refreshed', function() {
		var $button = $( '.widget_shopping_cart #woo_pp_ec_button' );
		if ( $button.length ) {
			$button.empty();
			render( true );
		}
	} );

} )( jQuery, window, document );
