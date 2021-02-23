;(function ( $ ) {
	'use strict';

	var uploadField = {
		frames: [],
		init: function() {
			$( 'button.image_upload' )
				.on( 'click', this.onClickUploadButton );

			$( 'button.image_remove' )
				.on( 'click', this.removeProductImage );

			$( '.wc_ppec_remove_certificate' )
				.on( 'click', this.removeCertificate );

			$( '#woocommerce_ppec_paypal_sandbox_api_signature, #woocommerce_ppec_paypal_api_signature' )
				.on( 'change', this.handleCertificateDisplay );

			$( '#woocommerce_ppec_paypal_sandbox_api_certificate, #woocommerce_ppec_paypal_api_certificate' )
				.on( 'change', this.handleSignatureDisplay );

			$( 'body' ).on( 'wc_ppec_cert_changed', this.handleSignatureDisplayFor );

			// Trigger the signature and cert display handlers on init.
			$( 'body' ).trigger( 'wc_ppec_cert_changed', [ 'sandbox' ] );
			$( 'body' ).trigger( 'wc_ppec_cert_changed', [ 'live' ] );
			$( '#woocommerce_ppec_paypal_sandbox_api_signature' ).trigger( 'change' );
			$( '#woocommerce_ppec_paypal_api_signature' ).trigger( 'change' );

			$( 'input[type=text]' ).on( 'keypress', this.maybeSubmitSettings );
			$( 'input[type=password]' ).on( 'keypress', this.maybeSubmitSettings );
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

		removeCertificate: function( event ) {
			event.preventDefault();
			var environment = $( event.target ).data( 'environment' );

			// Add a hidden element that will trigger the cert to be deleted on save.
			$( event.target ).parent( '.description' ).append( '<input name="woocommerce_ppec_delete_' + environment + '_api_certificate" type="hidden" value="true">' );
			$( event.target ).parent( '.description' ).fadeOut();
			$( 'body' ).trigger( 'wc_ppec_cert_changed', [ environment ] );
		},

		handleCertificateDisplay: function( event ) {
			var is_sandbox    = $( event.target ).attr( 'id' ).search( 'sandbox' ) !== -1;
			var signature     = $( event.target ).val();
			var cert_selector = is_sandbox ? '#woocommerce_ppec_paypal_sandbox_api_certificate' : '#woocommerce_ppec_paypal_api_certificate';

			if ( signature ) {
				$( cert_selector ).closest( 'tr' ).fadeOut();
			} else {
				$( cert_selector ).closest( 'tr' ).fadeIn();
			}
		},

		handleSignatureDisplay: function( event ) {
			var is_sandbox = $( event.target ).attr( 'id' ).search( 'sandbox' ) !== -1;
			$( 'body' ).trigger( 'wc_ppec_cert_changed', [ is_sandbox ? 'sandbox' : 'live' ] );
		},

		handleSignatureDisplayFor: function( event, environment ) {
			var certificate_upload_input = environment === 'sandbox' ? $( '#woocommerce_ppec_paypal_sandbox_api_certificate' )[0] : $( '#woocommerce_ppec_paypal_api_certificate' )[0];

			if ( ! certificate_upload_input ) {
				return;
			}

			var uploaded_new_cert        = certificate_upload_input.files.length > 0;
			var delete_cert_element_name = 'woocommerce_ppec_delete_' + environment + '_api_certificate';
			var cert_removed             = $( '.description > input[name="' + delete_cert_element_name + '"]' ).length > 0;
			var has_existing_cert        = $( certificate_upload_input ).clone().prop( {type:'text'} ).val() !== ''; // We need to clone the file input, turn it into a text field to retrieve the stored value.
			var signature_selector       = environment === 'sandbox' ? '#woocommerce_ppec_paypal_sandbox_api_signature' : '#woocommerce_ppec_paypal_api_signature';

			// If we have an existing cert which hasn't been removed or the user has uploaded a new cert, hide the signature.
			if ( ( has_existing_cert && ! cert_removed ) || uploaded_new_cert ) {
				$( signature_selector ).closest( 'tr' ).fadeOut();
			} else {
				$( signature_selector ).closest( 'tr' ).fadeIn();
			}
		},

		/**
		 * Saves the PayPal Checkout settings when the enter key is pressed inside a text field.
		 *
		 * This is the default WC behaviour, however, the image upload buttons were being 'clicked' instead.
		 *
		 * @param event
		 */
		maybeSubmitSettings: function( event ) {
			// If the enter key is pressed.
			if ( 13 === event.which ) {
				event.preventDefault();
				$( ".woocommerce-save-button[name='save']" ).trigger( 'click' );
			}
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
		var ppec_live_fields      = '#woocommerce_ppec_paypal_api_username, #woocommerce_ppec_paypal_api_password, #woocommerce_ppec_paypal_api_signature, #woocommerce_ppec_paypal_api_certificate, #woocommerce_ppec_paypal_api_subject';
		var ppec_sandbox_fields   = '#woocommerce_ppec_paypal_sandbox_api_username, #woocommerce_ppec_paypal_sandbox_api_password, #woocommerce_ppec_paypal_sandbox_api_signature, #woocommerce_ppec_paypal_sandbox_api_certificate, #woocommerce_ppec_paypal_sandbox_api_subject';

		var enable_toggle         = $( 'a.ppec-toggle-settings' ).length > 0;
		var enable_sandbox_toggle = $( 'a.ppec-toggle-sandbox-settings' ).length > 0;

		// as of v1.7.0 this option is enabled by default, but can be modified using a filter. Hiding the checkbox will let the settings page hide/show the correct settings depending on whether spb is enabled or disabled
		$( '#woocommerce_ppec_paypal_use_spb' ).closest( 'tr' ).hide();

		$( '#woocommerce_ppec_paypal_environment' ).on( 'change', function(){
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
		}).trigger( 'change' );

		$( '#woocommerce_ppec_paypal_enabled' ).on( 'change', function(){
			if ( $( this ).is( ':checked' ) ) {
				$( ppec_mark_fields ).closest( 'tr' ).show();
			} else {
				$( ppec_mark_fields ).closest( 'tr' ).hide();
			}
		}).trigger( 'change' );

		$( '#woocommerce_ppec_paypal_paymentaction' ).on( 'change', function(){
			if ( 'sale' === $( this ).val() ) {
				$( '#woocommerce_ppec_paypal_instant_payments' ).closest( 'tr' ).show();
			} else {
				$( '#woocommerce_ppec_paypal_instant_payments' ).closest( 'tr' ).hide();
			}
		}).trigger( 'change' );

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

		// Handle Credit settings.
		var creditSettings = {
			init: function() {
				var refreshOnChange = [ 'button_layout', 'hide_funding_methods[]', 'credit_enabled', 'credit_message_enabled', 'credit_message_layout', 'credit_message_logo' ];
				var selector        = $.map( refreshOnChange, function( val ) { return '[name^="woocommerce_ppec_paypal_"][name$="' + val + '"]'; } ).join( ', ' );

				$( selector ).on( 'change', this.refreshUI );

				// Trigger this to configure initial state for cart settings.
				$( '#woocommerce_ppec_paypal_credit_enabled' ).trigger( 'change' );
				$( '#woocommerce_ppec_paypal_hide_funding_methods' ).trigger( 'change' );
			},

			refreshUI: function( event ) {
				var $contextSettings = $( event.target ).closest( 'table' );
				var $creditSettings  = $contextSettings.find( '[name*="credit_"]' );
				var $creditToggle    = $creditSettings.filter( '[name$="credit_enabled"]' );
				var $messageToggle   = $creditSettings.filter( '[name$="credit_message_enabled"]' );
				var $messageSettings = $creditSettings.filter( '[name*="credit_message"]' );
				var $messageLayout   = $creditSettings.filter( '[name$="credit_message_layout"]' );
				var $messageLogo     = $creditSettings.filter( '[name$="credit_message_logo"]' );
				var creditEnabled    = false;

				if ( 'horizontal' === $contextSettings.find( '[name$="button_layout"]' ).val() ) {
					creditEnabled = $creditToggle.is( ':checked' ) && ! $creditToggle.is(':disabled');
				} else {
					creditEnabled = ( -1 === $.inArray( 'CREDIT', $contextSettings.find( '[name$="hide_funding_methods[]"]' ).val() ) );
				}

				// Hide Credit settings if Credit is not enabled.
				if ( ! creditEnabled ) {
					$creditSettings.not( $creditToggle ).closest( 'tr' ).hide();
					return;
				}

				// Show the Credit message toggle.
				$messageToggle.closest( 'tr' ).show();

				// Hide messaging related settings if Credit message is not enabled.
				if ( ! $messageToggle.is( ':checked' ) ) {
					$messageSettings.not( $messageToggle ).closest( 'tr' ).hide();
					return;
				}

				// Display layout setting.
				$messageLayout.closest( 'tr' ).show();

				// Display layout specific settings.
				switch ( $messageLayout.val() ) {
					case 'flex':
						$messageSettings.not( $messageToggle ).not( $messageLayout ).not( '[name*="_flex_"]' ).closest( 'tr' ).hide();
						$messageSettings.filter( '[name*="_flex_"]' ).closest( 'tr' ).show();
						break;
					case 'text':
					default:
						$messageSettings.filter( '[name*="_flex_"] ').closest( 'tr' ).hide();
						$messageLogo.closest( 'tr' ).show();
						$messageSettings.filter( '[name$="logo_position"]' ).closest( 'tr' ).toggle( 'primary' === $messageLogo.val() || 'alternative' === $messageLogo.val() );
						$messageSettings.filter( '[name$="text_color"]' ).closest( 'tr' ).show();
						break;
				}
			}
		};
		creditSettings.init();

		$( '.woocommerce_ppec_paypal_button_layout' ).on( 'change', function( event ) {
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
				! button_size.val() && button_size.val( 'responsive' ).trigger( 'change' );
			}
		} ).trigger( 'change' );

		// Hide default layout and size settings if they'll be overridden anyway.
		function showHideDefaultButtonSettings() {
			var display =
				$( '#woocommerce_ppec_paypal_cart_checkout_enabled' ).is( ':checked' ) ||
				( $( '#woocommerce_ppec_paypal_checkout_on_single_product_enabled' ).is( ':checked' ) && ! $( '#woocommerce_ppec_paypal_single_product_settings_toggle' ).is( ':checked' ) ) ||
				( $( '#woocommerce_ppec_paypal_mark_enabled' ).is( ':checked' ) && ! $( '#woocommerce_ppec_paypal_mark_settings_toggle' ).is( ':checked' ) );

			$( '#woocommerce_ppec_paypal_button_layout, #woocommerce_ppec_paypal_button_size, #woocommerce_ppec_paypal_hide_funding_methods, #woocommerce_ppec_paypal_credit_enabled' ).closest( 'tr' ).toggle( display );
			display && $( '#woocommerce_ppec_paypal_button_layout' ).trigger( 'change' );
		}

		// Toggle mini-cart section based on whether checkout on cart page is enabled
		$( '#woocommerce_ppec_paypal_cart_checkout_enabled' ).on( 'change', function( event ) {
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
			checked && $( '#woocommerce_ppec_paypal_mini_cart_settings_toggle' ).trigger( 'change' );
			showHideDefaultButtonSettings();
		} ).trigger( 'change' );

		$( '#woocommerce_ppec_paypal_mini_cart_settings_toggle' ).on( 'change', function( event ) {
			// Only show settings specific to mini-cart if configured to override global settings.
			var checked = $( event.target ).is( ':checked' );
			$( '.woocommerce_ppec_paypal_mini_cart' ).closest( 'tr' ).toggle( checked );
			checked && $( '#woocommerce_ppec_paypal_mini_cart_button_layout' ).trigger( 'change' );
			showHideDefaultButtonSettings();
		} ).trigger( 'change' );

		$( '#woocommerce_ppec_paypal_checkout_on_single_product_enabled, #woocommerce_ppec_paypal_single_product_settings_toggle' ).on( 'change', function( event ) {
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
				$( '#woocommerce_ppec_paypal_single_product_button_layout' ).trigger( 'change' );
				$( '#woocommerce_ppec_paypal_single_product_credit_enabled' ).trigger( 'change' );
			}
			showHideDefaultButtonSettings();
		} ).trigger( 'change' );

		$( '#woocommerce_ppec_paypal_mark_enabled, #woocommerce_ppec_paypal_mark_settings_toggle' ).on( 'change', function() {
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
				$( '#woocommerce_ppec_paypal_mark_button_layout' ).trigger( 'change' );
				$( '#woocommerce_ppec_paypal_mark_credit_enabled' ).trigger( 'change' );
			}
			showHideDefaultButtonSettings();
		} ).trigger( 'change' );

		// Make sure handlers are only attached once if script is loaded multiple times.
		$( '#woocommerce_ppec_paypal_use_spb' ).off( 'change' );

		$( '#woocommerce_ppec_paypal_use_spb' ).on( 'change', function( event ) {
			var checked = $( event.target ).is( ':checked' );

			// Show settings specific to Smart Payment Buttons only if enabled.
			$( '.woocommerce_ppec_paypal_spb' ).not( 'h3 ').closest( 'tr' ).toggle( checked );
			$( '.woocommerce_ppec_paypal_spb' ).filter( 'h3' ).next( 'p' ).addBack().toggle( checked );

			if ( checked ) {
				// Trigger all logic that controls visibility of other settings.
				$( '.woocommerce_ppec_paypal_visibility_toggle' ).trigger( 'change' );
			} else {
				// If non-SPB mode is enabled, show all settings that may have been hidden.
				$( '#woocommerce_ppec_paypal_button_size, #woocommerce_ppec_paypal_credit_enabled' ).closest( 'tr' ).show();
			}

			// Hide 'Responsive' button size option in SPB mode, and make sure to show 'Small' option.
			var button_size = $( '#woocommerce_ppec_paypal_button_size' ).removeClass( 'enhanced' );
			button_size.find( 'option[value=\"responsive\"]' ).prop( 'disabled', ! checked );
			! checked && button_size.find( 'option[value=\"small\"]' ).prop( 'disabled', false );
			$( document.body ).trigger( 'wc-enhanced-select-init' );
		} ).trigger( 'change' );

		// Reset button size values to default when switching modes.
		$( '#woocommerce_ppec_paypal_use_spb' ).on( 'change', function( event ) {
			if ( $( event.target ).is( ':checked' ) ) {
				// In SPB mode, set to recommended 'Responsive' value so it is not missed.
				$( '#woocommerce_ppec_paypal_button_size' ).val( 'responsive' ).trigger( 'change' );
			} else if ( ! $( '#woocommerce_ppec_paypal_button_size' ).val() ) {
				// Set back to original default for non-SPB mode.
				$( '#woocommerce_ppec_paypal_button_size' ).val( 'large' ).trigger( 'change' );
			}
		} );
	} );
}( jQuery ) );
