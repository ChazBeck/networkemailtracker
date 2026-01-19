<?php
header('Content-Type: text/plain');

$debugLog = __DIR__ . '/logs/webhook-debug.log';
$appLog = __DIR__ . '/logs/app.log';

echo "=== WEBHOOK DEBUG LOG (Last 50 lines) ===\n\n";
if (file_exists($debugLog)) {
    $lines = file($debugLog);
    $lastLines = array_slice($lines, -50);
    echo implode('', $lastLines);
} else {
    echo "File not found: $debugLog\n";
}

echo "\n\n=== APPLICATION LOG (Last 50 lines) ===\n\n";
if (file_exists($appLog)) {
    $lines = file($appLog);
    $lastLines = array_slice($lines, -50);
    echo implode('', $lastLines);
} else {
    echo "File not found: $appLog\n";
}
