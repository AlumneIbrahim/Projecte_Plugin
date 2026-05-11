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
			$engine = get_option( 'wp_ai_guard_engine', 'gemini' );
			$ai = ( $engine === 'ollama' ) ? new WP_AI_Guard_Ollama() : new WP_AI_Guard_AI();
			
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
		$table_name = $wpdb->prefix . 'wpguard_logs';
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'dashboard';
		
		// Handle Settings Save
		if ( isset( $_POST['wpguard_save_settings'] ) ) {
			check_admin_referer( 'wpguard_save_settings_action' );
			update_option( 'wpguard_notifications_enabled', isset( $_POST['notifications_enabled'] ) ? '1' : '0' );
			update_option( 'wpguard_notification_email', sanitize_email( $_POST['notification_email'] ) );
			update_option( 'wp_ai_guard_api_key', sanitize_text_field( $_POST['gemini_api_key'] ) );
			update_option( 'wp_ai_guard_engine', sanitize_text_field( $_POST['ai_engine'] ) );
			update_option( 'wp_ai_guard_ollama_model', sanitize_text_field( $_POST['ollama_model'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Configuració guardada correctament.', 'wp-ai-guard' ) . '</p></div>';
		}

		$notifications_enabled = get_option( 'wpguard_notifications_enabled', '1' );
		$notification_email    = get_option( 'wpguard_notification_email', get_option( 'admin_email' ) );
		$api_key               = get_option( 'wp_ai_guard_api_key', '' );
		$ai_engine             = get_option( 'wp_ai_guard_engine', 'gemini' );
		$ollama_model          = get_option( 'wp_ai_guard_ollama_model', 'llama3' );
		?>
		<style>
			.wpguard-header { background: #fff; padding: 20px; border-bottom: 1px solid #c3c4c7; margin: -20px -20px 20px -20px; display: flex; align-items: center; justify-content: space-between; }
			.wpguard-header h1 { margin: 0; display: flex; align-items: center; gap: 10px; }
			.wpguard-nav-tab-wrapper { margin-bottom: 20px; border-bottom: 1px solid #c3c4c7; }
			.wpguard-dashboard { margin-top: 20px; }
			.wpguard-stats { display: flex; gap: 20px; margin-bottom: 30px; }
			.wpguard-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); flex: 1; border-left: 4px solid #2271b1; }
			.wpguard-stat-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #646970; }
			.wpguard-stat-card .value { font-size: 24px; font-weight: bold; color: #1d2327; }
			.wpguard-stat-card.blocked { border-left-color: #d63638; }
			.wpguard-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
			.wpguard-badge-high { background: #fbe9eb; color: #d63638; }
			.wpguard-badge-medium { background: #fcf0e1; color: #9a5b13; }
			.wpguard-badge-low { background: #e7f6ed; color: #00a32a; }
			.wpguard-log-details { font-size: 12px; color: #646970; max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: help; }
			.wpguard-log-details:hover { white-space: normal; word-break: break-all; }
		</style>

		<div class="wrap">
			<div class="wpguard-header">
				<h1>
					<span class="dashicons dashicons-shield-alt" style="font-size: 30px; width: 30px; height: 30px;"></span>
					WP-AI-Guard
				</h1>
				<span class="wpguard-version">v<?php echo WP_AI_GUARD_VERSION; ?></span>
			</div>

			<h2 class="nav-tab-wrapper wpguard-nav-tab-wrapper">
				<a href="?page=wp-ai-guard-status&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Panel de Control', 'wp-ai-guard' ); ?></a>
				<a href="?page=wp-ai-guard-status&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Configuració', 'wp-ai-guard' ); ?></a>
			</h2>

			<?php if ( 'dashboard' === $active_tab ) : ?>
				<?php
				// Stats for Dashboard
				$total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
				$total_blocked = $wpdb->get_var( "SELECT COUNT(DISTINCT ip) FROM $table_name WHERE threat_score > 7" );
				$avg_threat = $wpdb->get_var( "SELECT AVG(threat_score) FROM $table_name" );
				$logs = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 50" );
				?>
				<div class="wpguard-dashboard">
					<div class="wpguard-stats">
						<div class="wpguard-stat-card">
							<h3><?php _e( 'Total Security Events', 'wp-ai-guard' ); ?></h3>
							<div class="value"><?php echo number_format( $total_logs ); ?></div>
						</div>
						<div class="wpguard-stat-card blocked">
							<h3><?php _e( 'Blocked IPs', 'wp-ai-guard' ); ?></h3>
							<div class="value"><?php echo number_format( $total_blocked ); ?></div>
						</div>
						<div class="wpguard-stat-card">
							<h3><?php _e( 'Avg. Threat Score', 'wp-ai-guard' ); ?></h3>
							<div class="value"><?php echo round( $avg_threat, 1 ); ?>/10</div>
						</div>
					</div>

					<div class="card" style="max-width: 100%; margin-bottom: 20px;">
						<h2><?php _e( 'AI Intelligence', 'wp-ai-guard' ); ?></h2>
						<p><?php _e( 'Trigger a deep analysis of recent suspicious activity using Google Gemini Pro.', 'wp-ai-guard' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="wpguard_run_ai">
							<?php wp_nonce_field( 'wpguard_run_ai_action' ); ?>
							<?php submit_button( __( 'Run AI Analysis Now', 'wp-ai-guard' ), 'primary' ); ?>
						</form>
					</div>

					<h2 style="margin-top: 30px;"><?php _e( 'Security Activity Logs', 'wp-ai-guard' ); ?></h2>
					<table class="widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 150px;"><?php _e( 'Date & Time', 'wp-ai-guard' ); ?></th>
								<th style="width: 120px;"><?php _e( 'IP Address', 'wp-ai-guard' ); ?></th>
								<th style="width: 100px;"><?php _e( 'Threat Level', 'wp-ai-guard' ); ?></th>
								<th><?php _e( 'Details', 'wp-ai-guard' ); ?></th>
								<th><?php _e( 'AI Analysis & Conclusion', 'wp-ai-guard' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( $logs ) : ?>
								<?php foreach ( $logs as $log ) : 
									$badge_class = 'wpguard-badge-low';
									if ( $log->threat_score > 7 ) $badge_class = 'wpguard-badge-high';
									elseif ( $log->threat_score > 3 ) $badge_class = 'wpguard-badge-medium';
									
									$request_info = json_decode( $log->request_data, true );
									$url = isset($request_info['url']) ? $request_info['url'] : 'N/A';
								?>
									<tr>
										<td><?php echo esc_html( $log->created_at ); ?></td>
										<td><strong><?php echo esc_html( $log->ip ); ?></strong></td>
										<td>
											<span class="wpguard-badge <?php echo $badge_class; ?>">
												<?php echo esc_html( $log->threat_score ); ?>/10
											</span>
										</td>
										<td>
											<div class="wpguard-log-details" title="<?php echo esc_attr( $log->request_data ); ?>">
												<code><?php echo esc_html( $url ); ?></code>
											</div>
										</td>
										<td>
											<?php if ( $log->ai_analysis ) : ?>
												<em><?php echo esc_html( $log->ai_analysis ); ?></em>
											<?php else : ?>
												<span style="color: #646970; font-style: italic;"><?php _e( 'Pending AI analysis...', 'wp-ai-guard' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr>
									<td colspan="5"><?php _e( 'No suspicious activity detected yet. Your site is safe!', 'wp-ai-guard' ); ?></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

			<?php else : ?>
				<div class="card" style="max-width: 100%;">
					<h2><?php _e( 'Configuració del Sistema', 'wp-ai-guard' ); ?></h2>
					<form method="post" action="">
						<?php wp_nonce_field( 'wpguard_save_settings_action' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row"><?php _e( 'AI Engine', 'wp-ai-guard' ); ?></th>
								<td>
									<select name="ai_engine" id="ai_engine">
										<option value="gemini" <?php selected( $ai_engine, 'gemini' ); ?>>Google Gemini (Online/API)</option>
										<option value="ollama" <?php selected( $ai_engine, 'ollama' ); ?>>Ollama (Local AI - Free)</option>
									</select>
									<p class="description"><?php _e( 'Selecciona quina IA vols utilitzar per analitzar els logs.', 'wp-ai-guard' ); ?></p>
								</td>
							</tr>
							<tr class="gemini-settings" <?php echo $ai_engine !== 'gemini' ? 'style="display:none;"' : ''; ?>>
								<th scope="row"><?php _e( 'Gemini API Key', 'wp-ai-guard' ); ?></th>
								<td>
									<input name="gemini_api_key" type="password" id="gemini_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
									<p class="description"><?php _e( 'Clau de l\'API per a l\'anàlisi amb Google Gemini.', 'wp-ai-guard' ); ?></p>
								</td>
							</tr>
							<tr class="ollama-settings" <?php echo $ai_engine !== 'ollama' ? 'style="display:none;"' : ''; ?>>
								<th scope="row"><?php _e( 'Ollama Model', 'wp-ai-guard' ); ?></th>
								<td>
									<input name="ollama_model" type="text" id="ollama_model" value="<?php echo esc_attr( $ollama_model ); ?>" class="regular-text">
									<p class="description"><?php _e( 'Nom del model instal·lat a Ollama (ex: llama3, mistral).', 'wp-ai-guard' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php _e( 'Alertes per Email', 'wp-ai-guard' ); ?></th>
								<td>
									<label for="notifications_enabled">
										<input name="notifications_enabled" type="checkbox" id="notifications_enabled" value="1" <?php checked( $notifications_enabled, '1' ); ?>>
										<?php _e( 'Envia un correu quan una IP sigui bloquejada.', 'wp-ai-guard' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php _e( 'Email de Notificació', 'wp-ai-guard' ); ?></th>
								<td>
									<input name="notification_email" type="email" id="notification_email" value="<?php echo esc_attr( $notification_email ); ?>" class="regular-text">
									<p class="description"><?php _e( 'Direcció on s\'enviaran les alertes de seguretat.', 'wp-ai-guard' ); ?></p>
								</td>
							</tr>
						</table>
						<script>
							document.getElementById('ai_engine').addEventListener('change', function() {
								var engine = this.value;
								document.querySelector('.gemini-settings').style.display = engine === 'gemini' ? '' : 'none';
								document.querySelector('.ollama-settings').style.display = engine === 'ollama' ? '' : 'none';
							});
						</script>
						<?php submit_button( __( 'Guardar Configuració', 'wp-ai-guard' ), 'primary', 'wpguard_save_settings' ); ?>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
