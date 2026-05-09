<?php
/**
 * Admin handler for WP-AI-Guard.
 *
 * @package WP_AI_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_AI_Guard_Admin
 */
class WP_AI_Guard_Admin {

	/**
	 * Initialize the admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add the admin menu item.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'WP-AI-Guard Status', 'wp-ai-guard' ),
			'WP-AI-Guard',
			'manage_options',
			'wp-ai-guard-status',
			array( $this, 'render_status_page' ),
			'dashicons-shield-alt'
		);
	}

	/**
	 * Render the status page.
	 */
	public function render_status_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'WP-AI-Guard Status', 'wp-ai-guard' ); ?></h1>
			<p><?php _e( 'Welcome to the WP-AI-Guard security dashboard.', 'wp-ai-guard' ); ?></p>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php _e( 'Recent Logs', 'wp-ai-guard' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php _e( 'No logs found yet.', 'wp-ai-guard' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
