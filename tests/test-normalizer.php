<?php
/**
 * Test PayloadNormalizer with actual Power Automate format
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\PayloadNormalizer;

$testPayload = [
    "EmailDetails" => [
        "MessageId" => "test-webhook-1768854570",
        "InternetMessageId" => "<test-696e942a5fee6@external.com>",
        "ConversationId" => "conv-test-1768854570",
        "Subject" => "Test Auto-Sync to Monday",
        "From" => "newcontact@example.com",
        "ToRecipients" => "charles@veerless.com",
        "CcRecipients" => "",
        "ReceivedDateTime" => "2026-01-19T20:29:30Z",
        "BodyPreview" => "Testing automatic sync to Monday.com after webhook ingestion.",
        "Body" => "Testing automatic sync to Monday.com after webhook ingestion. This should create a Monday item automatically.",
        "HasAttachments" => false,
        "Importance" => "Normal"
    ]
];

echo "=== Testing PayloadNormalizer ===\n\n";
echo "Input Payload:\n";
echo json_encode($testPayload, JSON_PRETTY_PRINT) . "\n\n";

$normalized = PayloadNormalizer::normalize($testPayload);

echo "Normalized Output:\n";
echo json_encode($normalized, JSON_PRETTY_PRINT) . "\n\n";

echo "Key Fields:\n";
echo "  From Email: " . ($normalized['data']['from_email'] ?? 'NULL') . "\n";
echo "  To Emails: " . json_encode($normalized['data']['to']) . "\n";
echo "  Subject: " . ($normalized['data']['subject'] ?? 'NULL') . "\n";
echo "  Message ID: " . ($normalized['data']['graph_message_id'] ?? 'NULL') . "\n";
echo "  Internet Message ID: " . ($normalized['data']['internet_message_id'] ?? 'NULL') . "\n";
