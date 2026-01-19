<?php

/**
 * Test webhook ingestion with sample payloads
 * 
 * Usage: php test-webhook.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Services\WebhookService;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize
$db = Database::getInstance();
$logger = Logger::getInstance();
$threadRepo = new ThreadRepository($db);
$emailRepo = new EmailRepository($db);
$webhookService = new WebhookService($threadRepo, $emailRepo, $logger);

// Test fixtures
$fixtures = [
    [
        'name' => 'Outbound initial email',
        'payload' => [
            'event_type' => 'email.sent',
            'event_id' => 'evt_001',
            'timestamp' => '2026-01-19T10:00:05+00:00',
            'data' => [
                'provider' => 'm365',
                'mailbox' => 'networking@veerless.com',
                'graph_message_id' => 'AAMkAGM2_fixture_001',
                'internet_message_id' => '<fixture-001@veerless.local>',
                'conversation_id' => 'conv_abc123',
                'subject' => 'Intro — Veerless x Acme',
                'from_email' => 'alice@veerless.com',
                'to' => ['joe@acme.com'],
                'cc' => [],
                'bcc' => ['networking@veerless.com'],
                'direction' => 'outbound',
                'sent_at' => '2026-01-19T10:00:00+00:00',
                'received_at' => null,
                'body_preview' => 'Hi Joe — quick intro...',
                'body_text' => "Hi Joe,\n\nQuick intro to our work.\n\nBest,\nAlice",
                'raw_payload' => ['fixture' => true]
            ]
        ]
    ],
    [
        'name' => 'Inbound reply',
        'payload' => [
            'event_type' => 'email.received',
            'event_id' => 'evt_002',
            'timestamp' => '2026-01-19T11:30:10+00:00',
            'data' => [
                'provider' => 'm365',
                'mailbox' => 'networking@veerless.com',
                'graph_message_id' => 'AAMkAGM2_fixture_002',
                'internet_message_id' => '<fixture-002@acme.com>',
                'conversation_id' => 'conv_abc123',
                'subject' => 'Re: Intro — Veerless x Acme',
                'from_email' => 'joe@acme.com',
                'to' => ['alice@veerless.com'],
                'cc' => [],
                'bcc' => ['networking@veerless.com'],
                'direction' => 'inbound',
                'sent_at' => '2026-01-19T11:30:00+00:00',
                'received_at' => '2026-01-19T11:30:05+00:00',
                'body_preview' => 'Thanks Alice, sounds great...',
                'body_text' => "Thanks Alice, sounds great!\n\nLet's schedule a call.\n\nJoe",
                'raw_payload' => ['fixture' => true]
            ]
        ]
    ],
    [
        'name' => 'Duplicate email (same graph_message_id)',
        'payload' => [
            'event_type' => 'email.sent',
            'event_id' => 'evt_003_duplicate',
            'timestamp' => '2026-01-19T10:00:10+00:00',
            'data' => [
                'provider' => 'm365',
                'mailbox' => 'networking@veerless.com',
                'graph_message_id' => 'AAMkAGM2_fixture_001', // DUPLICATE
                'internet_message_id' => '<fixture-001@veerless.local>',
                'conversation_id' => 'conv_abc123',
                'subject' => 'Intro — Veerless x Acme',
                'from_email' => 'alice@veerless.com',
                'to' => ['joe@acme.com'],
                'cc' => [],
                'bcc' => ['networking@veerless.com'],
                'direction' => 'outbound',
                'sent_at' => '2026-01-19T10:00:00+00:00',
                'received_at' => null,
                'body_preview' => 'Hi Joe — quick intro...',
                'body_text' => "Hi Joe,\n\nQuick intro to our work.\n\nBest,\nAlice",
                'raw_payload' => ['fixture' => true]
            ]
        ]
    ],
    [
        'name' => 'Different thread (different external party)',
        'payload' => [
            'event_type' => 'email.sent',
            'event_id' => 'evt_004',
            'timestamp' => '2026-01-19T14:00:00+00:00',
            'data' => [
                'provider' => 'm365',
                'mailbox' => 'networking@veerless.com',
                'graph_message_id' => 'AAMkAGM2_fixture_004',
                'internet_message_id' => '<fixture-004@veerless.local>',
                'conversation_id' => 'conv_xyz789',
                'subject' => 'Partnership inquiry',
                'from_email' => 'bob@veerless.com',
                'to' => ['sarah@techcorp.com'],
                'cc' => [],
                'bcc' => ['networking@veerless.com'],
                'direction' => 'outbound',
                'sent_at' => '2026-01-19T14:00:00+00:00',
                'received_at' => null,
                'body_preview' => 'Hi Sarah...',
                'body_text' => "Hi Sarah,\n\nI hope this email finds you well.\n\nBob",
                'raw_payload' => ['fixture' => true]
            ]
        ]
    ]
];

echo "====================================\n";
echo "Testing Webhook Ingestion\n";
echo "====================================\n\n";

foreach ($fixtures as $index => $fixture) {
    echo "[" . ($index + 1) . "] Testing: {$fixture['name']}\n";
    echo str_repeat('-', 60) . "\n";
    
    try {
        $result = $webhookService->processEmailWebhook($fixture['payload']);
        
        echo "✅ Result:\n";
        echo "   Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
        echo "   Email ID: " . ($result['email_id'] ?? 'N/A') . "\n";
        echo "   Thread ID: " . ($result['thread_id'] ?? 'N/A') . "\n";
        echo "   Duplicate: " . ($result['duplicate'] ? 'Yes' : 'No') . "\n";
        
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    echo "\n";
}

// Display summary
echo "====================================\n";
echo "Database Summary\n";
echo "====================================\n\n";

$threads = $threadRepo->getAllWithEmailCount();
echo "Threads created: " . count($threads) . "\n";
foreach ($threads as $thread) {
    echo "  - ID {$thread['id']}: {$thread['external_email']} <-> {$thread['internal_sender_email']}\n";
    echo "    Subject: " . ($thread['subject_normalized'] ?? 'N/A') . "\n";
    echo "    Emails: {$thread['email_count']}, Status: {$thread['status']}\n";
}

echo "\nRecent emails:\n";
$emails = $emailRepo->getRecent(10);
echo "Total emails: " . count($emails) . "\n";
foreach ($emails as $email) {
    echo "  - ID {$email['id']}: {$email['direction']} - {$email['from_email']}\n";
    echo "    Subject: {$email['subject']}\n";
    echo "    Thread ID: {$email['thread_id']}\n";
}

echo "\n✨ Test complete!\n";
