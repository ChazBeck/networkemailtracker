<?php

/**
 * Debug: Show all fields in the emails table for recent email
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = App\Core\Database::getInstance();

echo "🔍 All Email Fields for Most Recent Email\n";
echo str_repeat('=', 60) . "\n\n";

// Get the most recent email with ALL fields
$stmt = $db->query("SELECT * FROM emails ORDER BY id DESC LIMIT 1");
$email = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$email) {
    echo "❌ No emails found\n";
    exit(1);
}

echo "Email ID: {$email['id']}\n";
echo "Subject: {$email['subject']}\n\n";

foreach ($email as $field => $value) {
    if ($field === 'body_text' || $field === 'body_preview') {
        $length = strlen($value ?? '');
        $preview = substr($value ?? '', 0, 100);
        echo "$field: ($length chars) $preview" . ($length > 100 ? "..." : "") . "\n";
    } else {
        echo "$field: " . ($value ?? 'NULL') . "\n";
    }
}

echo "\n✅ Check complete!\n";
