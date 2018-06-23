/* global wc_ppec_generate_cart_context */
;(function( $, window, document ) {
	'use strict';

	// This button state is only applicable to non-SPB click handler below.
	var button_enabled = true;
	$( '#woo_pp_ec_button_product' )
		.on( 'enable.legacy', function() {
			button_enabled = true;
		} )
		.on( 'disable.legacy', function() {
			button_enabled = false;
		} );

	$( '#woo_pp_ec_button_product' )
		.on( 'enable', function() {
			$( '#woo_pp_ec_button_product' ).css( {
				'cursor': '',
				'-webkit-filter': '', // Safari 6.0 - 9.0
				'filter': '',
			} );
			$( '#woo_pp_ec_button_product > *' ).css( 'pointer-events', '' );
		} )
		.on( 'disable', function() {
			$( '#woo_pp_ec_button_product' ).css( {
				'cursor': 'not-allowed',
				'-webkit-filter': 'grayscale( 100% )', // Safari 6.0 - 9.0
				'filter': 'grayscale( 100% )',
			} );
			$( '#woo_pp_ec_button_product > *' ).css( 'pointer-events', 'none' );
		} );

	// It's a variations form, button availability should depend on its events
	if ( $( '.variations_form' ).length ) {
		$( '#woo_pp_ec_button_product' ).trigger( 'disable' );

		$( '.variations_form' )
		.on( 'show_variation', function( event, form, purchasable ) {
			$( '#woo_pp_ec_button_product' ).trigger( purchasable ? 'enable' : 'disable' );
		} )
		.on( 'hide_variation', function() {
			$( '#woo_pp_ec_button_product' ).trigger( 'disable' );
		} );
	}

	var get_attributes = function() {
		var select = $( '.variations_form' ).find( '.variations select' ),
			data   = {},
			count  = 0,
			chosen = 0;

		select.each( function() {
			var attribute_name = $( this ).data( 'attribute_name' ) || $( this ).attr( 'name' );
			var value	  = $( this ).val() || '';

			if ( value.length > 0 ) {
				chosen++;
			}

			count++;
			data[ attribute_name ] = value;
		} );

		return {
			'count'      : count,
			'chosenCount': chosen,
			'data'       : data
		};
	};

	var generate_cart = function( callback ) {
		var data = {
			'nonce':       wc_ppec_generate_cart_context.generate_cart_nonce,
			'qty':         $( '.quantity .qty' ).val(),
			'attributes':  $( '.variations_form' ).length ? get_attributes().data : [],
			'add-to-cart': $( '[name=add-to-cart]' ).val(),
		};

		$.ajax( {
			type:    'POST',
			data:    data,
			url:     wc_ppec_generate_cart_context.ajaxurl,
			success: callback,
		} );
	};

	window.wc_ppec_generate_cart = generate_cart;

	// Non-SPB mode click handler, namespaced as 'legacy' as it's replaced by `payment` callback of Button API.
	$( '#woo_pp_ec_button_product' ).on( 'click.legacy', function( event ) {
		event.preventDefault();

		if ( ! button_enabled ) {
			return;
		}

		$( '#woo_pp_ec_button_product' ).trigger( 'disable' );

		var href = $(this).attr( 'href' );

		generate_cart( function() {
			window.location.href = href;
		} );
	} );

})( jQuery, window, document );
