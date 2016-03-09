jQuery(document).ready(function() {
	var env = jQuery( '#woo_pp_environment option' ).filter( ':selected' ).val();
	var env_to_hide = '';
	var env_to_show = '';
	if ( 'live' == env ) {
		env_to_hide = 'sb';
		env_to_show = 'live';
	} else {
		env_to_hide = 'live';
		env_to_show = 'sb';
	}

	jQuery( '.woo_pp_' + env_to_hide).hide();
	jQuery( '.woo_pp_' + env_to_hide + '_signature' ).hide();
	jQuery( '.woo_pp_' + env_to_hide + '_certificate' ).hide();

	var style = jQuery( '#woo_pp_' + env_to_show + '_api_style option' ).filter( ':selected' ).val();
	var style_to_hide = '';
	if ( 'signature' == style ) {
		style_to_hide = 'certificate';
	} else {
		style_to_hide = 'signature';
	}

	jQuery( '.woo_pp_' + env_to_show + '_' + style_to_hide ).hide();

	jQuery( '#woo_pp_environment' ).change(function() {
		var env = jQuery( '#woo_pp_environment option' ).filter( ':selected' ).val();
		var env_to_hide = '';
		var env_to_show = '';
		if ( 'live' == env ) {
			env_to_hide = 'sb';
			env_to_show = 'live';
		} else {
			env_to_hide = 'live';
			env_to_show = 'sb';
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

		if ( 'live' == env_to_show ) {
			if ( woo_pp_live_is_rba_enabled ) {
				jQuery( '#woo_pp_req_ba_row' ).show();
			} else {
				jQuery( '#woo_pp_req_ba_row' ).hide();
			}
		} else {
			if ( woo_pp_sb_is_rba_enabled ) {
				jQuery( '#woo_pp_req_ba_row' ).show();
			} else {
				jQuery( '#woo_pp_req_ba_row' ).hide();
			}
		}
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

	jQuery( '#woo_pp_sb_api_style' ).change(function() {
		var style = jQuery( '#woo_pp_sb_api_style option' ).filter( ':selected' ).val();
		var style_to_hide = '';

		if ( 'signature' == style ) {
			style_to_hide = 'certificate';
		} else {
			style_to_hide = 'signature';
		}

		jQuery( '.woo_pp_sb_' + style_to_hide ).hide();
		jQuery( '.woo_pp_sb_' + style ).show();
	});

});
