( function( $ ) {
	'use strict';

	$( document ).ready( function() {
		$( '.llar-login-cache-notice' ).on( 'click', '.notice-dismiss', function() {
			$.post( llarLoginCacheNotice.ajaxUrl, {
				action: 'dismiss_login_cache_notice',
				sec: llarLoginCacheNotice.nonce
			} );
		} );
	} );
} )( jQuery );
