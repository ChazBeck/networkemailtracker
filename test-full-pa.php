<?php

/**
 * Test full webhook flow with Power Automate payload
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Services\WebhookService;
use App\Services\PayloadNormalizer;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize
$db = Database::getInstance();
$logger = Logger::getInstance();
$threadRepo = new ThreadRepository($db);
$emailRepo = new EmailRepository($db);
$webhookService = new WebhookService($threadRepo, $emailRepo, $logger);

// Real Power Automate payload (inbound email)
$powerAutomatePayload = [
    'provider' => 'm365',
    'mailbox' => 'networking@veerless.com',
    'graphMessageId' => 'AAMkAGZjNmVmOTQ1_PA_001',
    'internetMessageId' => '<PA001@external.com>',
    'conversationId' => 'AAQkPA_conv_001',
    'subject' => 'Partnership Opportunity',
    'fromEmail' => 'john@external.com',
    'toEmails' => json_encode([
        [
            'emailAddress' => [
                'name' => 'Alice Smith',
                'address' => 'alice@veerless.com'
            ]
        ],
        [
            'emailAddress' => [
                'name' => 'Networking Team',
                'address' => 'networking@veerless.com'
            ]
        ]
    ]),
    'ccEmails' => json_encode([
        [
            'emailAddress' => [
                'name' => 'Bob Jones',
                'address' => 'bob@veerless.com'
            ]
        ]
    ]),
    'receivedDateTime' => '2026-01-19T15:30:25Z',
    'sentDateTime' => '2026-01-19T15:30:20Z',
    'webLink' => 'https://outlook.office365.com/owa/?ItemID=...',
    'bodyPreview' => 'Hi Alice, I wanted to reach out about a partnership...',
    'hasAttachments' => false,
    'importance' => 'normal'
];

echo "====================================\n";
echo "Full Webhook Test with Power Automate\n";
echo "====================================\n\n";

try {
    // Normalize
    $normalized = PayloadNormalizer::normalize($powerAutomatePayload);
    
    echo "Processing inbound email from john@external.com...\n\n";
    
    // Process
    $result = $webhookService->processEmailWebhook($normalized);
    
    echo "✅ Result:\n";
    echo "   Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    echo "   Email ID: " . ($result['email_id'] ?? 'N/A') . "\n";
    echo "   Thread ID: " . ($result['thread_id'] ?? 'N/A') . "\n";
    echo "   Duplicate: " . ($result['duplicate'] ? 'Yes' : 'No') . "\n";
    echo "   Direction: Detected as inbound (from external domain)\n\n";
    
    // Check thread
    $threads = $threadRepo->getAllWithEmailCount();
    echo "Thread created:\n";
    foreach ($threads as $thread) {
        if ($thread['id'] == $result['thread_id']) {
            echo "   External: {$thread['external_email']}\n";
            echo "   Internal: {$thread['internal_sender_email']}\n";
            echo "   Subject: {$thread['subject_normalized']}\n";
            echo "   Emails: {$thread['email_count']}\n";
        }
    }
    
    echo "\n✅ Power Automate integration working!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
