<?php
/**
 * Monitor handler for WP-AI-Guard.
 *
 * @package WP_AI_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_AI_Guard_Monitor
 */
class WP_AI_Guard_Monitor {

	/**
	 * Initialize the monitor hooks.
	 */
	public function init() {
		add_action( 'init', array( $this, 'check_blocked_ips' ), 1 ); // High priority to block early
		add_action( 'init', array( $this, 'analyze_request' ) );
	}

	/**
	 * Check if the current visitor IP is blocked based on threat score.
	 */
	public function check_blocked_ips() {
		global $wpdb;
		$ip = $this->get_user_ip();
		$table_name = $wpdb->prefix . 'wpguard_logs';

		// Check if this IP has any record with threat_score > 7
		$threat = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(threat_score) FROM $table_name WHERE ip = %s",
			$ip
		) );

		if ( $threat && intval( $threat ) > 7 ) {
			$this->send_block_notification( $ip, $threat );
			wp_die(
				__( 'Access Denied: Your IP has been flagged by WP-AI-Guard due to suspicious activity.', 'wp-ai-guard' ),
				__( 'Security Block', 'wp-ai-guard' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Send an email notification when an IP is blocked.
	 */
	private function send_block_notification( $ip, $threat_score ) {
		$enabled = get_option( 'wpguard_notifications_enabled', '1' );
		if ( '1' !== $enabled ) {
			return;
		}

		$notification_email = get_option( 'wpguard_notification_email', get_option( 'admin_email' ) );
		$subject            = sprintf( '[WP-AI-Guard] IP Blocked: %s', $ip );
		$message            = sprintf(
			"The following IP address has been blocked by WP-AI-Guard:\n\nIP: %s\nThreat Score: %s/10\nTime: %s\n\nPlease check the WP-AI-Guard dashboard for more details.",
			$ip,
			$threat_score,
			current_time( 'mysql' )
		);

		wp_mail( $notification_email, $subject, $message );
	}

	/**
	 * Analyze the incoming request for suspicious content.
	 */
	public function analyze_request() {
		$ip           = $this->get_user_ip();
		$url          = $_SERVER['REQUEST_URI'];
		$post_data    = $_POST;
		$request_data = array(
			'url'  => $url,
			'post' => $post_data,
		);

		$is_suspicious = $this->check_suspicious_data( $request_data );

		if ( $is_suspicious ) {
			$this->log_suspicious_request( $ip, $request_data );
		}
	}

	/**
	 * Get the real user IP address.
	 */
	private function get_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			return $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * Check if the request contains suspicious patterns.
	 */
	private function check_suspicious_data( $data ) {
		$json_data = wp_json_encode( $data );
		
		// Patterns to look for: SQL injection, XSS characters, etc.
		$suspicious_patterns = array(
			'/<[^>]*>/',           // HTML tags (XSS)
			'/[\'"]/',              // Quotes
			'/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|TRUNCATE)\b/i', // SQL Keywords
			'/<script/i',           // Script tag
		);

		foreach ( $suspicious_patterns as $pattern ) {
			if ( preg_match( $pattern, $json_data ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Log the suspicious request to the database.
	 */
	private function log_suspicious_request( $ip, $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpguard_logs';

		$wpdb->insert(
			$table_name,
			array(
				'ip'           => $ip,
				'request_data' => wp_json_encode( $data ),
				'threat_score' => 10, // Default initial score for suspicious detection
				'ai_analysis'  => 'Detected via pattern matching: suspicious characters or SQL keywords.',
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
	}
}
