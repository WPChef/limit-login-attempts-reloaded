<?php

namespace LLAR\Core\Digest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DigestStorage {
	const POST_TYPE = 'llar_digest_day';

	/**
	 * Register internal CPT for daily digest stats.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Digest Days', 'limit-login-attempts-reloaded' ),
					'singular_name' => __( 'Digest Day', 'limit-login-attempts-reloaded' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'query_var'           => false,
				'rewrite'             => false,
				'map_meta_cap'        => true,
				'supports'            => array( 'custom-fields' ),
			)
		);
	}
}
