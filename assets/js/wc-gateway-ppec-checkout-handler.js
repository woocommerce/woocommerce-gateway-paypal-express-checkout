/* global wc_ppec_checkout_handler_context */
;( function ( $, window, document ) {
	'use strict';

	// If the fields are optional, such as for a virtual product
	if ( true == wc_ppec_checkout_handler_context.do_not_require_fields ) {

		// Trigger for pre-selected gateway
		$( '.woocommerce-checkout input[name="payment_method"]:checked' ).trigger( 'click' );

		// When PPEC is selected
		$( 'form.checkout' ).on( 'click', 'input[name="payment_method"]', function() {
			var $not_required_fields = wc_ppec_checkout_handler_context.not_required_fields;
			if ( 'payment_method_ppec_paypal' == $( '.woocommerce-checkout input[name="payment_method"]:checked' ).attr( 'id' ) ) {
				// For each field not required
				$.each( $not_required_fields, function( key, value ) {
					var $field = $( 'form.checkout' ).find( 'label[for="' + value + '"]' );
					if ( $field ) {
						make_address_field_optional( $field );
					}
				} );
			} else {
				$.each( $not_required_fields, function( key, value ) {
					var $field = $( 'form.checkout' ).find( 'label[for="' + value + '"]' );
					if ( $field ) {
						make_address_field_required( $field );
					}
				} );
			}
		} );

		// When shipping is not needed, do not require address fields
		var make_address_field_optional = function( field ) {
			field.parent().removeClass( 'validate-required woocommerce-invalid woocommerce-invalid-required-field' );
			field.find( 'abbr.required' ).remove();
			if ( field.find( 'span.optional' ).length === 0 ) {
				field.append( '<span class="optional">(optional)</span>' );
			}
		}

		// When a different gateway selected, do not make fields optional (aka revert them)
		var make_address_field_required = function( field ) {
			field.find( 'span.optional' ).remove();
			field.parent().addClass( 'validate-required' );
			if (field.find( 'abbr.required' ).length === 0 ) {
				field.append( '<abbr class="required" title="required">*</abbr>' );
			}
		}
	}
} ) ( jQuery, window, document );
