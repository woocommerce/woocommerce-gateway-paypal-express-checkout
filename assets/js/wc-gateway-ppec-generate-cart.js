/* global wc_ppec_context */
;(function( $, window, document ) {
	'use strict';

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

	$( '#woo_pp_ec_button' ).click( function( event ) {
		event.preventDefault();

		var data = {
			'nonce':      wc_ppec_context.generate_cart_nonce,
			'qty':        $( '.quantity .qty' ).val(),
			'attributes': $( '.variations_form' ).length ? get_attributes().data : []
		};

		var href = $(this).attr( 'href' );

		$.ajax( {
			type:    'POST',
			data:    data,
			url:     wc_ppec_context.ajaxurl,
			success: function( response ) {
				window.location.href = href;
			}
		} );
	} );

})( jQuery, window, document );
