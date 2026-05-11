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
		add_action( 'wp_ajax_wpguard_analyze_single_log', array( $this, 'ajax_analyze_single_log' ) );
		add_action( 'wp_ajax_wpguard_get_latest_logs', array( $this, 'ajax_get_latest_logs' ) );
	}

	/**
	 * AJAX handler to fetch latest logs.
	 */
	public function ajax_get_latest_logs() {
		check_ajax_referer( 'wpguard_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpguard_logs';
		$logs = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 20" );

		foreach ( $logs as &$log ) {
			$request_info = json_decode( $log->request_data, true );
			$log->url = isset($request_info['url']) ? $request_info['url'] : 'N/A';
			
			$badge_class = 'wpguard-badge-low';
			if ( $log->threat_score > 7 ) $badge_class = 'wpguard-badge-high';
			elseif ( $log->threat_score > 3 ) $badge_class = 'wpguard-badge-medium';
			
			$log->badge_html = sprintf( '<span class="wpguard-badge %s">%s/10</span>', $badge_class, $log->threat_score );
			$log->ai_display = $log->ai_analysis ? esc_html( $log->ai_analysis ) : '<span style="color: #646970; font-style: italic;">' . __( 'Pendent...', 'wp-ai-guard' ) . '</span>';
		}

		wp_send_json_success( $logs );
	}

	/**
	 * AJAX handler to analyze a single log and return progress.
	 */
	public function ajax_analyze_single_log() {
		check_ajax_referer( 'wpguard_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$log_id = intval( $_POST['log_id'] );
		global $wpdb;
		$log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpguard_logs WHERE id = %d", $log_id ) );

		if ( ! $log ) {
			wp_send_json_error( 'Log not found' );
		}

		$engine = get_option( 'wp_ai_guard_engine', 'gemini' );
		$ai = ( $engine === 'ollama' ) ? new WP_AI_Guard_Ollama() : new WP_AI_Guard_AI();

		$result = $ai->analyze_log( $log );

		if ( $result ) {
			// Extract some info for the console
			$request_info = json_decode( $log->request_data, true );
			$url = isset($request_info['url']) ? $request_info['url'] : 'N/A';

			wp_send_json_success( array(
				'message' => sprintf( "[%s] %s", $result['type'], $result['explanation'] ),
				'score'   => $result['threat_level'],
				'url'     => $url
			) );
		} else {
			wp_send_json_error( 'AI Analysis failed' );
		}
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
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<h2><?php _e( 'Monitor d\'Intel·ligència en Temps Real', 'wp-ai-guard' ); ?></h2>
							<div id="wpguard-live-indicator" style="color: #d63638; font-weight: bold; display: flex; align-items: center; gap: 5px;">
								<span class="dashicons dashicons-marker" style="animation: blinker 1s linear infinite;"></span>
								LIVE
							</div>
						</div>
						<p><?php _e( 'Aquest panell s\'actualitza automàticament cada 5 segons. Qualsevol atac detectat serà analitzat per la IA immediatament.', 'wp-ai-guard' ); ?></p>
						
						<style>
							@keyframes blinker { 50% { opacity: 0; } }
							.wpguard-analyzing { background: #fff8e1 !important; transition: background 0.5s; }
						</style>

						<div id="wpguard-progress-container" style="display:none; margin-bottom: 20px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
							<div style="font-weight:bold; margin-bottom:10px;"><?php _e( 'Anàlisi en curs...', 'wp-ai-guard' ); ?></div>
							<div id="wpguard-console" style="margin-top:5px; font-family:monospace; font-size:11px; max-height:100px; overflow-y:auto; background:#000; color:#0f0; padding:10px; border-radius:4px;">
								> <?php _e( 'Sistema de monitorització actiu.', 'wp-ai-guard' ); ?>
							</div>
						</div>
					</div>

					<h2 style="margin-top: 30px;"><?php _e( 'Seguretat en Temps Real', 'wp-ai-guard' ); ?></h2>
					<table class="widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 150px;"><?php _e( 'Data i Hora', 'wp-ai-guard' ); ?></th>
								<th style="width: 120px;"><?php _e( 'Adreça IP', 'wp-ai-guard' ); ?></th>
								<th style="width: 100px;"><?php _e( 'Nivell de Risc', 'wp-ai-guard' ); ?></th>
								<th><?php _e( 'Objectiu (URL)', 'wp-ai-guard' ); ?></th>
								<th><?php _e( 'Anàlisi Detallat de la IA', 'wp-ai-guard' ); ?></th>
							</tr>
						</thead>
						<tbody id="wpguard-log-body">
							<tr><td colspan="5"><?php _e( 'Carregant logs...', 'wp-ai-guard' ); ?></td></tr>
						</tbody>
					</table>

					<script>
					jQuery(document).ready(function($) {
						const $logBody = $('#wpguard-log-body');
						const $console = $('#wpguard-console');
						const $progressContainer = $('#wpguard-progress-container');
						let isAnalyzing = false;
						let analyzedIds = new Set();

						function updateLogs() {
							$.post(ajaxurl, {
								action: 'wpguard_get_latest_logs',
								nonce: '<?php echo wp_create_nonce("wpguard_ai_nonce"); ?>'
							}, function(response) {
								if (response.success) {
									renderLogs(response.data);
									processPendingAnalysis(response.data);
								}
							});
						}

						function renderLogs(logs) {
							let html = '';
							logs.forEach(log => {
								html += `<tr id="log-${log.id}" class="${(!log.ai_analysis) ? 'wpguard-analyzing' : ''}">
									<td>${log.created_at}</td>
									<td><strong>${log.ip}</strong></td>
									<td>${log.badge_html}</td>
									<td style="font-size:11px; font-family:monospace;">${log.url}</td>
									<td class="ai-cell">${log.ai_display}</td>
								</tr>`;
							});
							$logBody.html(html);
						}

						function processPendingAnalysis(logs) {
							if (isAnalyzing) return;

							const pending = logs.find(log => !log.ai_analysis && !analyzedIds.has(log.id));
							if (pending) {
								analyzeLog(pending.id, pending.url);
							}
						}

						function analyzeLog(id, url) {
							isAnalyzing = true;
							analyzedIds.add(id);
							$progressContainer.show();
							$console.append('<br>> **Analitzant atac a:** ' + url);
							$console.scrollTop($console[0].scrollHeight);

							$.post(ajaxurl, {
								action: 'wpguard_analyze_single_log',
								log_id: id,
								nonce: '<?php echo wp_create_nonce("wpguard_ai_nonce"); ?>'
							}, function(response) {
								isAnalyzing = false;
								if (response.success) {
									$console.append('<br>  [OK] Risc: ' + response.data.score + '/10 - ' + response.data.message);
								} else {
									$console.append('<br>  [FAIL] Error en Log ' + id);
									analyzedIds.delete(id); // Retry next time
								}
								$console.scrollTop($console[0].scrollHeight);
								updateLogs(); // Refresh immediately after analysis
							});
						}

						// Start polling
						updateLogs();
						setInterval(updateLogs, 5000);
					});
					</script>
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
									<p class="description">
										<strong><?php _e( 'Modo Online (Gemini):', 'wp-ai-guard' ); ?></strong> <?php _e( 'Màxima precisió, requereix internet i API Key.', 'wp-ai-guard' ); ?><br>
										<strong><?php _e( 'Modo Local (Ollama):', 'wp-ai-guard' ); ?></strong> <?php _e( 'Privadesa total, gratuït, funciona sense internet directament en el teu servidor.', 'wp-ai-guard' ); ?>
									</p>
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
