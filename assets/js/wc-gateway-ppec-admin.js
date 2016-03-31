/*globals jQuery, wc_ppec_settings */
( function( $ ) {

	function disable_paypal_standard_section_link() {
		var sections = $( '.woocommerce .subsubsub li' );

		if ( ! sections.length ) {
			return;
		}

		$.each( sections, function() {
			var section = $( this ),
				a = $( 'a', this );

			if ( ! a.length ) {
				return;
			}

			if ( 'wc_gateway_paypal' === get_gateway_slug( a.attr( 'href' ) ) ) {
				a.attr( 'href', '#' ).css({'opacity': 0.3, 'color': 'black', 'cursor': 'default'});
			}
		} );
	}

	function get_gateway_slug( href ) {
		var parsed_url = document.createElement('a'),
			qs,
			slug;

		parsed_url.href = href;
		qs = parsed_url.search.substr(1).split('&');

		qs.forEach( function( el ) {

			var kv = el.split( '=' );

			if ( 2 === kv.length && 'section' === kv[0] ) {
				slug = kv[1];
			}
		} );

		return slug;
	}

	if ( wc_ppec_settings && wc_ppec_settings.enabled ) {
		disable_paypal_standard_section_link();
	}

} )( jQuery );
