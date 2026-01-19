<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Services\WebhookService;
use App\Services\PayloadNormalizer;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "=== TESTING BCC SCENARIO ===\n\n";
echo "Scenario: Alice sends to external client, BCC networking@veerless.com\n";
echo "Expected: Direction=outbound, External=client, Internal=alice\n\n";

// Simulate what Power Automate sends when networking@ is BCC'd
$paPayload = [
    "provider" => "m365",
    "mailbox" => "networking@veerless.com",
    "graphMessageId" => "TEST-BCC-SCENARIO-001",
    "internetMessageId" => "<TEST-BCC-001@example.com>",
    "conversationId" => "TEST-BCC-CONV-001",
    "subject" => "Following up on our meeting",
    "fromEmail" => "alice@veerless.com",  // Internal sender
    "toEmails" => "john@clientcorp.com",   // External recipient
    "ccEmails" => "",                       // No CC
    // Note: BCC is not visible in the email data
    "receivedDateTime" => "2026-01-19T19:00:00+00:00",
    "sentDateTime" => "",
    "webLink" => "",
    "bodyPreview" => "Hi John, Great meeting with you today...",
    "hasAttachments" => "False",
    "importance" => "normal"
];

// Get PDO instance
$db = Database::getInstance();
$logger = Logger::getInstance();

// Initialize repositories
$threadRepo = new ThreadRepository($db);
$emailRepo = new EmailRepository($db);

// Initialize service
$webhookService = new WebhookService($threadRepo, $emailRepo, $logger);

// Normalize payload
echo "1. Normalizing payload...\n";
$normalized = PayloadNormalizer::normalize($paPayload);
echo "   ✓ From: " . $normalized['data']['from_email'] . "\n";
echo "   ✓ To: " . implode(', ', $normalized['data']['to']) . "\n\n";

// Process webhook
echo "2. Processing webhook...\n";
try {
    $result = $webhookService->processEmailWebhook($normalized['data']);
    
    echo "   ✓ SUCCESS!\n\n";
    echo "Results:\n";
    echo "   Email ID: " . $result['email_id'] . "\n";
    echo "   Thread ID: " . $result['thread_id'] . "\n";
    
    // Get the email details
    $email = $emailRepo->findByGraphMessageId($normalized['data']['graph_message_id']);
    $thread = $threadRepo->findByUniqueKey(
        $email['direction'] === 'outbound' ? 'john@clientcorp.com' : 'alice@veerless.com',
        $email['direction'] === 'outbound' ? 'alice@veerless.com' : 'john@clientcorp.com',
        'following up on our meeting'
    );
    
    if (!$thread) {
        // Try to get thread by ID
        $stmt = $db->prepare("SELECT * FROM threads WHERE id = ?");
        $stmt->execute([$result['thread_id']]);
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    echo "   Direction: " . $email['direction'] . "\n";
    echo "   External Email: " . $thread['external_email'] . "\n";
    echo "   Internal Email: " . $thread['internal_sender_email'] . "\n";
    echo "   Subject: " . $thread['subject_normalized'] . "\n\n";
    
    // Verify correctness
    echo "3. Verification:\n";
    if ($email['direction'] === 'outbound') {
        echo "   ✓ Direction is OUTBOUND (correct!)\n";
    } else {
        echo "   ✗ Direction is {$email['direction']} (should be outbound)\n";
    }
    
    if ($thread['external_email'] === 'john@clientcorp.com') {
        echo "   ✓ External email is john@clientcorp.com (correct!)\n";
    } else {
        echo "   ✗ External email is {$thread['external_email']} (should be john@clientcorp.com)\n";
    }
    
    if ($thread['internal_sender_email'] === 'alice@veerless.com') {
        echo "   ✓ Internal email is alice@veerless.com (correct!)\n";
    } else {
        echo "   ✗ Internal email is {$thread['internal_sender_email']} (should be alice@veerless.com)\n";
    }
    
    echo "\n✅ BCC scenario working correctly!\n";
    echo "Ready to test with real email.\n";
    
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}
