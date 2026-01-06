<?php

namespace LLAR\Core\Integrations;

use LLAR\Core\Config;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) exit;

abstract class BaseIntegration implements IntegrationInterface
{
	/**
	 * @var LimitLoginAttempts
	 */
	protected $llar_instance;

	/**
	 * @param LimitLoginAttempts $llar_instance
	 */
	public function __construct( LimitLoginAttempts $llar_instance )
	{
		$this->llar_instance = $llar_instance;
	}

	/**
	 * Default implementation - check if plugin function exists
	 *
	 * @return bool
	 */
	public function is_plugin_active(): bool
	{
		return false;
	}

	/**
	 * Default implementation - not a login page
	 *
	 * @return bool
	 */
	public function is_login_page(): bool
	{
		return false;
	}

	/**
	 * Default implementation - no credentials
	 *
	 * @return array|null
	 */
	public function get_login_credentials(): ?array
	{
		return null;
	}

	/**
	 * Default implementation - do nothing
	 *
	 * @param string $message
	 * @return void
	 */
	public function display_error( string $message ): void
	{
		// Default implementation
	}

	/**
	 * Helper method to get error message from LLAR
	 *
	 * @return string
	 */
	protected function get_error_message(): string
	{
		return $this->llar_instance->error_msg();
	}

	/**
	 * Helper method to check if login is allowed
	 *
	 * @return bool
	 */
	protected function is_login_allowed(): bool
	{
		return $this->llar_instance->is_limit_login_ok();
	}

	/**
	 * Default implementation - not a registration page
	 *
	 * @return bool
	 */
	public function is_registration_page(): bool
	{
		return false;
	}

	/**
	 * Default implementation - no registration data
	 *
	 * @return array|null
	 */
	public function get_registration_data(): ?array
	{
		return null;
	}

	/**
	 * Default implementation - do nothing
	 *
	 * @param string $message
	 * @return void
	 */
	public function display_registration_error( string $message ): void
	{
		// Default implementation
	}

	/**
	 * Helper method to check if registration is limited
	 *
	 * @return bool
	 */
	protected function is_registration_limited(): bool
	{
		// Check if registration limiting is enabled in cloud app
		if ( ! $this->llar_instance::$cloud_app ) {
			return false;
		}

		$app_config = Config::get( 'app_config' );
		$limit_registration = isset( $app_config['settings']['limit_registration']['value'] ) 
			? $app_config['settings']['limit_registration']['value'] 
			: '';

		return $limit_registration === 'on';
	}
}

