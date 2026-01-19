<?php

/**
 * Test Power Automate payload normalization
 * 
 * Usage: php test-power-automate.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\PayloadNormalizer;

// Simulate Power Automate payload
$powerAutomatePayload = [
    'provider' => 'm365',
    'mailbox' => 'networking@veerless.com',
    'graphMessageId' => 'AAMkAGZjNmVmOTQ1LWVhZmYtNGFmOC05YzQyLWY3ZTM5YWY2YWIyYwBGAAAAAACp8RbOzlSjQpAMy-sample',
    'internetMessageId' => '<AM6PR09MB4269F7B5C0F3E9F@AM6PR09MB4269.eurprd09.prod.outlook.com>',
    'conversationId' => 'AAQkAGZjNmVmOTQ1LWVhZmYtNGFmOC05YzQyLWY3ZTM5YWY2YWIyYwAQAJxLsample',
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
    'bodyPreview' => 'Hi Alice, I wanted to reach out about...',
    'hasAttachments' => false,
    'importance' => 'normal',
    'raw' => [
        '@odata.context' => 'https://graph.microsoft.com/v1.0/$metadata#Message',
        'id' => 'AAMkAGZjNmVmOTQ1...',
        'subject' => 'Partnership Opportunity',
        'from' => [
            'emailAddress' => [
                'name' => 'John External',
                'address' => 'john@external.com'
            ]
        ]
    ]
];

echo "====================================\n";
echo "Power Automate Payload Normalization Test\n";
echo "====================================\n\n";

echo "Original Power Automate payload:\n";
echo json_encode($powerAutomatePayload, JSON_PRETTY_PRINT) . "\n\n";

echo "------------------------------------\n\n";

$normalized = PayloadNormalizer::normalize($powerAutomatePayload);

echo "Normalized payload:\n";
echo json_encode($normalized, JSON_PRETTY_PRINT) . "\n\n";

echo "====================================\n";
echo "Extracted Email Addresses\n";
echo "====================================\n\n";

echo "From: " . ($normalized['data']['from_email'] ?? 'N/A') . "\n";
echo "To: " . implode(', ', $normalized['data']['to']) . "\n";
echo "CC: " . implode(', ', $normalized['data']['cc']) . "\n";
echo "BCC: " . implode(', ', $normalized['data']['bcc']) . "\n";

echo "\n====================================\n";
echo "Date Formats\n";
echo "====================================\n\n";

echo "Sent: " . ($normalized['data']['sent_at'] ?? 'N/A') . "\n";
echo "Received: " . ($normalized['data']['received_at'] ?? 'N/A') . "\n";

echo "\n✅ Normalization test complete!\n";
