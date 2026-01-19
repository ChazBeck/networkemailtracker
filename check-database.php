<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get database
$db = Database::getInstance();

echo "=== CHECKING PRODUCTION DATABASE ===\n\n";

// Count threads
$stmt = $db->query("SELECT COUNT(*) as count FROM threads");
$threadCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "Threads: $threadCount\n";

// Count emails
$stmt = $db->query("SELECT COUNT(*) as count FROM emails");
$emailCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "Emails: $emailCount\n\n";

if ($emailCount > 0) {
    echo "=== RECENT EMAILS ===\n";
    $stmt = $db->query("
        SELECT id, thread_id, direction, from_email, subject, 
               DATE_FORMAT(received_at, '%Y-%m-%d %H:%i:%s') as received_at,
               DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at
        FROM emails 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($emails as $email) {
        echo "\nEmail ID: {$email['id']}\n";
        echo "  Thread: {$email['thread_id']}\n";
        echo "  Direction: {$email['direction']}\n";
        echo "  From: {$email['from_email']}\n";
        echo "  Subject: {$email['subject']}\n";
        echo "  Received: {$email['received_at']}\n";
        echo "  Created: {$email['created_at']}\n";
    }
}

if ($threadCount > 0) {
    echo "\n=== RECENT THREADS ===\n";
    $stmt = $db->query("
        SELECT id, external_email, internal_sender_email, subject_normalized, status, 
               DATE_FORMAT(last_activity_at, '%Y-%m-%d %H:%i:%s') as last_activity_at
        FROM threads 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($threads as $thread) {
        echo "\nThread ID: {$thread['id']}\n";
        echo "  External: {$thread['external_email']}\n";
        echo "  Internal: {$thread['internal_sender_email']}\n";
        echo "  Subject: {$thread['subject_normalized']}\n";
        echo "  Status: {$thread['status']}\n";
        echo "  Last Activity: {$thread['last_activity_at']}\n";
    }
}
