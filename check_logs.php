<?php
define('WP_USE_THEMES', false);
require_once('wordpress/wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'wpguard_logs';

$results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 10");

if (empty($results)) {
    echo "No logs found in $table_name.\n";
} else {
    echo "Recent logs in $table_name:\n";
    foreach ($results as $log) {
        echo "ID: {$log->id} | IP: {$log->ip} | Score: {$log->threat_score} | Status: {$log->status} | Created: {$log->created_at}\n";
        echo "Data: " . substr($log->request_data, 0, 100) . "...\n";
        echo "---------------------------------\n";
    }
}
