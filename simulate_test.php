<?php
/**
 * Simulation script to test WP-AI-Guard detection logic.
 */

// Mock WordPress ABSPATH if not defined
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/wordpress/');
}

// Mock some WP functions used in the plugin
function wp_json_encode($data) {
    return json_encode($data);
}

function add_action($hook, $callback) {
    // Just a mock
}

function __($text, $domain) {
    return $text;
}

// Include the class to test
require_once 'wordpress/wp-content/plugins/wp-ai-guard/includes/class-wp-ai-guard-monitor.php';

// Create a subclass to expose private methods for testing
class TestMonitor extends WP_AI_Guard_Monitor {
    public function test_detection($data) {
        $reflection = new ReflectionClass('WP_AI_Guard_Monitor');
        $method = $reflection->getMethod('check_suspicious_data');
        $method->setAccessible(true);
        return $method->invoke(new WP_AI_Guard_Monitor(), $data);
    }
}

$monitor = new TestMonitor();

$test_cases = [
    'Normal request' => ['url' => '/index.php', 'post' => []],
    'XSS Attempt' => ['url' => '/?s=<script>alert(1)</script>', 'post' => []],
    'SQL Injection' => ['url' => '/product.php?id=1 UNION SELECT user,pass FROM users', 'post' => []],
    'Encoded XSS' => ['url' => '/?name=<b>Test</b>', 'post' => []],
    'Normal POST' => ['url' => '/comment.php', 'post' => ['comment' => 'This is a great post!']],
    'Malicious POST' => ['url' => '/comment.php', 'post' => ['comment' => "'; DROP TABLE wp_users; --"]],
];

echo "Testing WP-AI-Guard Detection Logic:\n";
echo str_repeat('-', 40) . "\n";

foreach ($test_cases as $name => $data) {
    $is_suspicious = $monitor->test_detection($data);
    $status = $is_suspicious ? "🚩 [SUSPICIOUS]" : "✅ [SAFE]";
    echo sprintf("%-15s: %s\n", $name, $status);
}
