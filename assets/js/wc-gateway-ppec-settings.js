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
