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
		
		// Hook for asynchronous AI analysis via WP-Cron
		add_action( 'wpguard_async_ai_analysis', array( $this, 'run_async_analysis' ), 10, 3 );
	}

	/**
	 * Run AI analysis asynchronously.
	 */
	public function run_async_analysis( $log_id, $ip, $request_data ) {
		$engine = get_option( 'wp_ai_guard_engine' );
		if ( ! $engine ) {
			return;
		}

		$log = (object) array(
			'id'           => $log_id,
			'ip'           => $ip,
			'request_data' => $request_data,
		);

		$ai = ( 'ollama' === $engine ) ? new WP_AI_Guard_Ollama() : new WP_AI_Guard_AI();
		$ai->analyze_log( $log );
	}

	/**
	 * Check if the current visitor IP is blocked based on threat score.
	 */
	public function check_blocked_ips() {
		// 1. Whitelist: Never block logged-in administrators.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// 2. Learning Mode: If enabled, only log but never block.
		if ( defined( 'WP_AI_LEARNING_MODE' ) && WP_AI_LEARNING_MODE ) {
			return;
		}

		global $wpdb;
		$ip = $this->get_user_ip();
		$table_name = $wpdb->prefix . 'wpguard_logs';

		// 3. Dynamic Threshold: Block only if more than 3 suspicious records in the last hour
		// and the average threat score is greater than 8.
		$one_hour_ago = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		
		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) as log_count, AVG(threat_score) as avg_score 
			 FROM $table_name 
			 WHERE ip = %s AND created_at >= %s",
			$ip,
			$one_hour_ago
		) );

		if ( $stats && $stats->log_count > 3 && $stats->avg_score > 8 ) {
			$this->send_block_notification( $ip, $stats->avg_score );
			wp_die(
				__( 'Access Denied: Your IP has been flagged by WP-AI-Guard due to persistent suspicious activity.', 'wp-ai-guard' ),
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
		// Flatten the array to check all values
		$values = $this->get_all_values( $data );
		
		$suspicious_patterns = array(
			'/<[^>]*>/',           // HTML tags (XSS)
			'/[\'"]/',              // Quotes
			'/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|TRUNCATE)\b/i', // SQL Keywords
			'/<script/i',           // Script tag
		);

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) continue;
			foreach ( $suspicious_patterns as $pattern ) {
				if ( preg_match( $pattern, $value ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Recursively get all string values from an array.
	 */
	private function get_all_values( $array ) {
		$values = array();
		foreach ( $array as $val ) {
			if ( is_array( $val ) ) {
				$values = array_merge( $values, $this->get_all_values( $val ) );
			} else {
				$values[] = $val;
			}
		}
		return $values;
	}

	/**
	 * Log the suspicious request to the database and schedule asynchronous AI analysis.
	 */
	private function log_suspicious_request( $ip, $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpguard_logs';
		$json_data  = wp_json_encode( $data );

		$wpdb->insert(
			$table_name,
			array(
				'ip'           => $ip,
				'request_data' => $json_data,
				'threat_score' => 10, // Default initial score
				'ai_analysis'  => '', // Leave empty for AI to fill
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		$log_id = $wpdb->insert_id;

		// SCHEDULE ASYNCHRONOUS ANALYSIS (WP-CRON)
		// This makes the site load instantly while the AI works in the background.
		wp_schedule_single_event( time(), 'wpguard_async_ai_analysis', array( $log_id, $ip, $json_data ) );
		
		// Force trigger WP-Cron to start the analysis immediately
		spawn_cron();
	}
}
