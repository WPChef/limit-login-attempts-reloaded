( function() {
	'use strict';

	const MAX_DWELL = 86400;

	function send( token ) {
		let dwellSeconds = 0;
		if ( window.performance && performance.now ) {
			dwellSeconds = Math.round( performance.now() / 1000 );
		}
		if ( dwellSeconds > MAX_DWELL ) {
			dwellSeconds = MAX_DWELL;
		}

		const body = new FormData();
		body.append( 'action', 'llar_login_cache_check' );
		body.append( 'token', token );
		body.append( 'dwell', String( dwellSeconds ) );

		fetch( llarLoginCacheCheck.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).catch( function() {} );
	}

	function init() {
		const probe = document.getElementById( 'llar-token' );
		if ( ! probe ) {
			return;
		}
		const token = probe.getAttribute( 'data-llar-token' );
		const form = document.getElementById( 'loginform' );
		if ( ! token || ! form ) {
			return;
		}

		let sent = false;
		const fire = function() {
			if ( sent ) {
				return;
			}
			sent = true;
			send( token );
		};

		const active = document.activeElement;
		if ( active && form.contains( active ) ) {
			fire();
			return;
		}
		form.addEventListener( 'focusin', fire, { once: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() { init(); } );
	} else {
		init();
	}
} )();
