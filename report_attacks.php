<?php
define('WP_USE_THEMES', false);
require_once('wordpress/wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'wpguard_logs';

$results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 5");

if (empty($results)) {
    echo "No logs found.\n";
} else {
    echo "LAST 5 SECURITY ALERTS:\n";
    echo "=======================\n";
    foreach ($results as $log) {
        echo "ID: {$log->id}\n";
        echo "IP: {$log->ip}\n";
        echo "THREAT SCORE: {$log->threat_score}/10\n";
        echo "AI ANALYSIS: " . ($log->ai_analysis ?: "Pending...") . "\n";
        echo "REQUEST: " . $log->request_data . "\n";
        echo "-----------------------\n";
    }
}
