<?php

/**
 * Diagnostic: Check link tracking setup on production
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = App\Core\Database::getInstance();

echo "🔍 Link Tracking Diagnostic\n";
echo str_repeat('=', 60) . "\n\n";

// 1. Check if draft_id column exists
echo "1. Checking draft_id column...\n";
$stmt = $db->query("SHOW COLUMNS FROM link_tracking LIKE 'draft_id'");
$result = $stmt->fetch();
if ($result) {
    echo "   ✅ draft_id column exists\n\n";
} else {
    echo "   ❌ draft_id column MISSING - need to run migration 008\n\n";
}

// 2. Count total links
echo "2. Checking link_tracking table...\n";
$stmt = $db->query("SELECT COUNT(*) as total FROM link_tracking");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Total links: " . $result['total'] . "\n";

// 3. Count links with email_id
$stmt = $db->query("SELECT COUNT(*) as with_email FROM link_tracking WHERE email_id IS NOT NULL");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Links with email_id: " . $result['with_email'] . "\n";

// 4. Count links with draft_id
if ($db->query("SHOW COLUMNS FROM link_tracking LIKE 'draft_id'")->fetch()) {
    $stmt = $db->query("SELECT COUNT(*) as with_draft FROM link_tracking WHERE draft_id IS NOT NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Links with draft_id: " . $result['with_draft'] . "\n\n";
}

// 5. Show sample links
echo "3. Sample link records:\n";
$stmt = $db->query("SELECT id, email_id, draft_id, short_url, created_at FROM link_tracking ORDER BY id DESC LIMIT 5");
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($links as $link) {
    echo "   ID {$link['id']}: email_id={$link['email_id']}, draft_id=" . ($link['draft_id'] ?? 'N/A') . ", url={$link['short_url']}\n";
}

// 6. Check recent emails
echo "\n4. Recent emails:\n";
$stmt = $db->query("SELECT id, subject, from_email, created_at FROM emails ORDER BY id DESC LIMIT 5");
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($emails as $email) {
    echo "   ID {$email['id']}: {$email['subject']} from {$email['from_email']}\n";
    
    // Check if this email has links
    $stmt2 = $db->prepare("SELECT COUNT(*) as count FROM link_tracking WHERE email_id = ?");
    $stmt2->execute([$email['id']]);
    $linkCount = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo "      -> Has {$linkCount['count']} tracked links\n";
}

// 7. Check for links that could be matched
echo "\n5. Checking for orphaned links (with draft_id but no email_id):\n";
if ($db->query("SHOW COLUMNS FROM link_tracking LIKE 'draft_id'")->fetch()) {
    $stmt = $db->query("SELECT draft_id, COUNT(*) as count FROM link_tracking WHERE draft_id IS NOT NULL AND email_id IS NULL GROUP BY draft_id");
    $orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($orphans)) {
        echo "   No orphaned links found\n";
    } else {
        foreach ($orphans as $orphan) {
            echo "   draft_id '{$orphan['draft_id']}': {$orphan['count']} links\n";
        }
    }
}

echo "\n✅ Diagnostic complete!\n";
