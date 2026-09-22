<?php
/**
 * Detects a page-cached or optimizer-stripped login page.
 *
 * A freshness token (render timestamp + HMAC) is printed on login_footer in two
 * carriers: a hidden probe element and an inline script. The frontend fires one
 * check after the first interaction with the login form; the options page runs a
 * loopback self-check (throttled). Verdicts are stored in a non-autoloaded
 * option and surfaced as an admin notice (AdminNoticesController key
 * 'login-page-cache') with dismiss + auto-heal.
 *
 * @package limit-login-attempts-reloaded
 * @subpackage security
 */

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoginPageCacheDetector {

	/**
	 * Max age of a freshly rendered page (seconds).
	 */
	const TTL = 300;

	/**
	 * Extra seconds tolerated for throttled background tabs (dwell grace).
	 */
	const GRACE = 60;

	/**
	 * Self-check throttle (seconds).
	 */
	const SELF_CHECK_INTERVAL = HOUR_IN_SECONDS;

	/**
	 * Option name for the detected issue.
	 */
	const OPTION_NAME = 'llar_login_page_cache_issue';

	/**
	 * Script marker searched for in the fetched HTML (stripped-script verdict).
	 */
	const SCRIPT_MARKER = 'llar-login-cache-check';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_probe_script' ) );
		add_action( 'login_footer', array( $this, 'render_probe' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_notice_script' ) );
	}

	/**
	 * Enqueue the external probe script on the login page (in footer, after the
	 * probe element is rendered). The handle doubles as the marker the loopback
	 * self-check looks for in the page HTML.
	 *
	 * @return void
	 */
	public function enqueue_probe_script() {
		wp_enqueue_script(
			self::SCRIPT_MARKER,
			LLA_PLUGIN_URL . 'assets/js/llar-login-cache-check.js',
			array(),
			self::script_version(),
			true
		);
		wp_localize_script(
			self::SCRIPT_MARKER,
			'llarLoginCacheCheck',
			array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) )
		);
	}

	/**
	 * Enqueue the notice dismiss script on admin pages where the notice shows.
	 *
	 * @return void
	 */
	public function enqueue_admin_notice_script() {
		if ( ! $this->has_visible_issue() ) {
			return;
		}
		wp_enqueue_script(
			'llar-admin-login-cache-notice',
			LLA_PLUGIN_URL . 'assets/js/llar-admin-login-cache-notice.js',
			array( 'jquery' ),
			self::script_version(),
			true
		);
		wp_localize_script(
			'llar-admin-login-cache-notice',
			'llarLoginCacheNotice',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'llar-dismiss-login-cache' ),
			)
		);
	}

	/**
	 * Cache-busting version for detector assets.
	 *
	 * @return string
	 */
	private static function script_version() {
		$plugin_data = get_plugin_data( LLA_PLUGIN_DIR . 'limit-login-attempts-reloaded.php' );
		return isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '3.3.7';
	}

	/**
	 * Build the signed freshness token: base64(time) . '.' . hmac-prefix.
	 *
	 * @return string
	 */
	public function build_token() {
		$time = time();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- benign: encodes a plain timestamp, not code.
		return base64_encode( (string) $time ) . '.' . $this->sign( $time );
	}

	/**
	 * HMAC prefix for a render timestamp.
	 *
	 * @param int $time Render timestamp.
	 * @return string 16 hex chars.
	 */
	private function sign( $time ) {
		return substr( wp_hash( $time . '|llar_login_cache_probe' ), 0, 16 );
	}

	/**
	 * Verify a token and return its render timestamp.
	 *
	 * @param string $token Token from the page/AJAX.
	 * @return int|false Unix timestamp, or false when malformed/unsigned.
	 */
	public function parse_token( $token ) {
		if ( ! is_string( $token ) || strpos( $token, '.' ) === false ) {
			return false;
		}
		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return false;
		}
		$raw = base64_decode( $parts[0], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- benign: decodes a plain timestamp, not code.
		if ( false === $raw || ! preg_match( '/^\d{1,12}$/', $raw ) ) {
			return false;
		}
		$time = (int) $raw;
		if ( hash_equals( $this->sign( $time ), $parts[1] ) ) {
			return $time;
		}
		return false;
	}

	/**
	 * Render the hidden probe element on the login page. The markup is read by
	 * the external probe script and by the loopback self-check (token carrier).
	 *
	 * @return void
	 */
	public function render_probe() {
		$token = $this->build_token();
		?>
		<div id="llar-token" data-llar-token="<?php echo esc_attr( $token ); ?>" style="display:none;"></div>
		<?php
	}

	/**
	 * Verdict for a render time at check time, dwell-corrected.
	 *
	 * @param int $render_time Token timestamp.
	 * @param int $dwell       Seconds the page has been open in the browser (0 for self-check).
	 * @return bool True when the page was served from cache.
	 */
	public function is_stale( $render_time, $dwell = 0 ) {
		$token_age = time() - (int) $render_time;
		if ( $token_age < 0 ) {
			$token_age = 0;
		}
		$cache_age = $token_age - max( 0, (int) $dwell );
		return $cache_age > self::TTL + self::GRACE;
	}

	/**
	 * Cache age for a verdict (token age minus dwell).
	 *
	 * @param int $render_time Token timestamp.
	 * @param int $dwell       Seconds the page has been open.
	 * @return int
	 */
	public function cache_age( $render_time, $dwell = 0 ) {
		$token_age = max( 0, time() - (int) $render_time );
		return max( 0, $token_age - max( 0, (int) $dwell ) );
	}

	/**
	 * Frontend AJAX check: verify the referer host, judge the token, record or heal.
	 *
	 * @return void
	 */
	public function handle_frontend_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- deliberately no WP nonce: it is stable for anonymous users and survives page cache; the signed freshness token below is the verification.
		$token = isset( $_POST['token'] ) ? (string) wp_unslash( $_POST['token'] ) : '';
		$token = preg_replace( '/[^A-Za-z0-9+\/=\.]/', '', $token );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
		$dwell = isset( $_POST['dwell'] ) ? absint( $_POST['dwell'] ) : 0;

		if ( ! $this->referer_host_ok() ) {
			wp_die( '0', '', 200 );
		}

		$render_time = $this->parse_token( $token );
		if ( false === $render_time ) {
			wp_die( '0', '', 200 );
		}

		if ( $this->is_stale( $render_time, $dwell ) ) {
			$this->record_issue(
				'cached',
				$this->cache_age( $render_time, $dwell ),
				'frontend',
				wp_login_url()
			);
		} else {
			$this->heal_issue();
		}

		wp_die( '0', '', 200 );
	}

	/**
	 * When a referer is present, its host must match the site host.
	 *
	 * @return bool
	 */
	private function referer_host_ok() {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return true;
		}
		$referer_host = wp_parse_url( (string) wp_unslash( $_SERVER['HTTP_REFERER'] ), PHP_URL_HOST );
		$home_host    = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $referer_host ) && is_string( $home_host ) && 0 === strcasecmp( $referer_host, $home_host );
	}

	/**
	 * Loopback self-check of the login URL (throttled, admins only).
	 *
	 * @return void
	 */
	public function maybe_self_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_transient( 'llar_login_cache_self_check' ) ) {
			return;
		}
		set_transient( 'llar_login_cache_self_check', 1, self::SELF_CHECK_INTERVAL );

		$url      = wp_login_url();
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 5,
				'sslverify'  => false,
				'user-agent' => 'LLAR login page cache check',
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return; // Loopback blocked — stay silent, the frontend path still covers detection.
		}

		$html    = (string) wp_remote_retrieve_body( $response );
		$headers = array(
			'x-cache'       => (string) wp_remote_retrieve_header( $response, 'x-cache' ),
			'age'           => (string) wp_remote_retrieve_header( $response, 'age' ),
			'cache-control' => (string) wp_remote_retrieve_header( $response, 'cache-control' ),
		);

		$render_time = $this->extract_token_time( $html );
		if ( false === $render_time ) {
			// No token at all: the probe markup is being stripped.
			$this->record_issue( 'stripped', 0, 'self-check', $url, $headers );
			return;
		}

		if ( false === strpos( $html, self::SCRIPT_MARKER ) ) {
			// Token survived but the inline script is gone: an optimizer strips scripts.
			$this->record_issue( 'stripped', 0, 'self-check', $url, $headers );
			return;
		}

		if ( $this->is_stale( $render_time, 0 ) ) {
			$this->record_issue( 'cached', $this->cache_age( $render_time, 0 ), 'self-check', $url, $headers );
			return;
		}

		// Fresh page: heal, but never silently clear an issue found by the frontend
		// path (a loopback request can bypass the edge cache that real visitors hit).
		$issue = $this->get_issue();
		if ( null === $issue || 'frontend' !== $issue['source'] ) {
			$this->heal_issue();
		}
	}

	/**
	 * Extract and verify the probe token from page HTML.
	 *
	 * @param string $html Fetched login page HTML.
	 * @return int|false Render timestamp or false when the probe is absent/invalid.
	 */
	public function extract_token_time( $html ) {
		if ( ! preg_match( '/data-llar-token="([^"]+)"/', $html, $matches ) ) {
			return false;
		}
		return $this->parse_token( $matches[1] );
	}

	/**
	 * Record (or refresh) the issue option.
	 *
	 * @param string $status 'cached' or 'stripped'.
	 * @param int    $age    Cache age in seconds.
	 * @param string $source 'frontend' or 'self-check'.
	 * @param string $url    Login URL checked.
	 * @param array  $headers Response headers evidence (self-check only).
	 * @return void
	 */
	public function record_issue( $status, $age, $source, $url, $headers = array() ) {
		$issue = array(
			'status'      => $status,
			'age'         => (int) $age,
			'source'      => $source,
			'detected_at' => time(),
			'url'         => esc_url_raw( $url ),
			'headers'     => array_map( 'sanitize_text_field', array_slice( (array) $headers, 0, 3 ) ),
		);
		if ( get_option( self::OPTION_NAME ) === $issue ) {
			return;
		}
		update_option( self::OPTION_NAME, $issue, false );
	}

	/**
	 * Auto-heal: a fresh pass deletes the stored issue.
	 *
	 * @return void
	 */
	public function heal_issue() {
		if ( false !== get_option( self::OPTION_NAME ) ) {
			delete_option( self::OPTION_NAME );
		}
	}

	/**
	 * Stored issue (validated) or null.
	 *
	 * @return array|null
	 */
	public function get_issue() {
		$issue = get_option( self::OPTION_NAME );
		if ( ! is_array( $issue ) || empty( $issue['status'] ) || empty( $issue['detected_at'] ) ) {
			return null;
		}
		return $issue;
	}

	/**
	 * The notice is visible while an issue exists and was not dismissed after detection.
	 *
	 * @return bool
	 */
	public function has_visible_issue() {
		$issue = $this->get_issue();
		if ( null === $issue ) {
			return false;
		}
		$dismissed_at = isset( $issue['dismissed_at'] ) ? (int) $issue['dismissed_at'] : 0;
		return $dismissed_at < (int) $issue['detected_at'];
	}

	/**
	 * Dismiss callback (AJAX, manage_options): hide until the next detection event.
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '0', '', 403 );
		}
		$issue = $this->get_issue();
		if ( null !== $issue ) {
			$issue['dismissed_at'] = time();
			update_option( self::OPTION_NAME, $issue, false );
		}
		wp_die( '1', '', 200 );
	}
}
