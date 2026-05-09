<?php
/**
 * Database handler for WP-AI-Guard.
 *
 * @package WP_AI_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_AI_Guard_DB
 */
class WP_AI_Guard_DB {

	/**
	 * Table name without prefix.
	 */
	const TABLE_NAME = 'wpguard_logs';

	/**
	 * Initialize the database table.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			ip varchar(45) NOT NULL,
			request_data text NOT NULL,
			threat_score int(3) DEFAULT 0,
			ai_analysis text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
