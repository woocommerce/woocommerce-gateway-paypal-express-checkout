;(function ( $ ) {
	'use strict';

	var uploadField = {
		frames: [],
		init: function() {
			$( 'button.image_upload' )
				.on( 'click', this.onClickUploadButton );

			$( 'button.image_remove' )
				.on( 'click', this.removeProductImage );
		},

		onClickUploadButton: function( event ) {
			event.preventDefault();

			var data = $( event.target ).data();

			// If the media frame already exists, reopen it.
			if ( 'undefined' !== typeof uploadField.frames[ data.fieldId ] ) {
				// Open frame.
				uploadField.frames[ data.fieldId ].open();
				return false;
			}

			// Create the media frame.
			uploadField.frames[ data.fieldId ] = wp.media( {
				title: data.mediaFrameTitle,
				button: {
					text: data.mediaFrameButton
				},
				multiple: false // Set to true to allow multiple files to be selected
			} );

			// When an image is selected, run a callback.
			var context = {
				fieldId: data.fieldId,
			};

			uploadField.frames[ data.fieldId ]
				.on( 'select', uploadField.onSelectAttachment, context );

			// Finally, open the modal.
			uploadField.frames[ data.fieldId ].open();
		},

		onSelectAttachment: function() {
			// We set multiple to false so only get one image from the uploader.
			var attachment = uploadField.frames[ this.fieldId ]
				.state()
				.get( 'selection' )
				.first()
				.toJSON();

			var $field = $( '#' + this.fieldId );
			var $img = $( '<img />' )
				.attr( 'src', getAttachmentUrl( attachment ) );

			$field.siblings( '.image-preview-wrapper' )
				.html( $img );

			$field.val( attachment.id );
			$field.siblings( 'button.image_remove' ).show();
			$field.siblings( 'button.image_upload' ).hide();
		},

		removeProductImage: function( event ) {
			event.preventDefault();
			var $button = $( event.target );
			var data = $button.data();
			var $field = $( '#' + data.fieldId );

			//update fields
			$field.val( '' );
			$field.siblings( '.image-preview-wrapper' ).html( ' ' );
			$button.hide();
			$field.siblings( 'button.image_upload' ).show();
		},
	};

	function getAttachmentUrl( attachment ) {
		if ( attachment.sizes && attachment.sizes.medium ) {
			return attachment.sizes.medium.url;
		}
		if ( attachment.sizes && attachment.sizes.thumbnail ) {
			return attachment.sizes.thumbnail.url;
		}
		return attachment.url;
	}

	function run() {
		uploadField.init();
	}

	$( run );

	// Handle gateway settings.
	$( function() {
		var ppec_mark_fields      = '#woocommerce_ppec_paypal_title, #woocommerce_ppec_paypal_description';
		var ppec_live_fields      = '#woocommerce_ppec_paypal_api_username, #woocommerce_ppec_paypal_api_password, #woocommerce_ppec_paypal_api_signature, #woocommerce_ppec_paypal_api_certificate, #woocommerce_ppec_paypal_api_subject, #woocommerce_ppec_paypal_api_client_id, #woocommerce_ppec_paypal_api_secret';
		var ppec_sandbox_fields   = '#woocommerce_ppec_paypal_sandbox_api_username, #woocommerce_ppec_paypal_sandbox_api_password, #woocommerce_ppec_paypal_sandbox_api_signature, #woocommerce_ppec_paypal_sandbox_api_certificate, #woocommerce_ppec_paypal_sandbox_api_subject, #woocommerce_ppec_paypal_sandbox_api_client_id, #woocommerce_ppec_paypal_sandbox_api_secret';

		var enable_toggle         = $( 'a.ppec-toggle-settings' ).length > 0;
		var enable_sandbox_toggle = $( 'a.ppec-toggle-sandbox-settings' ).length > 0;

		// as of v1.7.0 this option is enabled by default, but can be modified using a filter. Hiding the checkbox will let the settings page hide/show the correct settings depending on whether spb is enabled or disabled
		$( '#woocommerce_ppec_paypal_use_spb' ).closest( 'tr' ).hide();

		$( '#woocommerce_ppec_paypal_environment' ).change(function(){
			$( ppec_sandbox_fields + ',' + ppec_live_fields ).closest( 'tr' ).hide();

			if ( 'live' === $( this ).val() ) {
				$( '#woocommerce_ppec_paypal_api_credentials, #woocommerce_ppec_paypal_api_credentials + p' ).show();
				$( '#woocommerce_ppec_paypal_sandbox_api_credentials, #woocommerce_ppec_paypal_sandbox_api_credentials + p' ).hide();

				if ( ! enable_toggle ) {
					$( ppec_live_fields ).closest( 'tr' ).show();
				}
			} else {
				$( '#woocommerce_ppec_paypal_api_credentials, #woocommerce_ppec_paypal_api_credentials + p' ).hide();
				$( '#woocommerce_ppec_paypal_sandbox_api_credentials, #woocommerce_ppec_paypal_sandbox_api_credentials + p' ).show();

				if ( ! enable_sandbox_toggle ) {
					$( ppec_sandbox_fields ).closest( 'tr' ).show();
				}
			}
		}).change();

		$( '#woocommerce_ppec_paypal_enabled' ).change(function(){
			if ( $( this ).is( ':checked' ) ) {
				$( ppec_mark_fields ).closest( 'tr' ).show();
			} else {
				$( ppec_mark_fields ).closest( 'tr' ).hide();
			}
		}).change();

		$( '#woocommerce_ppec_paypal_paymentaction' ).change(function(){
			if ( 'sale' === $( this ).val() ) {
				$( '#woocommerce_ppec_paypal_instant_payments' ).closest( 'tr' ).show();
			} else {
				$( '#woocommerce_ppec_paypal_instant_payments' ).closest( 'tr' ).hide();
			}
		}).change();

		if ( enable_toggle ) {
			$( document ).off( 'click', '.ppec-toggle-settings' );
			$( document ).on( 'click', '.ppec-toggle-settings', function( e ) {
				$( ppec_live_fields ).closest( 'tr' ).toggle( 'fast' );
				e.preventDefault();
			} );
		}
		if ( enable_sandbox_toggle ) {
			$( document ).off( 'click', '.ppec-toggle-sandbox-settings' );
			$( document ).on( 'click', '.ppec-toggle-sandbox-settings', function( e ) {
				$( ppec_sandbox_fields ).closest( 'tr' ).toggle( 'fast' );
				e.preventDefault();
			} );
		}

		$( '.woocommerce_ppec_paypal_button_layout' ).change( function( event ) {
			if ( ! $( '#woocommerce_ppec_paypal_use_spb' ).is( ':checked' ) ) {
				return;
			}

			// Show settings that pertain to selected layout in same section
			var isVertical = 'vertical' === $( event.target ).val();
			var table      = $( event.target ).closest( 'table' );
			table.find( '.woocommerce_ppec_paypal_vertical' ).closest( 'tr' ).toggle( isVertical );
			table.find( '.woocommerce_ppec_paypal_horizontal' ).closest( 'tr' ).toggle( ! isVertical );

			// Disable 'small' button size option in vertical layout only
			var button_size        = table.find( '.woocommerce_ppec_paypal_button_size' );
			var button_size_option = button_size.find( 'option[value=\"small\"]' );
			if ( button_size_option.prop( 'disabled' ) !== isVertical ) {
				button_size.removeClass( 'enhanced' );
				button_size_option.prop( 'disabled', isVertical );
				$( document.body ).trigger( 'wc-enhanced-select-init' );
				! button_size.val() && button_size.val( 'responsive' ).change();
			}
		} ).change();

		// Hide default layout and size settings if they'll be overridden anyway.
		function showHideDefaultButtonSettings() {
			var display =
				$( '#woocommerce_ppec_paypal_cart_checkout_enabled' ).is( ':checked' ) ||
				( $( '#woocommerce_ppec_paypal_checkout_on_single_product_enabled' ).is( ':checked' ) && ! $( '#woocommerce_ppec_paypal_single_product_settings_toggle' ).is( ':checked' ) ) ||
				( $( '#woocommerce_ppec_paypal_mark_enabled' ).is( ':checked' ) && ! $( '#woocommerce_ppec_paypal_mark_settings_toggle' ).is( ':checked' ) );

			$( '#woocommerce_ppec_paypal_button_layout, #woocommerce_ppec_paypal_button_size, #woocommerce_ppec_paypal_hide_funding_methods, #woocommerce_ppec_paypal_credit_enabled' ).closest( 'tr' ).toggle( display );
			display && $( '#woocommerce_ppec_paypal_button_layout' ).change();
		}

		// Toggle mini-cart section based on whether checkout on cart page is enabled
		$( '#woocommerce_ppec_paypal_cart_checkout_enabled' ).change( function( event ) {
			if ( ! $( '#woocommerce_ppec_paypal_use_spb' ).is( ':checked' ) ) {
				return;
			}

			var checked = $( event.target ).is( ':checked' );
			$( '#woocommerce_ppec_paypal_mini_cart_settings_toggle, .woocommerce_ppec_paypal_mini_cart' )
				.closest( 'tr' )
				.add( '#woocommerce_ppec_paypal_mini_cart_settings' ) // Select title.
					.next( 'p' ) // Select description if present.
				.addBack()
				.toggle( checked );
			checked && $( '#woocommerce_ppec_paypal_mini_cart_settings_toggle' ).change();
			showHideDefaultButtonSettings();
		} ).change();

		$( '#woocommerce_ppec_paypal_mini_cart_settings_toggle' ).change( function( event ) {
			// Only show settings specific to mini-cart if configured to override global settings.
			var checked = $( event.target ).is( ':checked' );
			$( '.woocommerce_ppec_paypal_mini_cart' ).closest( 'tr' ).toggle( checked );
			checked && $( '#woocommerce_ppec_paypal_mini_cart_button_layout' ).change();
			showHideDefaultButtonSettings();
		} ).change();

		$( '#woocommerce_ppec_paypal_checkout_on_single_product_enabled, #woocommerce_ppec_paypal_single_product_settings_toggle' ).change( function( event ) {
			if ( ! $( '#woocommerce_ppec_paypal_use_spb' ).is( ':checked' ) ) {
				return;
			}

			if ( ! $( '#woocommerce_ppec_paypal_checkout_on_single_product_enabled' ).is( ':checked' ) ) {
				// If product page button is disabled, hide remaining settings in section.
				$( '#woocommerce_ppec_paypal_single_product_settings_toggle, .woocommerce_ppec_paypal_single_product' ).closest( 'tr' ).hide();
			} else if ( ! $( '#woocommerce_ppec_paypal_single_product_settings_toggle' ).is( ':checked' ) ) {
				// If product page button is enabled but not configured to override global settings, hide remaining settings in section.
				$( '#woocommerce_ppec_paypal_single_product_settings_toggle' ).closest( 'tr' ).show();
				$( '.woocommerce_ppec_paypal_single_product' ).closest( 'tr' ).hide();
			} else {
				// Show all settings in section.
				$( '#woocommerce_ppec_paypal_single_product_settings_toggle, .woocommerce_ppec_paypal_single_product' ).closest( 'tr' ).show();
				$( '#woocommerce_ppec_paypal_single_product_button_layout' ).change();
			}
			showHideDefaultButtonSettings();
		} ).change();

		$( '#woocommerce_ppec_paypal_mark_enabled, #woocommerce_ppec_paypal_mark_settings_toggle' ).change( function() {
			if ( ! $( '#woocommerce_ppec_paypal_use_spb' ).is( ':checked' ) ) {
				return;
			}

			if ( ! $( '#woocommerce_ppec_paypal_mark_enabled' ).is( ':checked' ) ) {
				// If checkout page button is disabled, hide remaining settings in section.
				$( '#woocommerce_ppec_paypal_mark_settings_toggle, .woocommerce_ppec_paypal_mark' ).closest( 'tr' ).hide();
			} else if ( ! $( '#woocommerce_ppec_paypal_mark_settings_toggle' ).is( ':checked' ) ) {
				// If checkout page button is enabled but not configured to override global settings, hide remaining settings in section.
				$( '#woocommerce_ppec_paypal_mark_settings_toggle' ).closest( 'tr' ).show();
				$( '.woocommerce_ppec_paypal_mark' ).closest( 'tr' ).hide();
			} else {
				// Show all settings in section.
				$( '#woocommerce_ppec_paypal_mark_settings_toggle, .woocommerce_ppec_paypal_mark' ).closest( 'tr' ).show();
				$( '#woocommerce_ppec_paypal_mark_button_layout' ).change();
			}
			showHideDefaultButtonSettings();
		} ).change();

		// Make sure handlers are only attached once if script is loaded multiple times.
		$( '#woocommerce_ppec_paypal_use_spb' ).off( 'change' );

		$( '#woocommerce_ppec_paypal_use_spb' ).change( function( event ) {
			var checked = $( event.target ).is( ':checked' );

			// Show settings specific to Smart Payment Buttons only if enabled.
			$( '.woocommerce_ppec_paypal_spb' ).not( 'h3 ').closest( 'tr' ).toggle( checked );
			$( '.woocommerce_ppec_paypal_spb' ).filter( 'h3' ).next( 'p' ).addBack().toggle( checked );

			if ( checked ) {
				// Trigger all logic that controls visibility of other settings.
				$( '.woocommerce_ppec_paypal_visibility_toggle' ).change();
			} else {
				// If non-SPB mode is enabled, show all settings that may have been hidden.
				$( '#woocommerce_ppec_paypal_button_size, #woocommerce_ppec_paypal_credit_enabled' ).closest( 'tr' ).show();
			}

			// Hide 'Responsive' button size option in SPB mode, and make sure to show 'Small' option.
			var button_size = $( '#woocommerce_ppec_paypal_button_size' ).removeClass( 'enhanced' );
			button_size.find( 'option[value=\"responsive\"]' ).prop( 'disabled', ! checked );
			! checked && button_size.find( 'option[value=\"small\"]' ).prop( 'disabled', false );
			$( document.body ).trigger( 'wc-enhanced-select-init' );
		} ).change();

		// Reset button size values to default when switching modes.
		$( '#woocommerce_ppec_paypal_use_spb' ).change( function( event ) {
			if ( $( event.target ).is( ':checked' ) ) {
				// In SPB mode, set to recommended 'Responsive' value so it is not missed.
				$( '#woocommerce_ppec_paypal_button_size' ).val( 'responsive' ).change();
			} else if ( ! $( '#woocommerce_ppec_paypal_button_size' ).val() ) {
				// Set back to original default for non-SPB mode.
				$( '#woocommerce_ppec_paypal_button_size' ).val( 'large' ).change();
			}
		} );
	} );
}( jQuery ) );
