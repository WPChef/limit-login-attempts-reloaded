<?php

if( ( empty( $acl ) && empty( $key ) ) || empty( $_POST['log'] ) ) return;

if( !( $username = trim( $_POST['log'] ) ) ) return;

require_once( 'lib/CidrCheck.php' );

(new LoadProxyHandler(
    $username,
    array(
        'acl' => isset( $acl ) ? $acl : null,
        'key' => isset( $key ) ? $key : null,
        'trusted_ip_origins' => isset( $trusted_ip_origins ) ? $trusted_ip_origins : null,
    ))
);

class LoadProxyHandler {

	private $user_login;
	private $user_ip;
	private $trusted_ip_origins;
	private $gateway;

	public function __construct( $username, $config = array() ) {

		if( empty( $username ) ) return;

		$this->user_login = $username;
		$this->trusted_ip_origins = json_decode( $config['trusted_ip_origins'], JSON_FORCE_OBJECT );
		$this->user_ip = $this->detect_ip_address();
		$this->gateway = $this->detect_gateway();

		if( !empty( $config['acl'] ) ) {

			$this->check_acl_local( $config['acl'] );

		} else if ( !empty( $config['key'] ) ) {

			$this->check_acl_cloud( $config['key'] );
		}
	}

	private function detect_ip_address() {
		$trusted_ip_origins = $this->trusted_ip_origins;

		if( empty( $trusted_ip_origins ) || !is_array( $trusted_ip_origins ) ) {

			$trusted_ip_origins = array();
		}

		if( !in_array( 'REMOTE_ADDR', $trusted_ip_origins ) ) {

			$trusted_ip_origins[] = 'REMOTE_ADDR';
		}

		$ip = '';
		foreach ( $trusted_ip_origins as $origin ) {

			if( isset( $_SERVER[$origin] ) && !empty( $_SERVER[$origin] ) ) {

				if( strpos( $_SERVER[$origin], ',' ) !== false ) {

					$origin_ips = explode( ',', $_SERVER[$origin] );
					$origin_ips = array_map( 'trim', $origin_ips );

					if( $origin_ips ) {

						foreach ($origin_ips as $check_ip) {

							if( $this->is_ip_valid( $check_ip ) ) {

								$ip = $check_ip;
								break 2;
							}
						}
					}
				}

				if( $this->is_ip_valid( $_SERVER[$origin] ) ) {

					$ip = $_SERVER[$origin];
					break;
				}
			}
		}

		$ip = preg_replace('/^(\d+\.\d+\.\d+\.\d+):\d+$/', '\1', $ip);

		return $ip;
	}

	private function get_all_ips() {

		$ips = array();

		foreach ( $_SERVER as $key => $value ) {

			if( in_array( $key, array( 'SERVER_ADDR' ) ) ) continue;

			if( filter_var( $value, FILTER_VALIDATE_IP ) ) {

				$ips[$key] = $value;
			}
		}

		if( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && !array_key_exists( 'HTTP_X_FORWARDED_FOR', $ips ) ) {

			$ips['HTTP_X_FORWARDED_FOR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}

		return $ips;
	}

	private function is_ip_valid( $ip ) {
		if( empty( $ip ) ) return false;

		return filter_var( $ip, FILTER_VALIDATE_IP );
	}

	private function detect_gateway() {

		$gateway = 'wp_login';

		if ( isset( $_POST['woocommerce-login-nonce'] ) ) {
			$gateway = 'wp_woo_login';
		} elseif ( isset( $GLOBALS['wp_xmlrpc_server'] ) && is_object( $GLOBALS['wp_xmlrpc_server'] ) ) {
			$gateway = 'wp_xmlrpc';
		}

		return $gateway;
	}

	private function check_acl_local( $acl ) {

		if( !$acl ) return;

		$acl = json_decode( $acl, JSON_FORCE_OBJECT );

		if(
			( is_array( $acl['whitelist_usernames'] ) && in_array( $this->user_login, $acl['whitelist_usernames'] ) )
			||
			( is_array( $acl['whitelist_ips'] ) && $this->ip_in_range( $this->user_ip, $acl['whitelist_ips'] ) )
		) {
			return;
		}

		if( is_array( $acl['blacklist_usernames'] ) && in_array( $this->user_login, $acl['blacklist_usernames'] ) ) {
			$this->show_error_page();
		} else if ( is_array( $acl['blacklist_ips'] ) && $this->ip_in_range( $this->user_ip, $acl['blacklist_ips'] ) ) {
			$this->show_error_page();
		}
	}

	private function check_acl_cloud( $key ) {

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, "https://api.limitloginattempts.com/v1-staging/acl" );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Content-Type'  => 'application/json; charset=utf-8',
			'X-Api-Key'     => $key,
        ) );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( array(
			'ip'        => $this->get_all_ips(),
			'login'     => $this->user_login,
			'gateway'   => $this->detect_gateway()
        ), JSON_FORCE_OBJECT ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		var_dump($response);

		curl_close($ch);

	}

	private function ip_in_range( $ip, $list ) {

		foreach ( $list as $range ) {

			$range = array_map('trim', explode('-', $range) );
			if ( count( $range ) == 1 ) {

				// CIDR
				if( strpos( $range[0], '/' ) !== false && $this->check_ip_cidr( $ip, $range[0] ) ) {

					return true;
				}
				// Single IP
				else if ( (string)$ip === (string)$range[0] ) {

					return true;
				}

			} else {

				$low = ip2long( $range[0] );
				$high = ip2long( $range[1] );
				$needle = ip2long( $ip );

				if ( $low === false || $high === false || $needle === false )
					continue;

				$low = (float)sprintf("%u",$low);
				$high = (float)sprintf("%u",$high);
				$needle = (float)sprintf("%u",$needle);

				if ( $needle >= $low && $needle <= $high )
					return true;
			}
		}

		return false;
	}

	private function check_ip_cidr( $ip, $cidr ) {

		if ( ! $ip || ! $cidr ) {
			return false;
		}

		$cidr_checker = new LLAR\Lib\CidrCheck();

		return $cidr_checker->match( $ip, $cidr );
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
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
            }
            .llar-load-proxy-error-page .inner-content {
                background-color: #fff;
                padding: 15px;
                border-radius: 7px;
                box-shadow: 0 0 4px 1px rgba(0,0,0, .05);
                width: 100%;
                max-width: 300px;
            }
        </style>
		<?php
		exit();
	}
}