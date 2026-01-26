<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Constants
 * Centralized constants for MFA functionality
 */
class MfaConstants {
	/**
	 * Length of each rescue code (characters)
	 */
	const CODE_LENGTH = 64;

	/**
	 * Number of rescue codes to generate
	 */
	const CODE_COUNT = 10;

	/**
	 * Maximum rescue link verification attempts per IP per hour
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Rescue link TTL in seconds (5 minutes)
	 */
	const RESCUE_LINK_TTL = 300;

	/**
	 * MFA temporary disable duration in seconds (1 hour)
	 */
	const MFA_DISABLE_DURATION = 3600; // HOUR_IN_SECONDS

	/**
	 * Rate limiting period for rescue attempts (1 hour)
	 */
	const RATE_LIMIT_PERIOD = 3600; // HOUR_IN_SECONDS

	/**
	 * Transient key prefix for rescue codes
	 */
	const TRANSIENT_RESCUE_PREFIX = 'llar_rescue_';

	/**
	 * Transient key prefix for rescue attempts rate limiting
	 */
	const TRANSIENT_ATTEMPTS_PREFIX = 'llar_rescue_attempts_';

	/**
	 * Transient key for MFA temporary disable
	 */
	const TRANSIENT_MFA_DISABLED = 'llar_mfa_temporarily_disabled';

	/**
	 * Transient key for MFA checkbox state
	 */
	const TRANSIENT_CHECKBOX_STATE = 'llar_mfa_checkbox_state';

	/**
	 * Checkbox state TTL in seconds (5 minutes)
	 */
	const CHECKBOX_STATE_TTL = 300;
}
