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
			"Analitza aquest trànsit de WordPress i detecta si és un intent d'atac (SQLi, XSS, Directory Traversal, etc.).
			DADES: IP: %s, URL: %s.
			INSTRUCCIÓ: Avalua el risc de forma objectiva segons el contingut del log, independentment de si la IP és local o externa.
			Respon EXCLUSIVAMENT amb JSON: {\"threat_level\": 0-10, \"type\": \"tipus\", \"explanation\": \"per què\"}.
			Log: %s",
			$log->ip,
			$log->url ?? 'Desconeguda',
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
			error_log( 'WP-AI-Guard [Gemini Error]: ' . $response->get_error_message() );
			return false;
		}

		$body_content = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_content, true );
		
		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$result_text = $data['candidates'][0]['content']['parts'][0]['text'];
			
			// Extract JSON part in case the AI adds conversational text
			if ( preg_match( '/\{.*\}/s', $result_text, $matches ) ) {
				$result_text = $matches[0];
			}

			$analysis = json_decode( $result_text, true );

			if ( $analysis ) {
				$this->update_log_analysis( $log->id, $analysis );
				return $analysis;
			} else {
				error_log( 'WP-AI-Guard [Gemini JSON Error]: Failed to parse analysis JSON. Raw text: ' . $data['candidates'][0]['content']['parts'][0]['text'] );
			}
		} else {
			error_log( 'WP-AI-Guard [Gemini API Error]: Invalid response. Body: ' . $body_content );
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
