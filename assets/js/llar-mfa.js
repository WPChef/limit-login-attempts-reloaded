jQuery(document).ready(function($) {

	function setCookie(name, value, seconds = 3600) {
		var expires = "";
		if (seconds) {
			var date = new Date();
			date.setTime(date.getTime() + (seconds * 1000));
			expires = "; expires=" + date.toUTCString();
		}
		document.cookie = name + "=" + (value || "") + expires + "; path=/";
	}

	function getCookie(name) {
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for (var i = 0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) === ' ') c = c.substring(1, c.length);
			if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
		}
		return null;
	}

	function eraseCookie(name) {
		document.cookie = name + "=; Expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
	}
	
	function clearMfaCookies() {
		eraseCookie("mfa_username");
		eraseCookie("mfa_password");
		eraseCookie("mfa_code");
		eraseCookie("mfa_message");
		eraseCookie("mfa_actions_visible");
		eraseCookie("mfa_code_visible");
		eraseCookie("isMfaVerified");
		eraseCookie("mfa_resend_cooldown");
		eraseCookie("mfa_last_request_time");
	}	
	
    var form = $("#loginform");
    var sendCodeButton = $("#send-mfa-code");
    var verifyButton = $("#verify-mfa-code");
    var mfaCodeInput = $("#mfa-code");
    var mfaMessage = $("#mfa-message");
    var mfaActions = $("#mfa-actions");
    var isMfaVerified = localStorage.getItem("isMfaVerified") === "true";


	if (llar_mfa_data.logged_out === 'true') {
		clearMfaCookies();
	}


	$("#user_login").val(getCookie("mfa_username") || '');
	$("#user_pass").val(getCookie("mfa_password") || '');
	mfaCodeInput.val(getCookie("mfa_code") || '');
	mfaMessage.text(getCookie("mfa_message") || '');


	if (getCookie("mfa_actions_visible") === "true") {
		mfaActions.show();
	}
	if (getCookie("mfa_code_visible") === "true") {
		mfaCodeInput.show();
		verifyButton.show();
	}

    /**
     * Setup resend cooldown timer.
     */
	var resendCooldown = parseInt(getCookie("mfa_resend_cooldown"), 10) || llar_mfa_data.mfa_resend_cooldown;
	var lastRequestTime = parseInt(getCookie("mfa_last_request_time"), 10) || 0;
    var currentTime = Math.floor(Date.now() / 1000);
    var remainingTime = lastRequestTime + resendCooldown - currentTime;

    /**
     * Start the resend cooldown timer.
     * 
     * @param {number} timeLeft - Time left in seconds.
     */
	function startResendTimer(timeLeft) {
		if (timeLeft > 0) {
			var currentTime = Math.floor(Date.now() / 1000);
			var savedEndTime = parseInt(getCookie("mfa_timer_end"), 10);

			if (!savedEndTime || currentTime >= savedEndTime) {
				var endTime = currentTime + timeLeft;
				setCookie("mfa_timer_end", endTime, timeLeft); // Set the end time in cookie
			} else {
				endTime = savedEndTime; // Continue using the previous timer
			}

			var resendTimer = setInterval(function() {
				currentTime = Math.floor(Date.now() / 1000);
				var timeLeft = endTime - currentTime;

				if (timeLeft <= 0) {
					clearInterval(resendTimer);
					sendCodeButton.text("Resend Code").prop("disabled", false);
					eraseCookie("mfa_timer_end");
				} else {
					sendCodeButton.text('Resend Code (' + timeLeft + 's)').prop("disabled", true);
				}
			}, 1000);
		}
	}

	var savedEndTime = parseInt(getCookie("mfa_timer_end"), 10);
	var currentTime = Math.floor(Date.now() / 1000);
	if (savedEndTime && currentTime < savedEndTime) {
		var remainingTime = savedEndTime - currentTime;
		startResendTimer(remainingTime);
	}
	/**
	 * Handles form submission and MFA role checking.
	 */
	form.on("submit", function(e) {

		var username = $("#user_login").val();
		var password = $("#user_pass").val();

		if (!password) {
			mfaMessage.text('Enter your password first.');
			return;
		}

		// Prevent login form from submitting if mfa is required
		if (!isMfaVerified) {
			e.preventDefault();

			setCookie("mfa_username", username);
			setCookie("mfa_password", password);

			$.post(
				llar_mfa_data.ajax_url,
				{
					action: "check_mfa_role",
					nonce: llar_mfa_data.nonce,
					password: password,
					username: username
				},
				function(response) {

					if (!response.success) {
						form.off("submit").submit();
						return;
					}

					if (!response.data.requires_mfa) {
						form.off("submit").submit();
						return;
					}

					setCookie("mfa_actions_visible", "true");
					mfaActions.show();
					sendCodeButton.click();
				}
			);
					
		}

	});

	/**
	 * Handles the event when the user clicks the "Send Code" button.
	 */
	sendCodeButton.click(function() {
		var password = $("#user_pass").val();
		if (!password) {
			mfaMessage.text('Enter your password first.');
			return;
		}

		$.post(
			llar_mfa_data.ajax_url,
			{
				action: "send_mfa_code",
				nonce: llar_mfa_data.nonce,
				password: password
			},
			function(response) {
				mfaMessage.text(response.data.message);
				setCookie("mfa_message", response.data.message);

				sendCodeButton.text("Resend Code (60s)").prop("disabled", true);

				setCookie("mfa_resend_cooldown", "60"); 
				setCookie("mfa_last_request_time", Math.floor(Date.now() / 1000));

				// Display the MFA code input field and verify button
				mfaCodeInput.show().css('display', 'block');
				verifyButton.show().css('display', 'block');
				
				setCookie("mfa_code_visible", "true");

				// Start the resend timer
				startResendTimer(60);
			}
		);
	});

	/**
	 * Handles the event when the user clicks the "Verify Code" button.
	 */
	verifyButton.click(function() {
		var mfa_code = mfaCodeInput.val();
		
		if (!mfa_code) {
			var message = 'Enter the mfa code.';
			mfaMessage.text(message);
			setCookie("mfa_message", message);
			return;
		}

		$.post(
			llar_mfa_data.ajax_url,
			{
				action: 'verify_mfa_code',
				username: $("#user_login").val(),
				nonce: llar_mfa_data.nonce,
				mfa_code: mfa_code
			},
			function(response) {
				mfaMessage.text(response.data.message);
				setCookie("mfa_message", response.data.message);

				
				if (!response.success) {
					if (response.data.message.includes("locked out") || response.data.message.includes("Too many failed")) {
						// Disable inputs when user is locked out
						sendCodeButton.prop("disabled", true);
						verifyButton.prop("disabled", true);
						mfaCodeInput.prop("disabled", true);
						
						isMfaVerified = true;
						setCookie("isMfaVerified", "true");

						var lockoutTime = 900; // 15 minutes in seconds
						var lockoutTimer = setInterval(function() {
							lockoutTime--;
							var lockoutMessage = 'Locked out. Try again in ' + lockoutTime + 's';
							mfaMessage.text(lockoutMessage);
							setCookie("mfa_message", lockoutMessage);

							if (lockoutTime <= 0) {
								clearInterval(lockoutTimer);
								sendCodeButton.prop("disabled", false);
								verifyButton.prop("disabled", false);
								mfaCodeInput.prop("disabled", false);

								var tryAgainMessage = 'You can try again now.';
								mfaMessage.text(tryAgainMessage);
								setCookie("mfa_message", tryAgainMessage);
							}
						}, 1000);

					} else {
						mfaCodeInput.val('');
					}
					if(response.success == false){
						document.cookie = "mfa_error=true; path=/;";
						form.off("submit").submit(); 
					}
					
					return;
				}

				if (response.success) {
					isMfaVerified = true; 
					clearMfaCookies();
					form.off("submit").submit(); 
				} else {
					
					isMfaVerified = false; 
					// form.off("submit").submit(); 
				}
			}
		);
	});

});