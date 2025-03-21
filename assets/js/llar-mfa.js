jQuery(document).ready(function($) {
	
    var form = $("#loginform");
    var sendCodeButton = $("#send-mfa-code");
    var verifyButton = $("#verify-mfa-code");
    var mfaCodeInput = $("#mfa-code");
    var mfaMessage = $("#mfa-message");
    var mfaActions = $("#mfa-actions");
    var isMfaVerified = localStorage.getItem("isMfaVerified") === "true";

    /**
     * Clear localStorage if user logs out.
     */
    if (llar_mfa_data.logged_out === 'true') {
        localStorage.clear();
    }

    /**
     * Restore saved form inputs.
     */
    $("#user_login").val(localStorage.getItem("mfa_username") || '');
    $("#user_pass").val(localStorage.getItem("mfa_password") || '');
    mfaCodeInput.val(localStorage.getItem("mfa_code") || '');
    mfaMessage.text(localStorage.getItem("mfa_message") || '');

    /**
     * Restore UI state from localStorage.
     */
    if (localStorage.getItem("mfa_actions_visible") === "true") {
        mfaActions.show();
    }
    if (localStorage.getItem("mfa_code_visible") === "true") {
        mfaCodeInput.show();
        verifyButton.show();
    }

    /**
     * Setup resend cooldown timer.
     */
    var resendCooldown = parseInt(localStorage.getItem("mfa_resend_cooldown"), 10) || llar_mfa_data.mfa_resend_cooldown;
    var lastRequestTime = parseInt(localStorage.getItem("mfa_last_request_time"), 10) || 0;
    var currentTime = Math.floor(Date.now() / 1000);
    var remainingTime = lastRequestTime + resendCooldown - currentTime;

    /**
     * Start the resend cooldown timer.
     * 
     * @param {number} timeLeft - Time left in seconds.
     */
    function startResendTimer(timeLeft) {
        if (timeLeft > 0) {
            sendCodeButton.text('Resend Code (' + timeLeft + 's)').prop("disabled", true);
            var resendTimer = setInterval(function() {
                timeLeft--;
                sendCodeButton.text('Resend Code (' + timeLeft + 's)');
                if (timeLeft <= 0) {
                    clearInterval(resendTimer);
                    sendCodeButton.text("Resend Code").prop("disabled", false);
                    localStorage.removeItem("mfa_resend_cooldown");
                    localStorage.removeItem("mfa_last_request_time");
                }
            }, 1000);
        }
    }

    if (remainingTime > 0) {
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

			localStorage.setItem("mfa_username", username);
			localStorage.setItem("mfa_password", password);

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
						alert(response.data.message);
						return;
					}

					if (!response.data.requires_mfa) {
						form.off("submit").submit();
						return;
					}

					localStorage.setItem("mfa_actions_visible", "true");
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
				localStorage.setItem("mfa_message", response.data.message);

				// Disable the button and initiate resend cooldown
				sendCodeButton.text("Resend Code (60s)").prop("disabled", true);
				localStorage.setItem("mfa_resend_cooldown", "60");
				localStorage.setItem("mfa_last_request_time", Math.floor(Date.now() / 1000));

				// Display the MFA code input field and verify button
				mfaCodeInput.show().css('display', 'block');
				verifyButton.show().css('display', 'block');
				
				localStorage.setItem("mfa_code_visible", "true");

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
			localStorage.setItem("mfa_message", message);
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
				localStorage.setItem("mfa_message", response.data.message);

				if (!response.success) {
					if (response.data.message.includes("locked out") || response.data.message.includes("Too many failed")) {
						// Disable inputs when user is locked out
						sendCodeButton.prop("disabled", true);
						verifyButton.prop("disabled", true);
						mfaCodeInput.prop("disabled", true);
						
						isMfaVerified = true;
						localStorage.setItem("isMfaVerified", "true");

						var lockoutTime = 900; // 15 minutes in seconds
						var lockoutTimer = setInterval(function() {
							lockoutTime--;
							var lockoutMessage = 'Locked out. Try again in ' + lockoutTime + 's';
							mfaMessage.text(lockoutMessage);
							localStorage.setItem("mfa_message", lockoutMessage);

							if (lockoutTime <= 0) {
								clearInterval(lockoutTimer);
								sendCodeButton.prop("disabled", false);
								verifyButton.prop("disabled", false);
								mfaCodeInput.prop("disabled", false);

								var tryAgainMessage = 'You can try again now.';
								mfaMessage.text(tryAgainMessage);
								localStorage.setItem("mfa_message", tryAgainMessage);
							}
						}, 1000);

					} else {
						mfaCodeInput.val('');
					}
					return;
				}

				if (response.success) {
					isMfaVerified = true; 
					localStorage.clear();
					form.off("submit").submit(); 
				}
			}
		);
	});

});