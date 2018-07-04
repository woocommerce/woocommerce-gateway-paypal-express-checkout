/* global wc_ppec_context */
;( function ( $, window, document ) {
	'use strict';

	// Map funding method settings to enumerated options provided by PayPal.
	var getFundingMethods = function( methods ) {
		if ( ! methods ) {
			return null;
		}

		var paypal_funding_methods = [];
		for ( var i = 0; i < methods.length; i++ ) {
			var method = paypal.FUNDING[ methods[ i ] ];
			if ( method ) {
				paypal_funding_methods.push( method );
			}
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

		var selector = isMiniCart ? '#woo_pp_ec_button_mini_cart' : '#woo_pp_ec_button_' + wc_ppec_context.page;

		paypal.Button.render( {
			env: wc_ppec_context.environment,
			locale: wc_ppec_context.locale,
			commit: 'checkout' === wc_ppec_context.page && ! isMiniCart,

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
				branding: true,
				tagline: false,
			},

			validate: function( actions ) {
				// Only enable on variable product page if purchasable variation selected.
				$( '#woo_pp_ec_button_product' ).off( '.legacy' )
					.on( 'enable', actions.enable )
					.on( 'disable', actions.disable );
			},

			payment: function() {
				// Clear any errors from previous attempt.
				$( '.woocommerce-error', selector ).remove();

				return new paypal.Promise( function( resolve, reject ) {
					// First, generate cart if triggered from single product.
					if ( 'product' === wc_ppec_context.page && ! isMiniCart ) {
						window.wc_ppec_generate_cart( resolve );
					} else {
						resolve();
					}
				} ).then( function() {
					// Make PayPal Checkout initialization request.
					return paypal.request( {
						method: 'post',
						url: wc_ppec_context.start_checkout_url,
						data: {
							'nonce': wc_ppec_context.start_checkout_nonce,
							'from_checkout': 'checkout' === wc_ppec_context.page && ! isMiniCart ? 'yes' : 'no',
						},
					} ).then( function( response ) {
						if ( ! response.success ) {
							// Render error notice inside button container.
							var $message = $( '<ul class="woocommerce-error" role="alert">' )
								.append( $( '<li>' ).text( response.data.message ) );
							$( selector ).prepend( $message );
							return null;
						}
						return response.data.token;
					} );
				} );
			},

			onAuthorize: function( data, actions ) {
				if ( 'checkout' === wc_ppec_context.page && ! isMiniCart ) {
					// Pass data necessary for authorizing payment to back-end.
					$( 'form.checkout' )
						.append( $( '<input type="hidden" name="paymentToken" /> ' ).attr( 'value', data.paymentToken ) )
						.append( $( '<input type="hidden" name="payerID" /> ' ).attr( 'value', data.payerID ) )
						.submit();
				} else {
					// Navigate to order confirmation URL specified in original request to PayPal from back-end.
					return actions.redirect();
				}
			},

		}, selector );
	};

	// Render cart, single product, or checkout buttons.
	if ( wc_ppec_context.page ) {
		render();
		$( document.body ).on( 'updated_cart_totals updated_checkout', render.bind( this, false ) );
	}

	// Render buttons in mini-cart if present.
	$( document.body ).on( 'wc_fragments_loaded wc_fragments_refreshed', function() {
		var $button = $( '.widget_shopping_cart #woo_pp_ec_button_mini_cart' );
		if ( $button.length ) {
			// Clear any existing button in container, and render.
			$button.empty();
			render( true );
		}
	} );

} )( jQuery, window, document );
