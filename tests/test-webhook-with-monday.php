<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Test payload - simulate a Power Automate webhook
$testPayload = [
    'EmailDetails' => [
        'MessageId' => 'test-webhook-' . time(),
        'InternetMessageId' => '<test-' . uniqid() . '@external.com>',
        'ConversationId' => 'conv-test-' . time(),
        'Subject' => 'Test Auto-Sync to Monday',
        'From' => 'newcontact@example.com',
        'ToRecipients' => 'charles@veerless.com',
        'CcRecipients' => '',
        'ReceivedDateTime' => date('Y-m-d\TH:i:s\Z'),
        'BodyPreview' => 'Testing automatic sync to Monday.com after webhook ingestion.',
        'Body' => 'Testing automatic sync to Monday.com after webhook ingestion. This should create a Monday item automatically.',
        'HasAttachments' => false,
        'Importance' => 'Normal'
    ]
];

// Make HTTP request to webhook endpoint
$ch = curl_init('http://localhost/networkemailtracking/webhook-ingest.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testPayload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Webhook Test with Monday Auto-Sync ===\n\n";
echo "HTTP Status: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode === 202) {
    $result = json_decode($response, true);
    
    echo "✓ Webhook processed successfully\n";
    echo "  Thread ID: {$result['thread_id']}\n";
    echo "  Email ID: {$result['email_id']}\n";
    echo "  Duplicate: " . ($result['duplicate'] ? 'Yes' : 'No') . "\n\n";
    
    if (!$result['duplicate']) {
        echo "Checking Monday sync status...\n";
        sleep(2); // Give it a moment
        
        // Check if synced
        $db = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS']
        );
        
        $stmt = $db->prepare("
            SELECT ms.*, t.external_email 
            FROM monday_sync ms 
            JOIN threads t ON ms.thread_id = t.id 
            WHERE ms.thread_id = ?
        ");
        $stmt->execute([$result['thread_id']]);
        $sync = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sync) {
            echo "✓ Auto-synced to Monday!\n";
            echo "  Monday Item ID: {$sync['item_id']}\n";
            echo "  Status: {$sync['last_push_status']}\n";
            echo "  View at: https://chuckbeck.monday.com/boards/{$_ENV['MONDAY_BOARD_ID']}/pulses/{$sync['item_id']}\n";
        } else {
            echo "✗ Not synced to Monday (might be internal-only thread)\n";
        }
    }
} else {
    echo "✗ Webhook failed\n";
}

echo "\n=== Test Complete ===\n";
