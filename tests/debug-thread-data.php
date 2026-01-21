<?php
/**
 * Debug script to check what data is in threads
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = Database::getInstance();

echo "=== Thread Data Debug ===\n\n";

// Get most recent thread
$stmt = $db->query("SELECT * FROM threads ORDER BY created_at DESC LIMIT 1");
$thread = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$thread) {
    die("No threads found\n");
}

echo "Thread ID: {$thread['id']}\n";
echo "Conversation ID: " . ($thread['conversation_id'] ?? 'NULL') . "\n";
echo "Internet Message ID: " . ($thread['internet_message_id'] ?? 'NULL') . "\n";
echo "Body Preview: " . (isset($thread['body_preview']) ? substr($thread['body_preview'], 0, 100) . '...' : 'NULL') . "\n";
echo "\n";

echo "All thread fields:\n";
print_r(array_keys($thread));

echo "\n\n=== Email Data for this thread ===\n\n";

$stmt = $db->prepare("SELECT * FROM emails WHERE thread_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$thread['id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

if ($email) {
    echo "Email ID: {$email['id']}\n";
    echo "Internet Message ID: " . ($email['internet_message_id'] ?? 'NULL') . "\n";
    echo "Graph Message ID: " . ($email['graph_message_id'] ?? 'NULL') . "\n";
    echo "Body Preview: " . (isset($email['body_preview']) ? substr($email['body_preview'], 0, 100) . '...' : 'NULL') . "\n";
    echo "\n";
    echo "All email fields:\n";
    print_r(array_keys($email));
} else {
    echo "No emails found for this thread\n";
}
