jQuery(document).ready(function() {

	/**
	 * Get active environment.
	 *
	 * @return {String}
	 */
	function get_active_env() {
		return jQuery( '#woo_pp_environment option' ).filter( ':selected' ).val();
	}

	/**
	 * Get row class of active environment.
	 *
	 * @return {String}
	 */
	function get_active_env_row_class() {
		return 'woo_pp_' + get_active_env();
	}

	/**
	 * Get row class to hide based on active environment.
	 *
	 * @return {String}
	 */
	function get_row_class_to_hide() {
		if ( 'live' === get_active_env() ) {
			return 'woo_pp_sandbox';
		}

		return 'woo_pp_live';
	}

	/**
	 * Get API style of active environment.
	 *
	 * @return {String}
	 */
	function get_active_env_api_style() {
		return jQuery( '#' + get_active_env_row_class() + '_api_style option' ).filter( ':selected' ).val();
	}

	/**
	 * Get API style class to hide based on active API style.
	 *
	 * @return {String}
	 */
	function get_api_style_class_to_hide() {
		var api_style_to_hide = 'signature';

		if ( 'signature' === get_active_env_api_style() ) {
			api_style_to_hide = 'certificate';
		}

		return get_active_env_row_class() + '_' + api_style_to_hide;
	}

	/**
	 * Checks whether IPS is enabled or not.
	 *
	 * @return {Bool}
	 */
	function is_ips_enabled() {
		return jQuery( '.ips-enabled' ).length > 0;
	}

	/**
	 * Hide API credential fields if IPS is enabled.
	 */
	function maybe_hide_api_credential_fields() {
		if ( is_ips_enabled() ) {
			jQuery( '.api-credential-row' ).hide();
		}
	}

	/**
	 * Event handler to toggle API credential fields.
	 *
	 * @param {Object} e
	 */
	function toggle_api_credential_fields_handler( e ) {
		var a = jQuery( e.target ),
			isHidden = jQuery( e.target ).hasClass( 'api-credential-fields-hidden' );

		if ( isHidden ) {
			jQuery( '.' + get_active_env_row_class() + '.api-credential-row' ).show();
			jQuery( '.' + get_active_env_row_class() + '_' + get_active_env_api_style() + '.api-credential-row' ).show();

			a.text( a.data( 'hide-text' ) );
		} else {
			jQuery( '.' + get_active_env_row_class() + '.api-credential-row' ).hide();
			jQuery( '.' + get_active_env_row_class() + '_' + get_active_env_api_style() + '.api-credential-row' ).hide();

			a.text( a.data( 'show-text' ) );
		}

		a.toggleClass( 'api-credential-fields-hidden' );

		e.preventDefault();
	}

	jQuery( '.toggle-api-credential-fields' ).click( toggle_api_credential_fields_handler );

	var env_to_hide = get_row_class_to_hide();

	jQuery( '.' + env_to_hide ).hide();
	jQuery( '.' + env_to_hide + '_signature' ).hide();
	jQuery( '.' + env_to_hide + '_certificate' ).hide();

	jQuery( '.' + get_api_style_class_to_hide() ).hide();

	maybe_hide_api_credential_fields();

	jQuery( '#woo_pp_environment' ).change(function() {
		var env = jQuery( '#woo_pp_environment option' ).filter( ':selected' ).val();
		var env_to_hide = '';
		var env_to_show = '';
		if ( 'live' == env ) {
			env_to_hide = 'sandbox';
			env_to_show = 'live';
		} else {
			env_to_hide = 'live';
			env_to_show = 'sandbox';
		}

		jQuery( '.woo_pp_' + env_to_hide ).hide();
		jQuery( '.woo_pp_' + env_to_hide + '_signature' ).hide();
		jQuery( '.woo_pp_' + env_to_hide + '_certificate' ).hide();

		jQuery( '.woo_pp_' + env_to_show ).show();

		var style = jQuery( '#woo_pp_' + env_to_show + '_api_style option' ).filter( ':selected' ).val();
		var style_to_hide = '';
		if ( 'signature' == style ) {
			style_to_hide = 'certificate';
		} else {
			style_to_hide = 'signature';
		}

		jQuery( '.woo_pp_' + env_to_show + '_' + style_to_hide ).hide();
		jQuery( '.woo_pp_' + env_to_show + '_' + style ).show();

		maybe_hide_api_credential_fields();

		var apiCredentialsToggler = jQuery( '.toggle-api-credential-fields' );
		apiCredentialsToggler.addClass( 'api-credential-fields-hidden' ).text( apiCredentialsToggler.data( 'show-text' ) );
	});

	jQuery( '#woo_pp_live_api_style' ).change(function() {
		var style = jQuery( '#woo_pp_live_api_style option' ).filter( ':selected' ).val();
		var style_to_hide = '';

		if ( 'signature' == style ) {
			style_to_hide = 'certificate';
		} else {
			style_to_hide = 'signature';
		}

		jQuery( '.woo_pp_live_' + style_to_hide ).hide();
		jQuery( '.woo_pp_live_' + style ).show();
	});

	jQuery( '#woo_pp_sandbox_api_style' ).change(function() {
		var style = jQuery( '#woo_pp_sandbox_api_style option' ).filter( ':selected' ).val();
		var style_to_hide = '';

		if ( 'signature' == style ) {
			style_to_hide = 'certificate';
		} else {
			style_to_hide = 'signature';
		}

		jQuery( '.woo_pp_sandbox_' + style_to_hide ).hide();
		jQuery( '.woo_pp_sandbox_' + style ).show();
	});

});
