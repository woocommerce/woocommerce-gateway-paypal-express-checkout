jQuery( document ).ready( function( $ ) {
	'use strict';

	// create namespace to avoid any possible conflicts
	$.wc_ppec_admin = {
		init: function() {

			// sandbox/live credential toggle
			$( '#woocommerce_paypal_express_checkout_environment' ).change( function() {
				var usernameLive = $( '#woocommerce_paypal_express_checkout_live_api_username' ).parents( 'tr' ).eq(0),
					passwordLive = $( '#woocommerce_paypal_express_checkout_live_api_password' ).parents( 'tr' ).eq(0),
					sigLive = $( '#woocommerce_paypal_express_checkout_live_api_signature' ).parents( 'tr' ).eq(0),
					subjectLive = $( '#woocommerce_paypal_express_checkout_live_api_subject' ).parents( 'tr' ).eq(0),
					certLive = $( '#woocommerce_paypal_express_checkout_live_api_certificate' ).parents( 'tr' ).eq(0),
					usernameSandbox = $( '#woocommerce_paypal_express_checkout_sb_api_password' ).parents( 'tr' ).eq(0),
					passwordSandbox = $( '#woocommerce_paypal_express_checkout_sb_api_password' ).parents( 'tr' ).eq(0),
					sigSandbox = $( '#woocommerce_paypal_express_checkout_sb_api_signature' ).parents( 'tr' ).eq(0),
					subjectSandbox = $( '#woocommerce_paypal_express_checkout_sb_api_subject' ).parents( 'tr' ).eq(0),
					certSandbox = $( '#woocommerce_paypal_express_checkout_sb_api_certificate' ).parents( 'tr' ).eq(0);

				if ( 'sandbox' === $( this ).val() ) {
					usernameLive.hide();
					passwordLive.hide();
					sigLive.hide();
					subjectLive.hide();
					certLive.hide();
					usernameSandbox.show();
					passwordSandbox.show();
					sigSandbox.show();
					subjectSandbox.show();
					certSandbox.show();
				} else {
					usernameLive.show();
					passwordLive.show();
					sigLive.show();
					subjectLive.show();
					certLive.show();
					usernameSandbox.hide();
					passwordSandbox.hide();
					sigSandbox.hide();
					subjectSandbox.hide();
					certSandbox.hide();
				}
			}).change();
		}
	}; // close namespace

	$.wc_ppec_admin.init();

// end document ready
});
