<?php
/**
 * Webhook Debug Logger
 * Captures all POST requests to help debug Power Automate integration
 */

$logFile = __DIR__ . '/logs/webhook-debug.log';
$logDir = dirname($logFile);

// Ensure logs directory exists
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Capture request data
$debugData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'headers' => getallheaders(),
    'raw_body' => file_get_contents('php://input'),
    'get_params' => $_GET,
    'post_params' => $_POST,
    'server' => [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
    ]
];

// Try to decode JSON body
$body = $debugData['raw_body'];
if (!empty($body)) {
    $json = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $debugData['json_decoded'] = $json;
    }
}

// Log to file
$logEntry = "\n" . str_repeat('=', 80) . "\n";
$logEntry .= "WEBHOOK REQUEST DEBUG\n";
$logEntry .= str_repeat('=', 80) . "\n";
$logEntry .= json_encode($debugData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

file_put_contents($logFile, $logEntry, FILE_APPEND);

// Return success
header('Content-Type: application/json');
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Debug data logged',
    'timestamp' => date('c')
]);
