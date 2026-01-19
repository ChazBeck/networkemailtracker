<?php
/**
 * Setup Script - Fix log file permissions
 * Run this once after deploying to production: https://yourdomain.com/networkemailtracking/setup-permissions.php
 * DELETE THIS FILE after running it for security
 */

$logsDir = __DIR__ . '/logs';

echo "<h2>Setting up log permissions...</h2>";

// Check if logs directory exists
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0777, true);
    echo "<p>✓ Created logs directory</p>";
}

// Set directory permissions
if (chmod($logsDir, 0777)) {
    echo "<p>✓ Set logs directory to 777</p>";
} else {
    echo "<p>✗ Failed to set logs directory permissions (may already be correct)</p>";
}

// Set permissions on all files in logs directory
$files = glob($logsDir . '/*');
foreach ($files as $file) {
    if (is_file($file)) {
        if (chmod($file, 0666)) {
            echo "<p>✓ Set permissions on " . basename($file) . "</p>";
        } else {
            echo "<p>✗ Could not set permissions on " . basename($file) . "</p>";
        }
    }
}

// Test write access
$testFile = $logsDir . '/test-write-' . time() . '.log';
if (file_put_contents($testFile, 'Test write at ' . date('Y-m-d H:i:s'))) {
    echo "<p>✓ Successfully wrote test file</p>";
    unlink($testFile);
    echo "<p>✓ Cleaned up test file</p>";
} else {
    echo "<p>✗ Could not write test file - permissions may still be incorrect</p>";
}

echo "<hr>";
echo "<h3>Setup Complete!</h3>";
echo "<p><strong>IMPORTANT:</strong> Delete this file (setup-permissions.php) for security.</p>";
