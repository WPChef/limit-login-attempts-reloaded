<?php

use LLAR\Core\Helpers;
use LLAR\Core\Http\Http;

if( empty( $proxy_config ) || !( $username = get_username_from_request() ) ) return;

function get_username_from_request() {

    if( $_SERVER['REQUEST_METHOD'] !== 'POST' ) return false;

	if ( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false && !empty( $_POST['log'] ) ) {
		return trim( $_POST['log'] );
	} else if ( !empty( $_POST['woocommerce-login-nonce'] ) && !empty( $_POST['username'] ) ) {
		return trim( $_POST['username'] );
	} else if( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {

		if( $data = @file_get_contents( 'php://input' ) ) {
			$message = new IXR_Message( $data );

			if ( !$message->parse() || $message->messageType != 'methodCall' ) return false;

			if( !empty( $message->params[1] ) ) return $message->params[1];
        }
	}

	return false;
}

spl_autoload_register(function($class) {

	$namespace = 'LLAR\\';

	$len = strlen( $namespace );
	if (strncmp( $namespace, $class, $len) !== 0) {
		return;
	}

	$relative_class = str_replace('\\', '/', substr( $class, $len ) );
	$relative_class = explode( '/', $relative_class );
	$class_name = array_pop( $relative_class );
	$relative_class = implode( '/', $relative_class );
	$file = dirname( __FILE__ ) . '/' . strtolower( $relative_class ) . '/' . $class_name . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
});

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

		Http::init();

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

		$response = Http::post( $config['api'] . '/acl', array(
            'headers'   => array( "{$config['header']}: {$config['key']}" ),
            'data'      => $post_data
        ) );

		if( empty( $response['data'] ) ) return;

		$response = json_decode( $response['data'], JSON_FORCE_OBJECT );

		if( is_array( $response ) && !empty( $response['result'] ) && $response['result'] === 'deny' ) {
			$this->show_error_page();
        }
	}

	private function show_error_page() {
	    if( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		    header('HTTP/1.0 403 Forbidden');
		    exit;
        }
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

class IXR_Message
{
	var $message;
	var $messageType;  // methodCall / methodResponse / fault
	var $faultCode;
	var $faultString;
	var $methodName;
	var $params;

	// Current variable stacks
	var $_arraystructs = array();   // The stack used to keep track of the current array/struct
	var $_arraystructstypes = array(); // Stack keeping track of if things are structs or array
	var $_currentStructName = array();  // A stack as well
	var $_param;
	var $_value;
	var $_currentTag;
	var $_currentTagContents;
	// The XML parser
	var $_parser;

	function __construct($message)
	{
		$this->message = &$message;
	}

	function parse()
	{
		// first remove the XML declaration
		// merged from WP #10698 - this method avoids the RAM usage of preg_replace on very large messages
		$header = preg_replace( '/<\?xml.*?\?'.'>/s', '', substr( $this->message, 0, 100 ), 1 );
		$this->message = trim( substr_replace( $this->message, $header, 0, 100 ) );
		if ( '' == $this->message ) {
			return false;
		}

		// Then remove the DOCTYPE
		$header = preg_replace( '/^<!DOCTYPE[^>]*+>/i', '', substr( $this->message, 0, 200 ), 1 );
		$this->message = trim( substr_replace( $this->message, $header, 0, 200 ) );
		if ( '' == $this->message ) {
			return false;
		}

		// Check that the root tag is valid
		$root_tag = substr( $this->message, 0, strcspn( substr( $this->message, 0, 20 ), "> \t\r\n" ) );
		if ( '<!DOCTYPE' === strtoupper( $root_tag ) ) {
			return false;
		}
		if ( ! in_array( $root_tag, array( '<methodCall', '<methodResponse', '<fault' ) ) ) {
			return false;
		}

		// Bail if there are too many elements to parse
		$element_limit = 30000;
		if ( $element_limit && 2 * $element_limit < substr_count( $this->message, '<' ) ) {
			return false;
		}

		$this->_parser = xml_parser_create();
		// Set XML parser to take the case of tags in to account
		xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, false);
		// Set XML parser callback functions
		xml_set_object($this->_parser, $this);
		xml_set_element_handler($this->_parser, 'tag_open', 'tag_close');
		xml_set_character_data_handler($this->_parser, 'cdata');
		$chunk_size = 262144; // 256Kb, parse in chunks to avoid the RAM usage on very large messages
		$final = false;
		do {
			if (strlen($this->message) <= $chunk_size) {
				$final = true;
			}
			$part = substr($this->message, 0, $chunk_size);
			$this->message = substr($this->message, $chunk_size);
			if (!xml_parse($this->_parser, $part, $final)) {
				return false;
			}
			if ($final) {
				break;
			}
		} while (true);
		xml_parser_free($this->_parser);

		// Grab the error messages, if any
		if ($this->messageType == 'fault') {
			$this->faultCode = $this->params[0]['faultCode'];
			$this->faultString = $this->params[0]['faultString'];
		}
		return true;
	}

	function tag_open($parser, $tag, $attr)
	{
		$this->_currentTagContents = '';
		$this->currentTag = $tag;
		switch($tag) {
			case 'methodCall':
			case 'methodResponse':
			case 'fault':
				$this->messageType = $tag;
				break;
			/* Deal with stacks of arrays and structs */
			case 'data':    // data is to all intents and puposes more interesting than array
				$this->_arraystructstypes[] = 'array';
				$this->_arraystructs[] = array();
				break;
			case 'struct':
				$this->_arraystructstypes[] = 'struct';
				$this->_arraystructs[] = array();
				break;
		}
	}

	function cdata($parser, $cdata)
	{
		$this->_currentTagContents .= $cdata;
	}

	function tag_close($parser, $tag)
	{
		$valueFlag = false;
		switch($tag) {
			case 'int':
			case 'i4':
				$value = (int)trim($this->_currentTagContents);
				$valueFlag = true;
				break;
			case 'double':
				$value = (double)trim($this->_currentTagContents);
				$valueFlag = true;
				break;
			case 'string':
				$value = (string)trim($this->_currentTagContents);
				$valueFlag = true;
				break;
			case 'value':
				// "If no type is indicated, the type is string."
				if (trim($this->_currentTagContents) != '') {
					$value = (string)$this->_currentTagContents;
					$valueFlag = true;
				}
				break;
			case 'boolean':
				$value = (boolean)trim($this->_currentTagContents);
				$valueFlag = true;
				break;
			case 'base64':
				$value = base64_decode($this->_currentTagContents);
				$valueFlag = true;
				break;
			/* Deal with stacks of arrays and structs */
			case 'data':
			case 'struct':
				$value = array_pop($this->_arraystructs);
				array_pop($this->_arraystructstypes);
				$valueFlag = true;
				break;
			case 'member':
				array_pop($this->_currentStructName);
				break;
			case 'name':
				$this->_currentStructName[] = trim($this->_currentTagContents);
				break;
			case 'methodName':
				$this->methodName = trim($this->_currentTagContents);
				break;
		}

		if ($valueFlag) {
			if (count($this->_arraystructs) > 0) {
				// Add value to struct or array
				if ($this->_arraystructstypes[count($this->_arraystructstypes)-1] == 'struct') {
					// Add to struct
					$this->_arraystructs[count($this->_arraystructs)-1][$this->_currentStructName[count($this->_currentStructName)-1]] = $value;
				} else {
					// Add to array
					$this->_arraystructs[count($this->_arraystructs)-1][] = $value;
				}
			} else {
				// Just add as a parameter
				$this->params[] = $value;
			}
		}
		$this->_currentTagContents = '';
	}
}