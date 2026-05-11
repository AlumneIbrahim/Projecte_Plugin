<?php
/**
 * AI handler for WP-AI-Guard using Gemini API.
 *
 * @package WP_AI_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_AI_Guard_AI
 */
class WP_AI_Guard_AI {

	/**
	 * API Endpoint for Gemini Pro.
	 */
	const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

	/**
	 * Analyze a log entry using Gemini AI.
	 *
	 * @param object $log The log object from database.
	 * @return array|bool Result array or false on failure.
	 */
	public function analyze_log( $log ) {
		$api_key = defined( 'WP_AI_GUARD_API_KEY' ) ? WP_AI_GUARD_API_KEY : get_option( 'wp_ai_guard_api_key' );

		if ( ! $api_key ) {
			return false;
		}

		$prompt = sprintf(
			"Analitza aquest log de trànsit de WordPress i determina si és un atac. Respon EXCLUSIVAMENT amb un format JSON: {\"threat_level\": 0-10, \"type\": \"tipus d'atac\", \"explanation\": \"per què\"}. \n\nLog: IP: %s, Data: %s",
			$log->ip,
			$log->request_data
		);

		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $prompt ),
					),
				),
			),
			'generationConfig' => array(
				'response_mime_type' => 'application/json',
			),
		);

		$response = wp_remote_post(
			add_query_arg( 'key', $api_key, self::API_URL ),
			array(
				'body'    => wp_json_encode( $body ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 60, // Increased timeout for AI
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$result_text = $data['candidates'][0]['content']['parts'][0]['text'];
			$analysis    = json_decode( $result_text, true );

			if ( $analysis ) {
				$this->update_log_analysis( $log->id, $analysis );
				return $analysis;
			}
		}

		return false;
	}

	/**
	 * Update the log in the database with AI results.
	 */
	private function update_log_analysis( $log_id, $analysis ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'wpguard_logs',
			array(
				'threat_score' => intval( $analysis['threat_level'] ),
				'ai_analysis'  => sprintf( "[%s] %s", $analysis['type'], $analysis['explanation'] ),
			),
			array( 'id' => $log_id )
		);
	}
}
