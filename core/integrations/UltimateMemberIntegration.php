<?php
// phpcs:ignoreFile WordPress.Files.FileName -- The file name must match the class name; IntegrationManager resolves integration files by class name.

namespace LLAR\Core\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ultimate Member login forms use dynamic field names (e.g. username-31615).
 * MFA and lockout paths that expect $_REQUEST['log'] fall back to get_login_identifier().
 */
class UltimateMemberIntegration extends BaseIntegration {

	/**
	 * @return string
	 */
	public function get_plugin_name() {
		return 'Ultimate Member';
	}

	/**
	 * @return bool
	 */
	public static function is_plugin_active() {
		return function_exists( 'UM' );
	}

	/**
	 * @return void
	 */
	public function register_hooks() {
		// Login limiting / MFA hooks for UM live in LimitLoginAttempts.
		// This class only maps UM form fields for the shared auth flow.
	}

	/**
	 * @return bool
	 */
	public function is_login_page() {
		if ( ! static::is_plugin_active() ) {
			return false;
		}

		return '' !== $this->get_login_identifier();
	}

	/**
	 * @return array|null
	 */
	public function get_login_credentials() {
		$username = $this->extract_field_value(
			array( 'username', 'user_login', 'user_email' ),
			$_POST // phpcs:ignore WordPress.Security.NonceVerification.Missing -- UM verifies its own form nonce.
		);
		$password = $this->extract_field_value(
			array( 'user_password' ),
			$_POST // phpcs:ignore WordPress.Security.NonceVerification.Missing -- UM verifies its own form nonce.
		);

		if ( '' === $username || '' === $password ) {
			return null;
		}

		return array(
			'username' => $username,
			'password' => $password,
		);
	}

	/**
	 * Resolve UM username/email from dynamic form fields (username-{form_id}).
	 *
	 * @return string
	 */
	public function get_login_identifier() {
		$from_request = $this->extract_field_value(
			array( 'username', 'user_login', 'user_email' ),
			$_REQUEST // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only identifier for auth/MFA.
		);
		if ( '' !== $from_request ) {
			return $from_request;
		}

		return $this->extract_field_value(
			array( 'username', 'user_login', 'user_email' ),
			$_POST // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only identifier for auth/MFA.
		);
	}

	/**
	 * Find a posted value by exact key or by UM dynamic suffix (base-{form_id}).
	 *
	 * @param string[]            $base_keys Field meta keys without form id.
	 * @param array<string,mixed> $source    Request/post bag.
	 * @return string Sanitized text for identifiers; unsanitized (only unslashed) for passwords when base is user_password.
	 */
	private function extract_field_value( array $base_keys, array $source ) {
		foreach ( $base_keys as $base_key ) {
			if ( isset( $source[ $base_key ] ) && '' !== $source[ $base_key ] && is_scalar( $source[ $base_key ] ) ) {
				return $this->normalize_field_value( $base_key, $source[ $base_key ] );
			}
		}

		foreach ( $source as $key => $value ) {
			if ( ! is_string( $key ) || ! is_scalar( $value ) || '' === $value ) {
				continue;
			}
			foreach ( $base_keys as $base_key ) {
				if ( strpos( $key, $base_key . '-' ) === 0 ) {
					return $this->normalize_field_value( $base_key, $value );
				}
			}
		}

		return '';
	}

	/**
	 * @param string $base_key Field meta key.
	 * @param mixed  $value    Raw request value.
	 * @return string
	 */
	private function normalize_field_value( $base_key, $value ) {
		$value = wp_unslash( (string) $value );
		if ( 'user_password' === $base_key ) {
			return $value;
		}

		return sanitize_text_field( $value );
	}
}
