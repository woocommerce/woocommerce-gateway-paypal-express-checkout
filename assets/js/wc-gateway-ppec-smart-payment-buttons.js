/* global wc_ppec_context */
;( function ( $, window, document ) {
	'use strict';

	// Show error notice at top of checkout form, or else within button container
	var showError = function( errorMessage, selector ) {
		var $container = $( '.woocommerce-notices-wrapper, form.checkout' );

		if ( ! $container || ! $container.length ) {
			$( selector ).prepend( errorMessage );
			return;
		} else {
			$container = $container.first();
		}

		// Adapted from https://github.com/woocommerce/woocommerce/blob/ea9aa8cd59c9fa735460abf0ebcb97fa18f80d03/assets/js/frontend/checkout.js#L514-L529
		$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
		$container.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + errorMessage + '</div>' );
		$container.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();

		var scrollElement = $( '.woocommerce-NoticeGroup-checkout' );
		if ( ! scrollElement.length ) {
			scrollElement = $container;
		}

		if ( $.scroll_to_notices ) {
			$.scroll_to_notices( scrollElement );
		} else {
			// Compatibility with WC <3.3
			$( 'html, body' ).animate( {
				scrollTop: ( $container.offset().top - 100 )
			}, 1000 );
		}

		$( document.body ).trigger( 'checkout_error' );
	}

	var render = function( isMiniCart ) {
		var prefix        = isMiniCart ? 'mini_cart_' : '';
		var button_layout = wc_ppec_context[ prefix + 'button_layout' ];
		var button_label  = wc_ppec_context[ prefix + 'button_label' ];

		var selector     = isMiniCart ? '#woo_pp_ec_button_mini_cart' : '#woo_pp_ec_button_' + wc_ppec_context.page;
		var fromCheckout = 'checkout' === wc_ppec_context.page && ! isMiniCart;
		const return_url = wc_ppec_context[ prefix + 'return_url' ];

		// Don't render if already rendered in DOM.
		if ( $( selector ).children().length ) {
			return;
		}

		paypal.Buttons( {
			style: {
				color: wc_ppec_context.button_color,
				shape: wc_ppec_context.button_shape,
				layout: button_layout,
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

			createOrder: function() {
				// Clear any errors from previous attempt.
				$( '.woocommerce-error', selector ).remove();

				return new Promise( function( resolve, reject ) {
					// First, generate cart if triggered from single product.
					if ( 'product' === wc_ppec_context.page && ! isMiniCart ) {
						window.wc_ppec_generate_cart( resolve );
					} else {
						resolve();
					}
				} ).then( function() {
					// Make PayPal Checkout initialization request.
					var data = $( selector ).closest( 'form' )
						.add( $( '<input type="hidden" name="nonce" /> ' )
							.attr( 'value', wc_ppec_context.start_checkout_nonce )
						)
						.add( $( '<input type="hidden" name="from_checkout" /> ' )
							.attr( 'value', fromCheckout ? 'yes' : 'no' )
						)
						.serialize();
					
					return fetch( wc_ppec_context.start_checkout_url, {
						method: 'post',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: data,
					} ).then(
						response => response.json()
					).then( function( response ) {
						if ( ! response.success ) {
							var messageItems = response.data.messages.map( function( message ) {
								return '<li>' + message + '</li>';
							} ).join( '' );

							showError( '<ul class="woocommerce-error" role="alert">' + messageItems + '</ul>', selector );
							return null;
						}
						return response.data.token;
					} );
				} );
			},

			onApprove: function( data, actions ) {
				if ( fromCheckout ) {
					// Pass data necessary for authorizing payment to back-end.
					$( 'form.checkout' )
						.append( $( '<input type="hidden" name="paymentToken" /> ' ).attr( 'value', data.orderID ) )
						.append( $( '<input type="hidden" name="payerID" /> ' ).attr( 'value', data.payerID ) )
						.submit();
				} else {
					// Navigate to order confirmation URL specified in original request to PayPal from back-end.
					const query_args = `?woo-paypal-return=true&token=${ data.orderID }&PayerID=${ data.payerID }`
					return actions.redirect( return_url + query_args );
				}
			},

		} ).render( selector );
	};

	// Render cart, single product, or checkout buttons.
	if ( wc_ppec_context.page ) {
		if ( 'checkout' !== wc_ppec_context.page ) {
			render();
		}
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
