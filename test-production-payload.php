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

// Real payload from Power Automate
$paPayload = [
    "provider" => "m365",
    "mailbox" => "networking@veerless.com",
    "graphMessageId" => "AAMkADI5ZDY4YTc0LTEyZDAtNGFlOS1iYzgjLTAxOWM3MGY2NDUwZgBGAAAAAACs6qdOGRiwQJuF4f4clgvYBwD-mvEI6z8PT41WLQPJ9knHAAAAAAEMAAD-mvEI6z8PT41WLQPJ9knHAAAHyIwUAAA=",
    "internetMessageId" => "<TEST123@example.com>",
    "conversationId" => "AAQkADI5ZDY4YTc0LTEyZDAtNGFlOS1iYzgjLTAxOWM3MGY2NDUwZgAQAGoX1CzgOqxOmF9PyrXUqvk=",
    "subject" => "Production Test Email",
    "fromEmail" => "charlie@veerless.com",
    "toEmails" => "networking@veerless.com",
    "ccEmails" => "",
    "receivedDateTime" => "2026-01-19T17:34:45+00:00",
    "sentDateTime" => "",
    "webLink" => "",
    "bodyPreview" => "This is a production test email",
    "hasAttachments" => "False",
    "importance" => "normal"
];

echo "Testing with production payload format...\n\n";

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
echo "   ✓ Normalized\n";
echo "   From: " . $normalized['data']['from_email'] . "\n";
echo "   To: " . implode(', ', $normalized['data']['to']) . "\n";
echo "   Graph ID: " . $normalized['data']['graph_message_id'] . "\n\n";

// Process webhook
echo "2. Processing webhook...\n";
try {
    $result = $webhookService->processEmailWebhook($normalized['data']);
    
    echo "   ✓ Success!\n";
    echo "   Email ID: " . $result['email_id'] . "\n";
    echo "   Thread ID: " . $result['thread_id'] . "\n";
    echo "   Direction: " . $result['direction'] . "\n";
    echo "   External: " . $result['external_email'] . "\n";
    echo "   Internal: " . $result['internal_email'] . "\n";
    echo "   Duplicate: " . ($result['duplicate'] ? 'Yes' : 'No') . "\n\n";
    
    // Check database
    echo "3. Verifying in database...\n";
    $email = $emailRepo->findByGraphMessageId($normalized['data']['graph_message_id']);
    if ($email) {
        echo "   ✓ Found in database!\n";
        echo "   Subject: " . $email['subject'] . "\n";
    } else {
        echo "   ✗ NOT found in database\n";
    }
    
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}
