/* global wc_ppec_context */
;( function ( $, window, document ) {
	'use strict';

	var render = function() {
		paypal.Button.render( {
			env: wc_ppec_context.environment,
			locale: wc_ppec_context.locale,
			commit: false,

			style: {
				color: wc_ppec_context.button_color,
				shape: wc_ppec_context.button_shape,
				layout: wc_ppec_context.button_layout,
				label: wc_ppec_context.button_label,
				tagline: false,
			},

			payment: function( data, actions ) {
				return paypal.request( {
					method: 'post',
					url: wc_ppec_context.start_checkout_url,
					data: {
						'nonce': wc_ppec_context.start_checkout_nonce,
					},
				} ).then( function( data ) {
					return data.token;
				} );
			},

			onAuthorize: function( data, actions ) {
				return actions.redirect();
			},

		}, '#woo_pp_ec_button' );
	};

	render();
	$( document.body ).on( 'updated_cart_totals', render );

} )( jQuery, window, document );
