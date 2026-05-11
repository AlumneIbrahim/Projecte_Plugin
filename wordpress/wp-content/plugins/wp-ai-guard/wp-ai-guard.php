<?php
/**
 * Plugin Name: WP-AI-Guard
 * Description: AI-powered security guard for WordPress.
 * Version: 0.1.0
 * Author: Security Expert
 * Text Domain: wp-ai-guard
 *
 * @package WP_AI_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'WP_AI_GUARD_VERSION', '0.1.0' );
define( 'WP_AI_GUARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_AI_LEARNING_MODE', true ); // Learning mode: log but don't block.

// Include required files.
require_once WP_AI_GUARD_PATH . 'includes/class-wp-ai-guard-db.php';
require_once WP_AI_GUARD_PATH . 'includes/class-wp-ai-guard-admin.php';
require_once WP_AI_GUARD_PATH . 'includes/class-wp-ai-guard-monitor.php';
require_once WP_AI_GUARD_PATH . 'includes/class-wp-ai-guard-ai.php';
require_once WP_AI_GUARD_PATH . 'includes/class-wp-ai-guard-ollama.php';

/**
 * Main plugin class.
 */
class WP_AI_Guard {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register activation hook.
		register_activation_hook( __FILE__, array( 'WP_AI_Guard_DB', 'create_table' ) );

		// Initialize monitoring on every request.
		$monitor = new WP_AI_Guard_Monitor();
		$monitor->init();

		// Initialize admin if in admin area.
		if ( is_admin() ) {
			$admin = new WP_AI_Guard_Admin();
			$admin->init();
		}
	}
}

// Initialize the plugin.
new WP_AI_Guard();
