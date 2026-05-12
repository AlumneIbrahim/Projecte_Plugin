<?php
/**
 * Admin page for WP-AI-Guard.
 *
 * @package WP_AI_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Render the WP-AI-Guard admin page.
 */
function wpguard_render_admin_page() {
	// Fetch the log count using the database class.
	$log_count = WP_AI_Guard_Database::get_log_count();
	?>
	<div class="wrap">
		<h1>WP-AI-Guard</h1>
		<div class="notice notice-success">
			<p><?php esc_html_e( 'Plugin actiu correctament', 'wp-ai-guard' ); ?></p>
		</div>
		<div class="card">
			<h2><?php esc_html_e( 'Status Overview', 'wp-ai-guard' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Total logs registered:', 'wp-ai-guard' ); ?></strong>
				<?php echo esc_html( $log_count ); ?>
			</p>
		</div>
	</div>
	<?php
}
