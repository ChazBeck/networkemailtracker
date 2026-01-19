<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = App\Core\Database::getInstance();

echo "=== ALL EMAILS (Most Recent First) ===\n\n";
$stmt = $db->query("
    SELECT id, thread_id, direction, subject, from_email, 
           DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created,
           DATE_FORMAT(received_at, '%Y-%m-%d %H:%i:%s') as received
    FROM emails 
    ORDER BY id DESC 
    LIMIT 20
");

$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($emails as $email) {
    echo "Email ID: {$email['id']}\n";
    echo "  Thread: {$email['thread_id']}\n";
    echo "  Direction: {$email['direction']}\n";
    echo "  From: {$email['from_email']}\n";
    echo "  Subject: {$email['subject']}\n";
    echo "  Received: {$email['received']}\n";
    echo "  Created: {$email['created']}\n";
    echo "\n";
}

echo "\n=== ALL THREADS ===\n\n";
$stmt = $db->query("
    SELECT t.id, t.external_email, t.internal_sender_email, t.subject_normalized,
           COUNT(e.id) as email_count
    FROM threads t
    LEFT JOIN emails e ON t.id = e.thread_id
    GROUP BY t.id
    ORDER BY t.id DESC
");

$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($threads as $thread) {
    echo "Thread ID: {$thread['id']}\n";
    echo "  External: {$thread['external_email']}\n";
    echo "  Internal: {$thread['internal_sender_email']}\n";
    echo "  Subject: {$thread['subject_normalized']}\n";
    echo "  Emails: {$thread['email_count']}\n";
    echo "\n";
}
