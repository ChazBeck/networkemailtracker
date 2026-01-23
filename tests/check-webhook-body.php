<?php

/**
 * Check what the webhook received in body_text for recent email
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = App\Core\Database::getInstance();

echo "🔍 Checking webhook body_text\n";
echo str_repeat('=', 60) . "\n\n";

// Get the most recent email
$stmt = $db->query("SELECT id, subject, body_text FROM emails ORDER BY id DESC LIMIT 1");
$email = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$email) {
    echo "❌ No emails found\n";
    exit(1);
}

echo "Email ID: {$email['id']}\n";
echo "Subject: {$email['subject']}\n\n";

// Check for veerl.es links
if (preg_match_all('/https?:\/\/veerl\.es\/[a-zA-Z0-9\-]+/', $email['body_text'], $matches)) {
    echo "✅ Found " . count($matches[0]) . " veerl.es link(s) in body:\n";
    foreach (array_unique($matches[0]) as $url) {
        echo "   - $url\n";
        
        // Check if this URL exists in link_tracking
        $stmt = $db->prepare("SELECT id, email_id, draft_id FROM link_tracking WHERE short_url = ?");
        $stmt->execute([$url]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($link) {
            $status = $link['email_id'] ? "Linked to email {$link['email_id']}" : "NOT linked (email_id is NULL)";
            echo "     → Found in DB: ID {$link['id']}, draft_id={$link['draft_id']}, $status\n";
        } else {
            echo "     → ❌ NOT FOUND in link_tracking table\n";
        }
    }
} else {
    echo "❌ NO veerl.es links found in body_text\n\n";
    echo "Body preview (first 1000 chars):\n";
    echo str_repeat('-', 60) . "\n";
    echo substr($email['body_text'], 0, 1000) . "\n";
    echo str_repeat('-', 60) . "\n";
}

echo "\n✅ Check complete!\n";
