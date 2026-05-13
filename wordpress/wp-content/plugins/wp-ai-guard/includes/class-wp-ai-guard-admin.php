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
				--wpguard-primary: #3b82f6;
				--wpguard-danger: #ef4444;
				--wpguard-warning: #f59e0b;
				--wpguard-success: #10b981;
				--wpguard-glass-bg: rgba(255, 255, 255, 0.7);
				--wpguard-glass-border: rgba(255, 255, 255, 0.4);
				--wpguard-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
			}

			.wpguard-admin-wrap {
				font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				padding: 20px;
				background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
				min-height: calc(100vh - 100px);
				border-radius: 12px;
				margin-right: 20px;
				margin-top: 20px;
			}

			.wpguard-header { 
				background: var(--wpguard-glass-bg); 
				backdrop-filter: blur(12px);
				-webkit-backdrop-filter: blur(12px);
				padding: 30px; 
				border: 1px solid var(--wpguard-glass-border);
				border-radius: 20px;
				margin-bottom: 25px; 
				display: flex; 
				align-items: center; 
				justify-content: space-between; 
				box-shadow: var(--wpguard-shadow);
			}
			.wpguard-header h1 { 
				margin: 0; 
				display: flex; 
				align-items: center; 
				gap: 15px; 
				font-weight: 800; 
				color: #1e293b; 
				font-size: 28px;
			}
			.wpguard-header h1 span.dashicons { 
				font-size: 36px; 
				width: 36px; 
				height: 36px; 
				color: var(--wpguard-primary);
				text-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
			}
			.wpguard-version { 
				background: rgba(59, 130, 246, 0.1); 
				padding: 6px 14px; 
				border-radius: 50px; 
				font-size: 13px; 
				color: var(--wpguard-primary); 
				font-weight: 700;
				border: 1px solid rgba(59, 130, 246, 0.2);
			}
			
			.wpguard-nav-tab-wrapper { 
				margin-bottom: 30px; 
				border: none;
				display: flex;
				gap: 10px;
				padding: 5px;
				background: rgba(0,0,0,0.05);
				border-radius: 12px;
				width: fit-content;
			}
			.wpguard-nav-tab-wrapper .nav-tab {
				margin: 0;
				border: none !important;
				background: transparent !important;
				padding: 10px 20px !important;
				border-radius: 8px !important;
				font-weight: 600;
				transition: all 0.3s ease;
				color: #64748b;
			}
			.wpguard-nav-tab-wrapper .nav-tab-active { 
				background: #fff !important; 
				color: var(--wpguard-primary) !important; 
				box-shadow: 0 4px 12px rgba(0,0,0,0.05);
			}
			
			.wpguard-stats { display: flex; gap: 20px; margin-bottom: 30px; }
			.wpguard-stat-card { 
				background: var(--wpguard-glass-bg); 
				backdrop-filter: blur(8px);
				padding: 25px; 
				border-radius: 20px; 
				box-shadow: var(--wpguard-shadow); 
				flex: 1; 
				border: 1px solid var(--wpguard-glass-border);
				transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
			}
			.wpguard-stat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 40px rgba(0,0,0,0.12); }
			.wpguard-stat-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
			.wpguard-stat-card .value { font-size: 36px; font-weight: 900; color: #0f172a; }
			
			.wpguard-live-card { 
				background: rgba(15, 23, 42, 0.9); 
				backdrop-filter: blur(12px);
				color: #fff; 
				border-radius: 20px; 
				padding: 25px; 
				margin-bottom: 30px; 
				box-shadow: 0 20px 50px rgba(0,0,0,0.2); 
				border: 1px solid rgba(255,255,255,0.1); 
			}
			.wpguard-live-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; }
			.wpguard-live-title { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 18px; color: #f8fafc; }
			
			#wpguard-console { 
				font-family: 'JetBrains Mono', 'Fira Code', monospace;
				font-size: 14px; 
				line-height: 1.7;
				height: 220px; 
				overflow-y: auto; 
				background: rgba(0,0,0,0.4); 
				color: #e2e8f0; 
				padding: 20px; 
				border-radius: 12px;
				border: 1px solid rgba(255,255,255,0.05);
			}
			.console-line { margin-bottom: 6px; padding-left: 10px; border-left: 3px solid transparent; }
			.console-info { color: #60a5fa; }
			.console-success { color: #4ade80; border-left-color: #4ade80; }
			.console-warning { color: #fbbf24; border-left-color: #fbbf24; }
			.console-accent { color: #94a3b8; font-weight: bold; }

			.wpguard-table-card { 
				background: var(--wpguard-glass-bg); 
				backdrop-filter: blur(8px);
				border-radius: 20px; 
				box-shadow: var(--wpguard-shadow); 
				border: 1px solid var(--wpguard-glass-border); 
				overflow: hidden; 
			}
			.wpguard-table-card h2 { padding: 25px 30px; margin: 0; background: rgba(255,255,255,0.3); border-bottom: 1px solid var(--wpguard-glass-border); font-size: 18px; font-weight: 800; color: #1e293b; }
			
			.widefat { background: transparent !important; border: none !important; }
			.widefat thead tr th { background: rgba(255,255,255,0.5) !important; padding: 18px 30px !important; color: #475569 !important; font-weight: 700 !important; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
			.widefat tbody tr td { padding: 18px 30px !important; vertical-align: middle !important; border-bottom: 1px solid rgba(0,0,0,0.05) !important; color: #334155; }
			.widefat tbody tr:hover { background: rgba(255,255,255,0.3) !important; }
			
			.wpguard-badge { padding: 8px 14px; border-radius: 50px; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; }
			.wpguard-badge-high { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
			.wpguard-badge-medium { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
			.wpguard-badge-low { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
			
			.url-cell { 
				font-size: 11px; 
				font-family: 'JetBrains Mono', monospace; 
				color: var(--wpguard-primary); 
				word-break: break-all; 
				max-width: 300px; 
				line-height: 1.4;
			}
			
			.ai-cell { line-height: 1.5; }
			
			/* Guide Styles */
			.wpguard-guide-container { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
			.wpguard-guide-main section { margin-bottom: 40px; }
			.wpguard-guide-main h3 { font-size: 22px; color: #1e293b; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
			.wpguard-guide-main h3 span { color: var(--wpguard-primary); }
			.wpguard-guide-main p { font-size: 16px; line-height: 1.6; color: #475569; }
			.wpguard-step { background: #fff; padding: 25px; border-radius: 15px; margin-bottom: 20px; border: 1px solid #e2e8f0; display: flex; gap: 20px; }
			.step-number { background: var(--wpguard-primary); color: #fff; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0; }
			.step-content h4 { margin: 0 0 10px 0; font-size: 18px; color: #1e293b; }
			
			.wpguard-sidebar-card { background: #fff; padding: 25px; border-radius: 20px; border: 1px solid #e2e8f0; margin-bottom: 25px; }
			.wpguard-sidebar-card h4 { margin: 0 0 15px 0; color: #1e293b; font-size: 16px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
			.wpguard-feature-list { list-style: none; padding: 0; margin: 0; }
			.wpguard-feature-list li { padding: 10px 0; display: flex; align-items: center; gap: 10px; color: #475569; border-bottom: 1px solid #f1f5f9; }
			.wpguard-feature-list li span { color: var(--wpguard-success); font-weight: bold; }

			code { background: #f1f5f9; padding: 3px 6px; border-radius: 4px; font-family: monospace; color: #e11d48; }

			@keyframes pulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.2); opacity: 0.5; } 100% { transform: scale(1); opacity: 1; } }
			.live-dot { height: 10px; width: 10px; background-color: #ef4444; border-radius: 50%; display: inline-block; animation: pulse 2s infinite; box-shadow: 0 0 10px #ef4444; }
		</style>

		<div class="wrap wpguard-admin-wrap">
			<div class="wpguard-header">
				<h1>
					<span class="dashicons dashicons-shield-alt"></span>
					WP-AI-Guard
				</h1>
				<span class="wpguard-version">v<?php echo WP_AI_GUARD_VERSION; ?></span>
			</div>

			<div class="wpguard-nav-tab-wrapper">
				<a href="?page=wp-ai-guard-status&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Tauler de Control', 'wp-ai-guard' ); ?></a>
				<a href="?page=wp-ai-guard-status&tab=guia" class="nav-tab <?php echo $active_tab == 'guia' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Guia d\'Inici', 'wp-ai-guard' ); ?></a>
				<a href="?page=wp-ai-guard-status&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Configuració', 'wp-ai-guard' ); ?></a>
			</div>

			<?php if ( 'dashboard' === $active_tab ) : ?>
				<?php
				// Stats for Dashboard
				$total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
				$total_blocked = $wpdb->get_var( "SELECT COUNT(DISTINCT ip) FROM $table_name WHERE threat_score > 7" );
				$avg_threat = $wpdb->get_var( "SELECT AVG(threat_score) FROM $table_name" );
				?>
				<div class="wpguard-dashboard">
					<div class="wpguard-stats">
						<div class="wpguard-stat-card">
							<h3><?php _e( 'Esdeveniments totals', 'wp-ai-guard' ); ?></h3>
							<div class="value"><?php echo number_format( $total_logs ); ?></div>
						</div>
						<div class="wpguard-stat-card" style="border-bottom: 4px solid var(--wpguard-danger);">
							<h3><?php _e( 'IPs Bloquejades', 'wp-ai-guard' ); ?></h3>
							<div class="value"><?php echo number_format( $total_blocked ); ?></div>
						</div>
						<div class="wpguard-stat-card" style="border-bottom: 4px solid var(--wpguard-warning);">
							<h3><?php _e( 'Risc Mitjà', 'wp-ai-guard' ); ?></h3>
							<div class="value"><?php echo round( $avg_threat, 1 ); ?><small style="font-size: 14px; color: #64748b;">/10</small></div>
						</div>
					</div>

					<div class="wpguard-live-card">
						<div class="wpguard-live-header">
							<div class="wpguard-live-title">
								<span class="dashicons dashicons-terminal"></span>
								<?php _e( 'Monitor d\'Intel·ligència en Temps Real', 'wp-ai-guard' ); ?>
							</div>
							<div style="display: flex; align-items: center; gap: 12px;">
								<div id="wpguard-live-indicator" style="font-size: 11px; color: #ef4444; font-weight: 800; letter-spacing: 2px; display: flex; align-items: center; gap: 8px;">
									<span class="live-dot"></span>
									LIVE FEED
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
									<th style="width: 130px;"><?php _e( 'Adreça IP', 'wp-ai-guard' ); ?></th>
									<th style="width: 110px;"><?php _e( 'Risc', 'wp-ai-guard' ); ?></th>
									<th style="width: 250px;"><?php _e( 'Objectiu (URL)', 'wp-ai-guard' ); ?></th>
									<th><?php _e( 'Anàlisi Detallat de la IA', 'wp-ai-guard' ); ?></th>
								</tr>
							</thead>
							<tbody id="wpguard-log-body">
								<tr><td colspan="5" style="text-align: center; padding: 60px !important; color: #64748b; font-style: italic;"><?php _e( 'Sincronitzant amb el servidor neural...', 'wp-ai-guard' ); ?></td></tr>
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

								const rowHtml = `<tr id="log-${log.id}" class="${(log.status !== 'completed') ? 'wpguard-pending-row' : ''}">
									<td style="color: #64748b; font-size: 12px; font-weight: 500;">${log.created_at}</td>
									<td><strong>${log.ip}</strong></td>
									<td>${log.badge_html}</td>
									<td class="url-cell">${log.url}</td>
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

			<?php elseif ( 'guia' === $active_tab ) : ?>
				<div class="wpguard-guide-container">
					<div class="wpguard-guide-main">
						<section>
							<h3><span class="dashicons dashicons-welcome-learn-more"></span> Benvingut a WP-AI-Guard</h3>
							<p>Aquest connector utilitza models d'Intel·ligència Artificial d'última generació per protegir el teu WordPress. A diferència dels tallafocs tradicionals basats en regles estàtiques, WP-AI-Guard "entén" el context de les peticions i pot detectar atacs complexos o variacions noves (Zero-Day).</p>
						</section>

						<section>
							<h3><span class="dashicons dashicons-hammer"></span> Passos per a la instal·lació i configuració</h3>
							
							<div class="wpguard-step">
								<div class="step-number">1</div>
								<div class="step-content">
									<h4>Tria el teu motor d'IA</h4>
									<p>Ves a la pestanya de <strong>Configuració</strong>. Tens dues opcions principals:</p>
									<ul>
										<li><strong>Google Gemini:</strong> El més potent i fàcil de configurar. Requereix una API Key gratuïta.</li>
										<li><strong>Ollama:</strong> Per a privadesa total. Executa la IA directament al teu servidor. Requereix tenir Ollama instal·lat al sistema.</li>
									</ul>
								</div>
							</div>

							<div class="wpguard-step">
								<div class="step-number">2</div>
								<div class="step-content">
									<h4>Configura l'API Key (Si uses Gemini)</h4>
									<p>Aconsegueix la teva clau a <a href="https://aistudio.google.com/" target="_blank">Google AI Studio</a> i enganxa-la al camp corresponent. També pots definir-la al teu <code>wp-config.php</code>:</p>
									<code>define( 'WP_AI_GUARD_API_KEY', 'la_teva_clau' );</code>
								</div>
							</div>

							<div class="wpguard-step">
								<div class="step-number">3</div>
								<div class="step-content">
									<h4>Modo Aprenentatge vs Bloqueig</h4>
									<p>Per defecte, el connector s'instal·la en <strong>Modo Aprenentatge</strong>. Això vol dir que registrarà els atacs però NO bloquejarà els usuaris. Recomanem mantenir-ho així uns dies per verificar que no hi hagi falsos positius.</p>
								</div>
							</div>

							<div class="wpguard-step">
								<div class="step-number">4</div>
								<div class="step-content">
									<h4>Monitoritza en temps real</h4>
									<p>Torna al <strong>Tauler de Control</strong>. Veuràs una consola tipus terminal. Quan algú intenti atacar la teva web, veuràs com la IA analitza la petició en temps real i dóna un veredicte.</p>
								</div>
							</div>
						</section>

						<section>
							<h3><span class="dashicons dashicons-lightbulb"></span> Tutorial d'ús</h3>
							<p>Un cop configurat, no cal que facis res més. WP-AI-Guard treballa en segon pla:</p>
							<ol>
								<li><strong>Detecció:</strong> El monitor detecta patrons sospitosos (SQLi, XSS, Path Traversal).</li>
								<li><strong>Anàlisi:</strong> Si la petició sembla maliciosa, s'envia a la IA.</li>
								<li><strong>Decisió:</strong> Si la puntuació és superior a 7, la IP es marca per bloqueig.</li>
								<li><strong>Acció:</strong> La pròxima vegada que aquesta IP intenti accedir, rebrà un error 403 Forbidden.</li>
							</ol>
						</section>
					</div>

					<div class="wpguard-guide-sidebar">
						<div class="wpguard-sidebar-card">
							<h4>Característiques Clau</h4>
							<ul class="wpguard-feature-list">
								<li><span>✓</span> Anàlisi Neuronal</li>
								<li><span>✓</span> Bloqueig Automàtic</li>
								<li><span>✓</span> Suport Ollama Local</li>
								<li><span>✓</span> Alertes per Email</li>
								<li><span>✓</span> Historial de Logs</li>
							</ul>
						</div>

						<div class="wpguard-sidebar-card" style="background: #f0f9ff; border-color: #bae6fd;">
							<h4 style="color: #0369a1;">Necessites ajuda?</h4>
							<p style="font-size: 13px; color: #0c4a6e;">Si trobes algun problema o vols suggerir una millora, pots contactar amb l'equip de seguretat o revisar la documentació tècnica al directori <code>docs/</code> del projecte.</p>
						</div>
					</div>
				</div>

			<?php else : ?>
				<div class="wpguard-table-card" style="padding: 30px;">
					<h2 style="background: transparent; padding: 0 0 20px 0;"><?php _e( 'Configuració del Sistema', 'wp-ai-guard' ); ?></h2>
					<form method="post" action="">
						<?php wp_nonce_field( 'wpguard_save_settings_action' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row"><?php _e( 'Motor d\'IA', 'wp-ai-guard' ); ?></th>
								<td>
									<select name="ai_engine" id="ai_engine" style="min-width: 250px; padding: 8px; border-radius: 8px;">
										<option value="gemini" <?php selected( $ai_engine, 'gemini' ); ?>>Google Gemini (Online/API)</option>
										<option value="ollama" <?php selected( $ai_engine, 'ollama' ); ?>>Ollama (Local AI - Free)</option>
									</select>
									<p class="description" style="margin-top: 10px;">
										<strong><?php _e( 'Modo Online (Gemini):', 'wp-ai-guard' ); ?></strong> <?php _e( 'Màxima precisió, requereix internet i API Key.', 'wp-ai-guard' ); ?><br>
										<strong><?php _e( 'Modo Local (Ollama):', 'wp-ai-guard' ); ?></strong> <?php _e( 'Privadesa total, gratuït, funciona sense internet directament en el teu servidor.', 'wp-ai-guard' ); ?>
									</p>
								</td>
							</tr>
							<tr class="gemini-settings" <?php echo $ai_engine !== 'gemini' ? 'style="display:none;"' : ''; ?>>
								<th scope="row"><?php _e( 'Gemini API Key', 'wp-ai-guard' ); ?></th>
								<td>
									<input name="gemini_api_key" type="password" id="gemini_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" style="border-radius: 8px; padding: 8px;">
								</td>
							</tr>
							<tr class="ollama-settings" <?php echo $ai_engine !== 'ollama' ? 'style="display:none;"' : ''; ?>>
								<th scope="row"><?php _e( 'Model d\'Ollama', 'wp-ai-guard' ); ?></th>
								<td>
									<input name="ollama_model" type="text" id="ollama_model" value="<?php echo esc_attr( $ollama_model ); ?>" class="regular-text" placeholder="ex: llama3" style="border-radius: 8px; padding: 8px;">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php _e( 'Mode de Seguretat', 'wp-ai-guard' ); ?></th>
								<td>
									<label class="switch">
										<input name="learning_mode" type="checkbox" id="learning_mode" value="1" <?php checked( $learning_mode, '1' ); ?>>
										<span class="slider round"></span>
										<span style="margin-left: 10px; font-weight: 500;"><?php _e( 'Activar Mode Aprenentatge (Només registrar)', 'wp-ai-guard' ); ?></span>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php _e( 'Retenció de Logs', 'wp-ai-guard' ); ?></th>
								<td>
									<input name="log_retention" type="number" id="log_retention" value="<?php echo esc_attr( $log_retention ); ?>" class="small-text" style="border-radius: 8px; padding: 5px;"> dies
								</td>
							</tr>
							<tr>
								<th scope="row"><?php _e( 'Notificacions per Email', 'wp-ai-guard' ); ?></th>
								<td>
									<input name="notifications_enabled" type="checkbox" id="notifications_enabled" value="1" <?php checked( $notifications_enabled, '1' ); ?>>
									<input name="notification_email" type="email" id="notification_email" value="<?php echo esc_attr( $notification_email ); ?>" class="regular-text" placeholder="email@exemple.com" style="border-radius: 8px; padding: 8px;">
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
						<div style="margin-top: 30px;">
							<?php submit_button( __( 'Guardar Canvis', 'wp-ai-guard' ), 'primary large', 'wpguard_save_settings', true, array('style' => 'border-radius: 12px; padding: 12px 30px; font-weight: bold; background: var(--wpguard-primary); border: none; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);') ); ?>
						</div>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
