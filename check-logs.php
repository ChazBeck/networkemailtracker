<?php
/**
 * Quick log viewer for debugging
 */

$logsDir = __DIR__ . '/logs';
$logFiles = glob($logsDir . '/*.log');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Log Viewer</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h2 { color: #4ec9b0; }
        .log-file { background: #252526; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .log-entry { padding: 5px; border-bottom: 1px solid #333; }
        .log-entry.error { background: #5a1d1d; }
        .log-entry.warning { background: #5a4a1d; }
        .log-entry.info { background: #1d3a5a; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <h1>📋 Application Logs</h1>
    
<?php
// Get today's log file
$today = date('Y-m-d');
$todayLog = $logsDir . '/app-' . $today . '.log';

if (file_exists($todayLog)) {
    echo "<div class='log-file'>";
    echo "<h2>Today's Log: " . basename($todayLog) . "</h2>";
    
    $lines = file($todayLog);
    $recent = array_slice($lines, -50); // Last 50 lines
    
    echo "<div style='max-height: 600px; overflow-y: auto;'>";
    foreach ($recent as $line) {
        $class = '';
        if (stripos($line, 'ERROR') !== false) $class = 'error';
        elseif (stripos($line, 'WARNING') !== false) $class = 'warning';
        elseif (stripos($line, 'INFO') !== false) $class = 'info';
        
        echo "<div class='log-entry $class'>" . htmlspecialchars($line) . "</div>";
    }
    echo "</div>";
    echo "</div>";
} else {
    echo "<p>No log file found for today: $todayLog</p>";
}

echo "<h2>All Log Files:</h2>";
foreach ($logFiles as $file) {
    $size = filesize($file);
    $modified = date('Y-m-d H:i:s', filemtime($file));
    echo "<div>" . basename($file) . " - " . number_format($size) . " bytes - Modified: $modified</div>";
}
?>

</body>
</html>
