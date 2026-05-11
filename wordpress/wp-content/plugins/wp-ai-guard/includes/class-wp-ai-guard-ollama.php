<?php
/**
 * AI handler for WP-AI-Guard using local Ollama.
 *
 * @package WP_AI_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_AI_Guard_Ollama
 */
class WP_AI_Guard_Ollama {

	/**
	 * API Endpoint for Ollama.
	 */
	const API_URL = 'http://localhost:11434/api/generate';

	/**
	 * Analyze a log entry using local Ollama.
	 *
	 * @param object $log The log object from database.
	 * @return array|bool Result array or false on failure.
	 */
	public function analyze_log( $log ) {
		$model = get_option( 'wp_ai_guard_ollama_model', 'llama3' );

		$prompt = sprintf(
			"Analitza aquest log de trànsit de WordPress i determina si és un atac. Respon EXCLUSIVAMENT amb un format JSON: {\"threat_level\": 0-10, \"type\": \"tipus d'atac\", \"explanation\": \"per què\"}. \n\nLog: IP: %s, Data: %s",
			$log->ip,
			$log->request_data
		);

		$body = array(
			'model'  => $model,
			'prompt' => $prompt,
			'stream' => false,
			'format' => 'json',
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'body'    => wp_json_encode( $body ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 60, // Local AI can be slower
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'WP-AI-Guard [Ollama Error]: ' . $response->get_error_message() );
			return false;
		}

		$body_content = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_content, true );
		
		if ( isset( $data['response'] ) ) {
			// Extract JSON from the response (sometimes AI adds text before/after)
			$result_text = $data['response'];
			if ( preg_match( '/\{.*\}/s', $result_text, $matches ) ) {
				$result_text = $matches[0];
			}
			
			$analysis = json_decode( $result_text, true );

			if ( $analysis ) {
				$this->update_log_analysis( $log->id, $analysis );
				return $analysis;
			} else {
				error_log( 'WP-AI-Guard [Ollama JSON Error]: Failed to parse analysis JSON. Raw response: ' . $data['response'] );
			}
		} else {
			error_log( 'WP-AI-Guard [Ollama API Error]: Invalid response format. Body: ' . $body_content );
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
				'ai_analysis'  => sprintf( "[Ollama: %s] %s", $analysis['type'] ?? 'Desconegut', $analysis['explanation'] ?? '' ),
			),
			array( 'id' => $log_id )
		);
	}
}
