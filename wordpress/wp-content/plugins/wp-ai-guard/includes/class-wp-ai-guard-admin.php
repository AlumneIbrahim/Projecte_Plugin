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
		add_action( 'admin_post_wpguard_run_ai', array( $this, 'handle_manual_ai_analysis' ) );
	}

	/**
	 * Handle the manual AI analysis trigger.
	 */
	public function handle_manual_ai_analysis() {
		check_admin_referer( 'wpguard_run_ai_action' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		global $wpdb;
		$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpguard_logs WHERE threat_score < 100 ORDER BY created_at DESC LIMIT 5" );

		if ( ! empty( $logs ) ) {
			$ai = new WP_AI_Guard_AI();
			foreach ( $logs as $log ) {
				$ai->analyze_log( $log );
			}
		}

		wp_redirect( admin_url( 'admin.php?page=wp-ai-guard-status&analyzed=1' ) );
		exit;
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
		global $wpdb;
		$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpguard_logs ORDER BY created_at DESC LIMIT 20" );
		?>
		<div class="wrap">
			<h1><?php _e( 'WP-AI-Guard Status', 'wp-ai-guard' ); ?></h1>
			
			<?php if ( isset( $_GET['analyzed'] ) ) : ?>
				<div class="updated"><p><?php _e( 'AI Analysis completed for recent logs.', 'wp-ai-guard' ); ?></p></div>
			<?php endif; ?>

			<div class="card">
				<h2><?php _e( 'Actions', 'wp-ai-guard' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpguard_run_ai">
					<?php wp_nonce_field( 'wpguard_run_ai_action' ); ?>
					<?php submit_button( __( 'Executar Anàlisi d\'IA', 'wp-ai-guard' ), 'primary', 'submit', false ); ?>
					<p class="description"><?php _e( 'Envia els darrers logs a Gemini per a una anàlisi detallada.', 'wp-ai-guard' ); ?></p>
				</form>
			</div>

			<table class="widefat fixed striped" style="margin-top: 20px;">
				<thead>
					<tr>
						<th><?php _e( 'Data', 'wp-ai-guard' ); ?></th>
						<th><?php _e( 'IP', 'wp-ai-guard' ); ?></th>
						<th><?php _e( 'Threat Level', 'wp-ai-guard' ); ?></th>
						<th><?php _e( 'AI Analysis', 'wp-ai-guard' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $logs ) : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log->created_at ); ?></td>
								<td><?php echo esc_html( $log->ip ); ?></td>
								<td>
									<span class="badge" style="background: <?php echo $log->threat_score > 5 ? '#d63638' : '#00a32a'; ?>; color: #fff; padding: 2px 8px; border-radius: 4px;">
										<?php echo esc_html( $log->threat_score ); ?>/10
									</span>
								</td>
								<td><?php echo esc_html( $log->ai_analysis ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="4"><?php _e( 'No logs found yet.', 'wp-ai-guard' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
