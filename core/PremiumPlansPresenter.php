<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepares hardcoded Premium compare-table pricing and renders the abstract card view.
 */
class PremiumPlansPresenter {

	/**
	 * Hardcoded annual-billed promo prices for upgradeable plans.
	 *
	 * @return array<string, array{
	 *     price: string,
	 *     price_old: string,
	 *     billing_label: string,
	 *     save_badge: string,
	 *     description_items: string[]
	 * }>
	 */
	public function get_upgrade_plan_pricing() {
		return array(
			'Personal' => array(
				'price'             => '$1.25/mo',
				'price_old'         => '$2.50',
				'billing_label'     => __( 'Billed Annually', 'limit-login-attempts-reloaded' ),
				'save_badge'        => __( 'Save 50%', 'limit-login-attempts-reloaded' ),
				'description_items' => array(
					__( 'Personal blogs, portfolios, and side projects.', 'limit-login-attempts-reloaded' ),
					__( 'For sites where a break-in is a headache, not a business loss.', 'limit-login-attempts-reloaded' ),
					__( 'One site, protected in the cloud before attacks reach your server.', 'limit-login-attempts-reloaded' ),
				),
			),
			'Business' => array(
				'price'             => '$4.17/mo',
				'price_old'         => '$8.33',
				'billing_label'     => __( 'Billed Annually', 'limit-login-attempts-reloaded' ),
				'save_badge'        => __( 'Save 50%', 'limit-login-attempts-reloaded' ),
				'description_items' => array(
					__( 'Sites that make money such as shops, memberships, client work.', 'limit-login-attempts-reloaded' ),
					__( "For anyone who'd lose orders or trust if the site went down.", 'limit-login-attempts-reloaded' ),
					__( 'Block whole countries and 500,000 known attackers automatically.', 'limit-login-attempts-reloaded' ),
				),
			),
		);
	}

	/**
	 * Render the pricing card view with the given values.
	 *
	 * @param array $data View variables (price, price_old, billing_label, save_badge, description_items).
	 * @return string
	 */
	public function render_pricing_card( array $data ) {
		$price             = isset( $data['price'] ) ? (string) $data['price'] : '';
		$price_old         = isset( $data['price_old'] ) ? (string) $data['price_old'] : '';
		$billing_label     = isset( $data['billing_label'] ) ? (string) $data['billing_label'] : '';
		$save_badge        = isset( $data['save_badge'] ) ? (string) $data['save_badge'] : '';
		$description_items = isset( $data['description_items'] ) && is_array( $data['description_items'] )
			? $data['description_items']
			: array();

		if ( '' === $price ) {
			return '';
		}

		$path = LLA_PLUGIN_DIR . 'views/plan-pricing-card.php';
		if ( ! is_readable( $path ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// Relative path in message; log file under plugin root (project debug convention).
				error_log(
					'[' . gmdate( 'c' ) . '] LLAR: missing plan pricing view: views/plan-pricing-card.php' . PHP_EOL,
					3,
					LLA_PLUGIN_DIR . 'debug-wp.log'
				);
			}
			return '';
		}

		ob_start();
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * Build the compare-table pricing row. Features column stays empty via the table loop.
	 * Cards are shown only for plans that are an upgrade from the current plan.
	 *
	 * @param string[] $display_plans Visible plan column names.
	 * @param int      $actual_rate   Current plan rate.
	 * @param array    $plans         Plan name => rate map from array_name_plans().
	 * @return array<string, string>
	 */
	public function build_pricing_row( array $display_plans, $actual_rate, array $plans ) {
		$pricing_data = $this->get_upgrade_plan_pricing();
		$row          = array();

		foreach ( $display_plans as $plan ) {
			$plan_rate = isset( $plans[ $plan ] ) ? (int) $plans[ $plan ] : (int) $plans['Free'];

			if ( $plan_rate <= (int) $actual_rate || ! isset( $pricing_data[ $plan ] ) ) {
				$row[ $plan ] = '';
				continue;
			}

			$row[ $plan ] = $this->render_pricing_card( $pricing_data[ $plan ] );
		}

		return $row;
	}
}
