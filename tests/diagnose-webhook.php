<?php
/**
 * Diagnose why enrichment and Monday sync aren't working
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = Database::getInstance();

echo "=== Webhook Processing Diagnosis ===\n\n";

// Get latest thread
$thread = $db->query('SELECT * FROM threads ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

echo "Latest Thread:\n";
echo "  ID: " . $thread['id'] . "\n";
echo "  External Email: " . ($thread['external_email'] ?? 'NULL') . "\n";
echo "  Subject: " . ($thread['subject_normalized'] ?? 'NULL') . "\n";
echo "  Status: " . ($thread['status'] ?? 'NULL') . "\n";
echo "  Created: " . ($thread['created_at'] ?? 'NULL') . "\n";
echo "\n";

// Check enrichment
echo "Enrichment Status:\n";
$enrich = $db->query("SELECT * FROM contact_enrichment WHERE thread_id = {$thread['id']}")->fetch(PDO::FETCH_ASSOC);
if ($enrich) {
    echo "  ✓ Enrichment record exists\n";
    echo "  Status: " . $enrich['enrichment_status'] . "\n";
    echo "  First Name: " . ($enrich['first_name'] ?? 'NULL') . "\n";
    echo "  Company: " . ($enrich['company_name'] ?? 'NULL') . "\n";
    echo "  Created: " . ($enrich['created_at'] ?? 'NULL') . "\n";
} else {
    echo "  ✗ NO enrichment record found\n";
    echo "  This means EnrichmentService was NOT called\n";
}
echo "\n";

// Check Monday sync
echo "Monday.com Sync Status:\n";
$monday = $db->query("SELECT * FROM monday_sync WHERE thread_id = {$thread['id']}")->fetch(PDO::FETCH_ASSOC);
if ($monday) {
    echo "  ✓ Monday sync record exists\n";
    echo "  Status: " . $monday['sync_status'] . "\n";
    echo "  Monday Item ID: " . ($monday['monday_item_id'] ?? 'NULL') . "\n";
    echo "  Created: " . ($monday['created_at'] ?? 'NULL') . "\n";
} else {
    echo "  ✗ NO Monday sync record found\n";
    echo "  This means MondayService was NOT called\n";
}
echo "\n";

// Check emails for this thread
echo "Emails in this thread:\n";
$emails = $db->query("SELECT id, direction, from_email, subject, created_at FROM emails WHERE thread_id = {$thread['id']} ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "  Total emails: " . count($emails) . "\n";
foreach ($emails as $email) {
    echo "  - Email #{$email['id']}: {$email['direction']} from {$email['from_email']}\n";
}
echo "\n";

// Possible reasons
echo "=== Diagnosis ===\n";
if (!$enrich && !$monday) {
    echo "❌ Neither enrichment nor Monday sync ran.\n\n";
    echo "Possible reasons:\n";
    echo "1. The webhook detected this as a DUPLICATE email\n";
    echo "   - Check if graph_message_id or internet_message_id already exists\n";
    echo "2. The WebhookController didn't have services injected\n";
    echo "   - Check index.php initialization\n";
    echo "3. An exception was thrown and caught silently\n";
    echo "   - Check logs/app-*.log files\n";
    echo "4. External email is NULL or empty\n";
    echo "   - External email: " . ($thread['external_email'] ?? 'NULL') . "\n";
} else {
    echo "✓ Services appear to be working\n";
}
