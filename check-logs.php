<?php
/**
 * Check production logs
 */

$logFile = __DIR__ . '/logs/app.log';

if (!file_exists($logFile)) {
    echo "Log file does not exist: $logFile\n";
    exit;
}

// Get last 100 lines
$lines = file($logFile);
$lastLines = array_slice($lines, -100);

echo "=== LAST 100 LINES OF APPLICATION LOG ===\n\n";
echo implode('', $lastLines);
