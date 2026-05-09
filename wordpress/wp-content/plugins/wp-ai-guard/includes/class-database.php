<?php
/**
 * Database management class for WP-AI-Guard.
 *
 * @package WP_AI_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WP_AI_Guard_Database
 *
 * Handles database table creation and management.
 */
class WP_AI_Guard_Database {

	/**
	 * Table name without prefix.
	 */
	const TABLE_NAME = 'wpguard_logs';

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the logs table in the database.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			ip_address varchar(45) NOT NULL,
			request_uri text NOT NULL,
			request_method varchar(10) NOT NULL,
			post_data longtext DEFAULT NULL,
			threat_level varchar(10) DEFAULT 'none' NOT NULL,
			threat_type varchar(100) DEFAULT NULL,
			gemini_analysis longtext DEFAULT NULL,
			is_blocked tinyint(1) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get the total number of logs in the table.
	 *
	 * @return int
	 */
	public static function get_log_count() {
		global $wpdb;
		$table_name = self::get_table_name();
		
		// Check if table exists first to avoid errors
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return 0;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
	}
}
