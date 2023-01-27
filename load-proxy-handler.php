<?php

use LLAR\Core\Helpers;

if( ( empty( $proxy_config ) ) || empty( $_POST['log'] ) ) return;

if( !( $username = trim( $_POST['log'] ) ) ) return;

require_once( 'lib/CidrCheck.php' );
require_once( 'core/Helpers.php' );

( new LoadProxyHandler( $username, json_decode( $proxy_config, JSON_FORCE_OBJECT ) ) );

class LoadProxyHandler {

	private $user_login;
	private $user_ip;
	private $gateway;

	public function __construct( $username, $config = array() ) {

		if( empty( $username ) ) return;

		$this->user_login = $username;
		$this->user_ip = Helpers::detect_ip_address( $config['trusted_ip_origins'] );
		$this->gateway = Helpers::detect_gateway();

		if( !empty( $config['acl'] ) ) {

			$this->check_acl_local( $config['acl'] );

		} else if ( !empty( $config['key'] ) ) {

			$this->check_acl_cloud( $config );
		}
	}

	private function check_acl_local( $acl ) {

		if( empty( $acl ) || !is_array( $acl ) ) return;

		if(
			( is_array( $acl['whitelist_usernames'] ) && in_array( $this->user_login, $acl['whitelist_usernames'] ) )
			||
			( is_array( $acl['whitelist_ips'] ) && Helpers::ip_in_range( $this->user_ip, $acl['whitelist_ips'] ) )
		) {
			return;
		}

		if( is_array( $acl['blacklist_usernames'] ) && in_array( $this->user_login, $acl['blacklist_usernames'] ) ) {
			$this->show_error_page();
		} else if ( is_array( $acl['blacklist_ips'] ) && Helpers::ip_in_range( $this->user_ip, $acl['blacklist_ips'] ) ) {
			$this->show_error_page();
		}
	}

	private function check_acl_cloud( $config = array() ) {

	    if( !$config ) return;

		$settings = array();
		if( !empty( $config['settings'] ) ) {

			foreach ( $config['settings'] as $setting_name => $setting_data ) {

				if( in_array( 'acl', $setting_data['methods'] ) ) {

					$settings[$setting_name] = $setting_data['value'];
				}
			}
		}

		$post_data = array(
			'ip'        => Helpers::get_all_ips(),
			'login'     => $this->user_login,
			'gateway'   => Helpers::detect_gateway(),
		);

		if( $settings ) $post_data['settings'] = $settings;

		$ch = curl_init( $config['api'] . '/acl' );

		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			"{$config['header']}: {$config['key']}",
			'Content-Type: application/json; charset=utf-8',
        ) );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $post_data, JSON_FORCE_OBJECT ) );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

		$response = json_decode( curl_exec( $ch ), JSON_FORCE_OBJECT );

		curl_close($ch);

		if( is_array( $response ) && $response['result'] === 'deny' ) {
			$this->show_error_page();
        }
	}

	private function show_error_page() {
		?>
		<div class="llar-load-proxy-error-page">
			<div class="inner-content">
                <h3>Access denied!</h3>
                <p>Your login attempt is blocked.</p>
                <p><a href="/wp-login.php">Go to login page</a></p>
            </div>
		</div>
        <style type="text/css">
            body, html {
                padding: 0;
                margin: 0;
                background-color: #f4f4f4;
                font-family: Arial, Helvetica, sans-serif;
            }
            .llar-load-proxy-error-page {
                text-align: center;
                display: -webkit-box;
                display: -ms-flexbox;
                display: flex;
                -webkit-box-align: center;
                -ms-flex-align: center;
                align-items: center;
                -webkit-box-pack: center;
                -ms-flex-pack: center;
                justify-content: center;
                height: 100vh;
            }
            .llar-load-proxy-error-page .inner-content {
                background-color: #fff;
                padding: 15px;
                border-radius: 7px;
                -webkit-box-shadow: 0 0 4px 1px rgba(0,0,0, .05);
                box-shadow: 0 0 4px 1px rgba(0,0,0, .05);
                width: 100%;
                max-width: 300px;
            }
        </style>
		<?php
		exit();
	}
}