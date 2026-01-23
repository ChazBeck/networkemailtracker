<?php

/**
 * Check if the most recent email has the draft_id comment
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = App\Core\Database::getInstance();

echo "🔍 Checking for draft_id in recent emails\n";
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

// Check for draft_id comment
if (preg_match('/<!-- tracking-draft-id:([a-z0-9_\.]+) -->/', $email['body_text'], $matches)) {
    echo "✅ Found draft_id comment in body!\n";
    echo "   Draft ID: {$matches[1]}\n\n";
    
    // Check if this draft_id exists in link_tracking
    $stmt = $db->prepare("SELECT id, email_id, original_url, short_url FROM link_tracking WHERE draft_id = ?");
    $stmt->execute([$matches[1]]);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($links)) {
        echo "   ⚠️  No links found with this draft_id\n";
    } else {
        echo "   Found {count($links)} link(s) with this draft_id:\n";
        foreach ($links as $link) {
            $status = $link['email_id'] ? "✅ Linked to email {$link['email_id']}" : "❌ NOT linked (email_id is NULL)";
            echo "   - ID {$link['id']}: {$link['short_url']} → {$status}\n";
        }
    }
} else {
    echo "❌ NO draft_id comment found in body\n\n";
    echo "Body preview (first 500 chars):\n";
    echo substr($email['body_text'], 0, 500) . "\n\n";
    
    // Check what links exist for this email
    $stmt = $db->prepare("SELECT id, draft_id, original_url, short_url FROM link_tracking WHERE email_id = ?");
    $stmt->execute([$email['id']]);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($links)) {
        echo "⚠️  This email has {count($links)} linked link(s):\n";
        foreach ($links as $link) {
            echo "   - ID {$link['id']}: draft_id={$link['draft_id']}, url={$link['short_url']}\n";
        }
    } else {
        echo "⚠️  No links are linked to this email\n";
    }
}

echo "\n✅ Check complete!\n";
