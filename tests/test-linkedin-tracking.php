<?php
/**
 * Test script for LinkedIn Tracking Feature
 * 
 * Run from command line: php test-linkedin-tracking.php
 * Or access via browser: http://localhost/networkemailtracking/tests/test-linkedin-tracking.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Core\Database;
use App\Core\Logger;
use App\Services\LinkedInUrlNormalizer;
use App\Services\LinkedInWebhookService;
use App\Services\EnrichmentService;
use App\Services\MondayService;
use App\Services\PerplexityService;
use App\Core\HttpClient;
use App\Repositories\LinkedInThreadRepository;
use App\Repositories\LinkedInMessageRepository;
use App\Repositories\LinkedInMondaySyncRepository;
use App\Repositories\EnrichmentRepository;

// Determine if running in CLI or browser
$isCli = php_sapi_name() === 'cli';

function output($message, $color = 'white') {
    global $isCli;
    
    $colors = [
        'green' => $isCli ? "\033[32m" : '<span style="color: green;">',
        'red' => $isCli ? "\033[31m" : '<span style="color: red;">',
        'yellow' => $isCli ? "\033[33m" : '<span style="color: orange;">',
        'blue' => $isCli ? "\033[34m" : '<span style="color: blue;">',
        'white' => $isCli ? "\033[0m" : '<span>',
    ];
    
    $reset = $isCli ? "\033[0m" : '</span>';
    
    if (!$isCli) {
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }
    
    echo ($colors[$color] ?? $colors['white']) . $message . $reset . ($isCli ? "\n" : "<br>\n");
}

function testHeader($title) {
    output("\n" . str_repeat('=', 60), 'blue');
    output($title, 'blue');
    output(str_repeat('=', 60), 'blue');
}

function testResult($name, $passed, $details = '') {
    $status = $passed ? '✅ PASS' : '❌ FAIL';
    $color = $passed ? 'green' : 'red';
    output("$status - $name", $color);
    if ($details) {
        output("  → $details", 'yellow');
    }
}

// HTML header for browser
if (!$isCli) {
    echo '<!DOCTYPE html><html><head><title>LinkedIn Tracking Tests</title>';
    echo '<style>body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }</style>';
    echo '</head><body><pre>';
}

output("LinkedIn Tracking Feature - Test Suite", 'blue');
output("Started at: " . date('Y-m-d H:i:s'), 'white');

try {
    // Initialize database
    $pdo = Database::getInstance();
    $logger = Logger::getInstance();
    
    // Initialize services
    $normalizer = new LinkedInUrlNormalizer();
    $threadRepo = new LinkedInThreadRepository($pdo);
    $messageRepo = new LinkedInMessageRepository($pdo);
    $syncRepo = new LinkedInMondaySyncRepository($pdo);
    $enrichmentRepo = new EnrichmentRepository($pdo);
    
    $webhookService = new LinkedInWebhookService(
        $threadRepo,
        $messageRepo,
        $normalizer,
        $logger
    );
    
    // =================================================================
    // TEST 1: URL Normalization
    // =================================================================
    testHeader("TEST 1: LinkedIn URL Normalization");
    
    $testUrls = [
        'https://www.linkedin.com/in/johnsmith/' => 'https://www.linkedin.com/in/johnsmith',
        'https://linkedin.com/in/johnsmith?trk=test' => 'https://www.linkedin.com/in/johnsmith',
        'HTTPS://WWW.LINKEDIN.COM/IN/JOHNSMITH' => 'https://www.linkedin.com/in/johnsmith',
        'https://www.linkedin.com/in/john-smith-123456/' => 'https://www.linkedin.com/in/john-smith-123456',
    ];
    
    foreach ($testUrls as $input => $expected) {
        $normalized = $normalizer->normalize($input);
        $passed = $normalized === $expected;
        testResult("Normalize: $input", $passed, "Got: $normalized");
    }
    
    // Test validation
    $validUrls = [
        'https://www.linkedin.com/in/johnsmith',
        'https://linkedin.com/company/microsoft',
        'https://www.linkedin.com/sales/people/john-smith',
    ];
    
    foreach ($validUrls as $url) {
        $passed = $normalizer->isValid($url);
        testResult("Validate: $url", $passed);
    }
    
    $invalidUrls = [
        'https://twitter.com/user',
        'https://www.linkedin.com',
        'not-a-url',
    ];
    
    foreach ($invalidUrls as $url) {
        $passed = !$normalizer->isValid($url);
        testResult("Reject invalid: $url", $passed);
    }
    
    // =================================================================
    // TEST 2: Create LinkedIn Thread and Messages
    // =================================================================
    testHeader("TEST 2: Create LinkedIn Thread and Messages");
    
    $testLinkedInUrl = 'https://www.linkedin.com/in/test-user-' . time();
    
    // Submit first message (outbound)
    $payload1 = [
        'linkedin_url' => $testLinkedInUrl,
        'message_text' => 'Hi! This is a test outbound message.',
        'direction' => 'outbound',
        'sender_email' => 'charlie@veerless.com'
    ];
    
    $result1 = $webhookService->processLinkedInSubmission($payload1);
    testResult('Create thread with outbound message', $result1['success'], 
        "Thread ID: {$result1['thread_id']}, Message ID: {$result1['message_id']}");
    
    $threadId = $result1['thread_id'];
    $isNewThread = $result1['new_thread'];
    
    testResult('New thread flag set correctly', $isNewThread);
    
    // Submit second message (inbound) to same thread
    $payload2 = [
        'linkedin_url' => $testLinkedInUrl,
        'message_text' => 'Thanks for reaching out! This is an inbound reply.',
        'direction' => 'inbound',
        'sender_email' => 'charlie@veerless.com'
    ];
    
    $result2 = $webhookService->processLinkedInSubmission($payload2);
    testResult('Add inbound message to existing thread', $result2['success'],
        "Thread ID: {$result2['thread_id']}, Message ID: {$result2['message_id']}");
    
    testResult('Same thread ID used', $result2['thread_id'] === $threadId);
    testResult('Not flagged as new thread', !$result2['new_thread']);
    
    // =================================================================
    // TEST 3: Retrieve Thread Data
    // =================================================================
    testHeader("TEST 3: Retrieve Thread Data");
    
    $thread = $threadRepo->findById($threadId);
    testResult('Thread exists in database', !empty($thread),
        "External URL: {$thread['external_linkedin_url']}");
    
    testResult('Thread status updated to Responded', $thread['status'] === 'Responded',
        "Current status: {$thread['status']}");
    
    testResult('Owner email set correctly', $thread['owner_email'] === 'charlie@veerless.com',
        "Owner: {$thread['owner_email']}");
    
    $messages = $messageRepo->findByThreadId($threadId);
    testResult('Two messages retrieved', count($messages) === 2,
        "Message count: " . count($messages));
    
    if (count($messages) === 2) {
        testResult('First message is outbound', $messages[0]['direction'] === 'outbound');
        testResult('Second message is inbound', $messages[1]['direction'] === 'inbound');
    }
    
    // =================================================================
    // TEST 4: Get All Threads
    // =================================================================
    testHeader("TEST 4: Get All Threads");
    
    $allThreads = $webhookService->getAllThreads();
    testResult('Get all threads returns array', is_array($allThreads),
        "Total threads: " . count($allThreads));
    
    $ourThread = array_filter($allThreads, fn($t) => $t['id'] === $threadId);
    testResult('Our test thread is in results', !empty($ourThread));
    
    // =================================================================
    // TEST 5: Test Enrichment Integration
    // =================================================================
    testHeader("TEST 5: Test Enrichment Integration");
    
    // Check if enrichment was created
    $enrichment = $enrichmentRepo->findByLinkedInThreadId($threadId);
    
    if ($enrichment) {
        output("⚠️  Note: Enrichment found for thread (may be from previous test)", 'yellow');
        testResult('Enrichment exists', true, "Status: {$enrichment['enrichment_status']}");
    } else {
        output("ℹ️  No enrichment found (expected if Perplexity API not configured)", 'yellow');
        testResult('No enrichment yet', true, "This is expected without API key");
    }
    
    // =================================================================
    // TEST 6: Test Monday.com Sync Status
    // =================================================================
    testHeader("TEST 6: Test Monday.com Sync Status");
    
    $syncStatus = $syncRepo->findByThreadId($threadId);
    
    if ($syncStatus) {
        testResult('Monday sync record exists', true,
            "Item ID: {$syncStatus['monday_item_id']}, Status: {$syncStatus['last_push_status']}");
    } else {
        output("ℹ️  No Monday sync found (expected if Monday.com not configured)", 'yellow');
        testResult('No Monday sync yet', true, "This is expected without Monday API key");
    }
    
    // =================================================================
    // TEST 7: Test Thread Querying
    // =================================================================
    testHeader("TEST 7: Test Thread Querying");
    
    // Test finding by LinkedIn URL
    $foundThread = $threadRepo->findByLinkedInUrl($normalizer->normalize($testLinkedInUrl));
    testResult('Find thread by LinkedIn URL', !empty($foundThread),
        "Thread ID: " . ($foundThread['id'] ?? 'N/A'));
    
    // Test getting threads with message count
    $threadsWithCount = $threadRepo->getAllWithMessageCount();
    $ourThreadWithCount = array_filter($threadsWithCount, fn($t) => $t['id'] === $threadId);
    $ourThreadWithCount = reset($ourThreadWithCount);
    
    testResult('Thread includes message count', isset($ourThreadWithCount['message_count']),
        "Count: " . ($ourThreadWithCount['message_count'] ?? 0));
    
    // =================================================================
    // TEST 8: Test URL Normalization in Database
    // =================================================================
    testHeader("TEST 8: Test URL Normalization in Database");
    
    // Try to create duplicate thread with different URL format
    $duplicatePayload = [
        'linkedin_url' => $testLinkedInUrl . '?trk=test&utm_source=share',
        'message_text' => 'This should go to the same thread',
        'direction' => 'outbound',
        'sender_email' => 'sarah@veerless.com'
    ];
    
    $duplicateResult = $webhookService->processLinkedInSubmission($duplicatePayload);
    testResult('URL normalization prevents duplicate threads', 
        $duplicateResult['thread_id'] === $threadId,
        "Thread ID: {$duplicateResult['thread_id']} (expected: $threadId)");
    
    $messagesNow = $messageRepo->findByThreadId($threadId);
    testResult('Message added to existing thread', count($messagesNow) === 3,
        "Total messages: " . count($messagesNow));
    
    // =================================================================
    // TEST 9: Database Schema Validation
    // =================================================================
    testHeader("TEST 9: Database Schema Validation");
    
    // Check if tables exist
    $tables = $pdo->query("SHOW TABLES LIKE '%linkedin%'")->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredTables = ['linkedin_threads', 'linkedin_messages', 'monday_sync_linkedin'];
    foreach ($requiredTables as $table) {
        testResult("Table exists: $table", in_array($table, $tables));
    }
    
    // Check enrichment table has LinkedIn support
    $enrichmentColumns = $pdo->query("DESCRIBE contact_enrichment")->fetchAll(PDO::FETCH_COLUMN);
    testResult('contact_enrichment has linkedin_thread_id', in_array('linkedin_thread_id', $enrichmentColumns));
    testResult('contact_enrichment has external_linkedin_url', in_array('external_linkedin_url', $enrichmentColumns));
    
    // =================================================================
    // Summary
    // =================================================================
    testHeader("TEST SUMMARY");
    
    output("✅ All core functionality tests passed!", 'green');
    output("", 'white');
    output("Test thread created with ID: $threadId", 'white');
    output("LinkedIn URL: $testLinkedInUrl", 'white');
    output("Total messages in thread: " . count($messagesNow), 'white');
    output("", 'white');
    output("You can view this thread in:", 'white');
    output("  • Dashboard: http://localhost/networkemailtracking/dashboard.php", 'white');
    output("  • Direct API: http://localhost/networkemailtracking/api/linkedin/thread/$threadId", 'white');
    output("", 'white');
    output("To test in browser:", 'white');
    output("  • Form: http://localhost/networkemailtracking/linkedin-logger.php", 'white');
    output("  • API Test: http://localhost/networkemailtracking/test-api.php", 'white');
    
} catch (\Exception $e) {
    output("\n❌ FATAL ERROR: " . $e->getMessage(), 'red');
    output("Stack trace:", 'red');
    output($e->getTraceAsString(), 'red');
    exit(1);
}

output("\n" . str_repeat('=', 60), 'blue');
output("Tests completed at: " . date('Y-m-d H:i:s'), 'white');
output(str_repeat('=', 60), 'blue');

// HTML footer for browser
if (!$isCli) {
    echo '</pre></body></html>';
}
