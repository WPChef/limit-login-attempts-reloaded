<?php
// phpcs:disable WordPress.Files.FileName -- IntegrationManager resolves integration files by class name, so file names cannot match class names.

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

		return $this->is_login_form( $this->get_form_id() );
	}

	/**
	 * @return array|null
	 */
	public function get_login_credentials() {
		$form_id = $this->get_form_id();
		if ( ! $this->is_login_form( $form_id ) ) {
			return null;
		}

		$source   = $this->get_post_data();
		$username = $this->extract_field_value(
			array( 'username', 'user_email', 'user_login' ),
			$source,
			$form_id
		);
		$password = $this->extract_field_value(
			array( 'user_password' ),
			$source,
			$form_id
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
	 * Resolve the UM login identifier from the submitted login form.
	 *
	 * Only the field bound to the posted UM form id is trusted. Plain or
	 * arbitrary username/user_login keys are ignored so a request cannot point
	 * LLAR at a different identifier than the one UM authenticates.
	 *
	 * @return string
	 */
	public function get_login_identifier() {
		$form_id = $this->get_form_id();
		if ( ! $this->is_login_form( $form_id ) ) {
			return '';
		}

		return $this->extract_field_value(
			array( 'username', 'user_email', 'user_login' ),
			$this->get_post_data(),
			$form_id
		);
	}

	/**
	 * @return int
	 */
	private function get_form_id() {
		if ( ! isset( $_POST['form_id'] ) || ! is_scalar( $_POST['form_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- UM verifies its own form nonce.
			return 0;
		}

		return absint( wp_unslash( $_POST['form_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- UM verifies its own form nonce.
	}

	/**
	 * @param int $form_id UM form post id.
	 * @return bool
	 */
	private function is_login_form( $form_id ) {
		if ( $form_id <= 0 || 'um_form' !== get_post_type( $form_id ) ) {
			return false;
		}

		return 'login' === get_post_meta( $form_id, '_um_mode', true );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_post_data() {
		return $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- UM verifies its own form nonce.
	}

	/**
	 * Read a field posted under UM's dynamic name (base-{form_id}).
	 *
	 * @param string[]            $base_keys Field meta keys without form id.
	 * @param array<string,mixed> $source    Request data.
	 * @param int                 $form_id   UM form post id.
	 * @return string Sanitized text for identifiers; unslashed only for passwords.
	 */
	private function extract_field_value( array $base_keys, array $source, $form_id ) {
		if ( $form_id <= 0 ) {
			return '';
		}

		foreach ( $base_keys as $base_key ) {
			$field_name = $base_key . '-' . $form_id;
			if ( isset( $source[ $field_name ] ) && is_scalar( $source[ $field_name ] ) && '' !== $source[ $field_name ] ) {
				return $this->normalize_field_value( $base_key, $source[ $field_name ] );
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
