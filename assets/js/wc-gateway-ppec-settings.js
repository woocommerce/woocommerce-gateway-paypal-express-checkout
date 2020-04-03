;(function ( $, window, document ) {
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
		}
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
}( jQuery ) );
