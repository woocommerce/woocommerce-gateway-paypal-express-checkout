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

    var variation_valid = true;
    var fields_valid = true;
    var update_button = function() {
        $( '#woo_pp_ec_button_product' ).trigger( ( variation_valid && fields_valid ) ? 'enable' : 'disable' );
	};

    var validate_form = function() {
        silent_validation = true;
        fields_valid = true;
        form.find( 'select:enabled, input:enabled, textarea:enabled' ).each( function() {
            fields_valid = fields_valid && this.checkValidity();
        } );
        silent_validation = false;
        update_button();
    };

	// It's a variations form, button availability should depend on its events
	if ( $( '.variations_form' ).length ) {
		variation_valid = false;

		$( '.variations_form' )
		.on( 'show_variation', function( event, form, purchasable ) {
			variation_valid = purchasable;
            update_button();
		} )
		.on( 'hide_variation', function() {
			variation_valid = false;
            update_button();
		} );
	}

	// Disable the button if there are invalid fields in the product page (like required fields from Product Addons)
	var silent_validation;
	var form = $( 'form.cart' );
	form.get( 0 ).addEventListener( 'invalid', function( e ) {
		if ( silent_validation ) {
			e.preventDefault();
		}
	}, true );
	form.on( 'change', 'select, input, textarea', validate_form );
	validate_form();

	var get_attributes = function() {
		var select = $( '.variations_form' ).find( '.variations select' ),
			data   = {};

		select.each( function() {
			var attribute_name = $( this ).data( 'attribute_name' ) || $( this ).attr( 'name' );
            data[ attribute_name ] = $( this ).val() || '';
		} );

		return data;
	};

	var generate_cart = function( callback ) {
		var data = {
			'nonce':       wc_ppec_generate_cart_context.generate_cart_nonce,
			'qty':         $( '.quantity .qty' ).val(),
			'attributes':  $( '.variations_form' ).length ? get_attributes() : [],
			'add-to-cart': $( '[name=add-to-cart]' ).val(),
		};

		// Integrate with Product Addons
		$( form ).find( '[name^=addon-]:enabled' ).each( function () {
			data[ $( this ).attr( 'name' ) ] = $( this ).val();
		} );

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
