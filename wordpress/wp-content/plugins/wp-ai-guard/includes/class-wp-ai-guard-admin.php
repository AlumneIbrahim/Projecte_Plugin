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
		$last_id = isset( $_POST['last_id'] ) ? intval( $_POST['last_id'] ) : 0;

		// If it's the first load, get the last 20. If not, get only new ones.
		if ( $last_id === 0 ) {
			$logs = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 20" );
		} else {
			$logs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE id > %d ORDER BY created_at ASC", $last_id ) );
		}

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
		$table_name = $wpdb->prefix . 'wpguard_logs';

		// Lock row for processing
		$updated = $wpdb->update(
			$table_name,
			array( 'status' => 'processing' ),
			array( 'id' => $log_id, 'status' => 'pending' )
		);

		if ( ! $updated ) {
			wp_send_json_error( 'Log already being processed or completed' );
		}

		$log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $log_id ) );

		if ( ! $log ) {
			wp_send_json_error( 'Log not found' );
		}

		$engine = get_option( 'wp_ai_guard_engine', 'gemini' );
		$ai = ( $engine === 'ollama' ) ? new WP_AI_Guard_Ollama() : new WP_AI_Guard_AI();

		$result = $ai->analyze_log( $log );

		if ( $result ) {
			$wpdb->update( $table_name, array( 'status' => 'completed' ), array( 'id' => $log_id ) );
			
			$request_info = json_decode( $log->request_data, true );
			$url = isset($request_info['url']) ? $request_info['url'] : 'N/A';

			wp_send_json_success( array(
				'message' => sprintf( "[%s] %s", $result['type'], $result['explanation'] ),
				'score'   => $result['threat_level'],
				'url'     => $url
			) );
		} else {
			// Back to pending for retry
			$wpdb->update( $table_name, array( 'status' => 'pending' ), array( 'id' => $log_id ) );
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
			update_option( 'wpguard_learning_mode', isset( $_POST['learning_mode'] ) ? '1' : '0' );
			update_option( 'wpguard_log_retention', intval( $_POST['log_retention'] ) );
			update_option( 'wpguard_notification_email', sanitize_email( $_POST['notification_email'] ) );
			update_option( 'wp_ai_guard_api_key', sanitize_text_field( $_POST['gemini_api_key'] ) );
			update_option( 'wp_ai_guard_engine', sanitize_text_field( $_POST['ai_engine'] ) );
			update_option( 'wp_ai_guard_ollama_model', sanitize_text_field( $_POST['ollama_model'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Configuració guardada correctament.', 'wp-ai-guard' ) . '</p></div>';
		}

		$notifications_enabled = get_option( 'wpguard_notifications_enabled', '1' );
		$learning_mode         = get_option( 'wpguard_learning_mode', '1' );
		$log_retention         = get_option( 'wpguard_log_retention', '30' );
		$notification_email    = get_option( 'wpguard_notification_email', get_option( 'admin_email' ) );
		$api_key               = get_option( 'wp_ai_guard_api_key', '' );
		$ai_engine             = get_option( 'wp_ai_guard_engine', 'gemini' );
		$ollama_model          = get_option( 'wp_ai_guard_ollama_model', 'llama3' );
		?>
		<style>
			:root {
				--wpguard-primary: #2271b1;
				--wpguard-danger: #d63638;
				--wpguard-warning: #9a5b13;
				--wpguard-success: #00a32a;
				--wpguard-bg-soft: #f0f6fb;
				--wpguard-card-shadow: 0 4px 12px rgba(0,0,0,0.08);
			}
			.wpguard-header { background: #fff; padding: 24px; border-bottom: 1px solid #dcdcde; margin: -20px -20px 20px -20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
			.wpguard-header h1 { margin: 0; display: flex; align-items: center; gap: 12px; font-weight: 700; color: #1d2327; }
			.wpguard-header h1 span { color: var(--wpguard-primary); }
			.wpguard-version { background: var(--wpguard-bg-soft); padding: 4px 10px; border-radius: 6px; font-size: 12px; color: var(--wpguard-primary); font-weight: 600; }
			
			.wpguard-nav-tab-wrapper { margin-bottom: 24px; border-bottom: 2px solid #dcdcde; }
			.nav-tab-active { border-bottom: 2px solid var(--wpguard-primary) !important; background: transparent !important; color: var(--wpguard-primary) !important; }
			
			.wpguard-stats { display: flex; gap: 20px; margin-bottom: 24px; }
			.wpguard-stat-card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: var(--wpguard-card-shadow); flex: 1; border: 1px solid #e0e0e0; transition: transform 0.2s; }
			.wpguard-stat-card:hover { transform: translateY(-2px); }
			.wpguard-stat-card h3 { margin: 0 0 8px 0; font-size: 13px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px; }
			.wpguard-stat-card .value { font-size: 32px; font-weight: 800; color: #1d2327; }
			.wpguard-stat-card.blocked { border-top: 4px solid var(--wpguard-danger); }
			.wpguard-stat-card.threat { border-top: 4px solid var(--wpguard-warning); }
			.wpguard-stat-card.total { border-top: 4px solid var(--wpguard-primary); }

			.wpguard-live-card { background: #1e1e1e; color: #fff; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border: 1px solid #333; }
			.wpguard-live-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #333; padding-bottom: 12px; }
			.wpguard-live-title { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 16px; color: #e0e0e0; }
			
			#wpguard-console { 
				font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
				font-size: 13px; 
				line-height: 1.6;
				height: 180px; 
				overflow-y: auto; 
				background: rgba(0,0,0,0.3); 
				color: #a9b7c6; 
				padding: 16px; 
				border-radius: 8px;
				border: 1px solid #444;
			}
			.console-line { margin-bottom: 4px; border-left: 2px solid transparent; padding-left: 8px; }
			.console-info { color: #569cd6; }
			.console-success { color: #6a9955; border-left-color: #6a9955; }
			.console-warning { color: #ce9178; border-left-color: #ce9178; }
			.console-accent { color: #b5cea8; }

			.wpguard-table-card { background: #fff; border-radius: 12px; box-shadow: var(--wpguard-card-shadow); border: 1px solid #e0e0e0; overflow: hidden; }
			.wpguard-table-card h2 { padding: 20px 24px; margin: 0; background: #fafafa; border-bottom: 1px solid #e0e0e0; font-size: 16px; }
			
			.widefat { border: none !important; box-shadow: none !important; }
			.widefat thead tr th { background: #fafafa !important; padding: 16px 24px !important; color: #646970 !important; font-weight: 600 !important; border-bottom: 2px solid #f0f0f1 !important; }
			.widefat tbody tr td { padding: 16px 24px !important; vertical-align: middle !important; border-bottom: 1px solid #f0f0f1 !important; }
			
			.wpguard-badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
			.wpguard-badge-high { background: #fff1f0; color: #cf1322; border: 1px solid #ffa39e; }
			.wpguard-badge-medium { background: #fff7e6; color: #d46b08; border: 1px solid #ffd591; }
			.wpguard-badge-low { background: #f6ffed; color: #389e0d; border: 1px solid #b7eb8f; }
			
			.ai-analysis-text { line-height: 1.5; color: #2c3338; font-size: 13px; }
			.ai-type-tag { font-weight: 700; color: var(--wpguard-primary); margin-right: 6px; }
			
			@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
			.live-dot { height: 8px; width: 8px; background-color: #ff4d4f; border-radius: 50%; display: inline-block; animation: pulse 1.5s infinite; }
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
				<a href="?page=wp-ai-guard-status&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Tauler de Control', 'wp-ai-guard' ); ?></a>
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
						<div class="wpguard-stat-card total">
							<h3><?php _e( 'Esdeveniments totals', 'wp-ai-guard' ); ?></h3>
							<div class="value"><?php echo number_format( $total_logs ); ?></div>
						</div>
						<div class="wpguard-stat-card blocked">
							<h3><?php _e( 'IPs Bloquejades', 'wp-ai-guard' ); ?></h3>
							<div class="value"><?php echo number_format( $total_blocked ); ?></div>
						</div>
						<div class="wpguard-stat-card threat">
							<h3><?php _e( 'Risc Mitjà', 'wp-ai-guard' ); ?></h3>
							<div class="value"><?php echo round( $avg_threat, 1 ); ?><small style="font-size: 14px; color: #646970;">/10</small></div>
						</div>
					</div>

					<div class="wpguard-live-card">
						<div class="wpguard-live-header">
							<div class="wpguard-live-title">
								<span class="dashicons dashicons-terminal"></span>
								<?php _e( 'Monitor d\'Intel·ligència en Temps Real', 'wp-ai-guard' ); ?>
							</div>
							<div style="display: flex; align-items: center; gap: 12px;">
								<div id="wpguard-live-indicator" style="font-size: 11px; color: #ff4d4f; font-weight: 700; letter-spacing: 1px; display: flex; align-items: center; gap: 6px;">
									<span class="live-dot"></span>
									FEED EN VIU
								</div>
							</div>
						</div>
						
						<div id="wpguard-console">
							<div class="console-line console-info">> <?php _e( 'Sistema de monitorització actiu. Esperant activitat...', 'wp-ai-guard' ); ?></div>
						</div>
					</div>

					<div class="wpguard-table-card">
						<h2><?php _e( 'Activitat Recuenta de Seguretat', 'wp-ai-guard' ); ?></h2>
						<table class="widefat fixed striped">
							<thead>
								<tr>
									<th style="width: 160px;"><?php _e( 'Data i Hora', 'wp-ai-guard' ); ?></th>
									<th style="width: 140px;"><?php _e( 'Adreça IP', 'wp-ai-guard' ); ?></th>
									<th style="width: 120px;"><?php _e( 'Nivell de Risc', 'wp-ai-guard' ); ?></th>
									<th><?php _e( 'Objectiu (URL)', 'wp-ai-guard' ); ?></th>
									<th style="width: 40%;"><?php _e( 'Anàlisi Detallat de la IA', 'wp-ai-guard' ); ?></th>
								</tr>
							</thead>
							<tbody id="wpguard-log-body">
								<tr><td colspan="5" style="text-align: center; padding: 40px !important; color: #646970;"><?php _e( 'Sincronitzant amb el servidor...', 'wp-ai-guard' ); ?></td></tr>
							</tbody>
						</table>
					</div>

					<script>
					jQuery(document).ready(function($) {
						const $logBody = $('#wpguard-log-body');
						const $console = $('#wpguard-console');
						let isAnalyzing = false;
						let lastId = 0;
						let pendingQueue = [];

						function addConsoleLine(text, type = 'info') {
							const timestamp = new Date().toLocaleTimeString();
							const line = `<div class="console-line console-${type}">
								<span class="console-accent">[${timestamp}]</span> ${text}
							</div>`;
							$console.append(line);
							$console.scrollTop($console[0].scrollHeight);
						}

						function updateLogs() {
							$.post(ajaxurl, {
								action: 'wpguard_get_latest_logs',
								last_id: lastId,
								nonce: '<?php echo wp_create_nonce("wpguard_ai_nonce"); ?>'
							}, function(response) {
								if (response.success && response.data.length > 0) {
									renderLogs(response.data);
								}
							});
						}

						function renderLogs(logs) {
							logs.forEach(log => {
								if (log.id > lastId) lastId = log.id;
								
								const existingRow = $(`#log-${log.id}`);
								
								// Format AI analysis
								let aiContent = log.ai_display;
								if (log.ai_analysis) {
									const parts = log.ai_analysis.match(/\[(.*?)\] (.*)/);
									if (parts) {
										aiContent = `<span class="ai-analysis-text"><span class="ai-type-tag">${parts[1]}</span>${parts[2]}</span>`;
									}
								}

								const rowHtml = `<tr id="log-${log.id}" style="${(log.status !== 'completed') ? 'background: #fffbe6;' : ''}">
									<td style="color: #646970; font-size: 12px;">${log.created_at}</td>
									<td><strong>${log.ip}</strong></td>
									<td>${log.badge_html}</td>
									<td style="font-size:11px; font-family:monospace; color: #2271b1;">${log.url}</td>
									<td class="ai-cell">${aiContent}</td>
								</tr>`;

								if (existingRow.length) {
									existingRow.replaceWith(rowHtml);
								} else {
									$logBody.prepend(rowHtml);
									if (lastId !== 0 && log.status === 'pending') {
										addConsoleLine(`Atac detectat des de ${log.ip} a ${log.url}`, 'warning');
									}
								}

								if (log.status === 'pending') {
									if (!pendingQueue.includes(log.id)) {
										pendingQueue.push(log.id);
										processQueue();
									}
								}
							});
							
							$logBody.find('tr:contains("Sincronitzant")').remove();
						}

						function processQueue() {
							if (isAnalyzing || pendingQueue.length === 0) return;

							const logId = pendingQueue.shift();
							const $row = $(`#log-${logId}`);
							const url = $row.find('td:eq(3)').text();

							analyzeLog(logId, url);
						}

						function analyzeLog(id, url) {
							isAnalyzing = true;
							addConsoleLine(`Iniciant anàlisi neuronal de la petició...`, 'info');

							$.post(ajaxurl, {
								action: 'wpguard_analyze_single_log',
								log_id: id,
								nonce: '<?php echo wp_create_nonce("wpguard_ai_nonce"); ?>'
							}, function(response) {
								isAnalyzing = false;
								if (response.success) {
									addConsoleLine(`ANÀLISI COMPLETAT: [${response.data.score}/10] ${response.data.message}`, 'success');
								} else {
									addConsoleLine(`ERROR EN L'ANÀLISI: ${response.data || 'Error desconegut'}`, 'warning');
								}
								
								updateLogs();
								processQueue();
							});
						}

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
								<th scope="row"><?php _e( 'Modo de Seguretat', 'wp-ai-guard' ); ?></th>
								<td>
									<label for="learning_mode">
										<input name="learning_mode" type="checkbox" id="learning_mode" value="1" <?php checked( $learning_mode, '1' ); ?>>
										<?php _e( 'Activa el "Modo Aprenentatge" (no bloqueja res, només registra).', 'wp-ai-guard' ); ?>
									</label>
									<p class="description"><?php _e( 'Desactiva aquesta opció per començar a bloquejar IPs malicioses automàticament.', 'wp-ai-guard' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php _e( 'Retenció de Logs', 'wp-ai-guard' ); ?></th>
								<td>
									<input name="log_retention" type="number" id="log_retention" value="<?php echo esc_attr( $log_retention ); ?>" class="small-text"> dies
									<p class="description"><?php _e( 'Els logs més antics s\'esborraran automàticament.', 'wp-ai-guard' ); ?></p>
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
