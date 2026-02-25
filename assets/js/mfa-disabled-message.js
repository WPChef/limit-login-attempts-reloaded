/**
 * Display MFA disabled message on login page
 * Uses the same notification mechanism as other LLAR messages
 */
(function($) {
	'use strict';

	// Use the same notification function as LLAR uses for other messages
	// Check if notification_login_page is already defined globally (from LLAR's login_page_render_js)
	if (typeof window.notification_login_page === 'undefined') {
		window.notification_login_page = function(message) {
			if (!message.length) {
				return false;
			}
			const css = '.llar_notification_login_page { position: fixed; top: 50%; left: 50%; font-size: 120%; line-height: 1.5; width: 365px; z-index: 999999; background: #fffbe0; padding: 20px; color: rgb(121, 121, 121); text-align: center; border-radius: 10px; transform: translate(-50%, -50%); box-shadow: 10px 10px 14px 0 #72757B99;} .llar_notification_login_page h4 { color: rgb(255, 255, 255); margin-bottom: 1.5rem; } .llar_notification_login_page .close-button {position: absolute; top: 0; right: 5px; cursor: pointer; line-height: 1;}';
			const style = document.createElement('style');
			style.appendChild(document.createTextNode(css));
			document.head.appendChild(style);

			$('body').prepend('<div class="llar_notification_login_page"><div class="close-button">&times;</div>' + message + '</div>');

			setTimeout(function() {
				$('.llar_notification_login_page').hide();
			}, 10000);

			$('.llar_notification_login_page').on('click', '.close-button', function() {
				$('.llar_notification_login_page').hide();
			});

			$('body').on('click', function(event) {
				if (!$(event.target).closest('.llar_notification_login_page').length) {
					$('.llar_notification_login_page').hide();
				}
			});
		};
	}

	// Show message when DOM is ready
	$(document).ready(function() {
		if (typeof llarMfaDisabled !== 'undefined' && llarMfaDisabled.showMessage && typeof window.notification_login_page === 'function') {
			window.notification_login_page(llarMfaDisabled.message);
		}
	});

})(jQuery);
