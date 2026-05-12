<?php
/**
 * Test script to run AI analysis directly.
 */

define('WP_USE_THEMES', false);
require_once('/opt/lampp/htdocs/wordpress/wp-load.php');
require_once('/opt/lampp/htdocs/wordpress/wp-content/plugins/wp-ai-guard/includes/class-wp-ai-guard-ollama.php');

global $wpdb;
$log = $wpdb->get_row("SELECT * FROM wp_wpguard_logs WHERE threat_score = 5 LIMIT 1");

if (!$log) {
    echo "No pending logs found.\n";
    exit;
}

echo "Analyzing log ID: {$log->id} for IP: {$log->ip}...\n";

$ai = new WP_AI_Guard_Ollama();
$result = $ai->analyze_log($log);

if ($result) {
    echo "Analysis successful!\n";
    print_r($result);
} else {
    echo "Analysis failed. Check php_error_log.\n";
}
