( function( $ ) {
	'use strict';

	$( document ).ready( function() {
		// The localized data ships in a separate inline <script>; JS optimizers
		// (Perfmatters, WP Rocket, FlyingPress) may strip or delay it while the
		// file still loads. Bail out instead of throwing on click.
		if ( typeof llarReviewNotice === 'undefined' ) {
			return;
		}

		$( '.llar-review-dismiss' ).on( 'click', function( e ) {
			e.preventDefault();
			var type = $( this ).data( 'type' );
			$.post( llarReviewNotice.ajaxUrl, {
				action: 'dismiss_review_notice',
				type: type,
				sec: llarReviewNotice.nonce
			} );
			$( this ).closest( '.llar-notice-review' ).remove();
		} );

		$( '.llar-notice-review' ).on( 'click', '.notice-dismiss', function() {
			var expires = '';
			var date = new Date();
			date.setTime( date.getTime() + ( 30 * 24 * 60 * 60 * 1000 ) );
			expires = '; expires=' + date.toUTCString();
			document.cookie = encodeURIComponent( llarReviewNotice.cookieName ) + '=1' + expires + '; path=/';
		} );
	} );
} )( jQuery );
