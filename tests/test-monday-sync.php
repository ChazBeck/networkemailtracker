<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\MondayService;
use App\Repositories\MondaySyncRepository;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EnrichmentRepository;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Setup
$db = Database::getInstance();
$logger = new Logger('monday-test');
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/monday-sync.log', Logger::DEBUG));

$syncRepo = new MondaySyncRepository($db);
$threadRepo = new ThreadRepository($db);
$emailRepo = new EmailRepository($db);
$enrichmentRepo = new EnrichmentRepository($db);
$mondayService = new MondayService($syncRepo, $threadRepo, $enrichmentRepo, $emailRepo, $logger);

echo "\n=== Testing Monday.com Sync ===\n\n";

// Test 1: Get threads that need syncing
echo "1. Getting threads that need syncing...\n";
$threads = $mondayService->syncPendingThreads();
echo "   Found " . count($threads) . " threads to sync\n";

// Test 2: Get sync status
echo "\n2. Getting sync status...\n";
$status = $mondayService->getSyncStatus();
echo "   Total synced records: " . count($status) . "\n";

if (!empty($status)) {
    echo "\n   Recent syncs:\n";
    foreach (array_slice($status, 0, 5) as $sync) {
        echo sprintf(
            "   - Thread #%d: %s (Monday ID: %s) - Status: %s\n",
            $sync['thread_id'],
            $sync['external_email'] ?? 'N/A',
            $sync['item_id'] ?? 'N/A',
            $sync['last_push_status'] ?? 'unknown'
        );
    }
}

// Test 3: Try to manually sync one thread
echo "\n3. Testing manual sync of a single thread...\n";
$stmt = $db->query("
    SELECT * FROM threads 
    WHERE external_email IS NOT NULL 
    LIMIT 1
");
$testThread = $stmt->fetch(PDO::FETCH_ASSOC);

if ($testThread) {
    echo "   Syncing thread: {$testThread['external_email']}\n";
    $result = $mondayService->syncThread($testThread);
    
    if ($result['success'] ?? false) {
        echo "   ✓ Sync successful!\n";
        echo "   Monday Item ID: " . ($result['monday_item_id'] ?? 'N/A') . "\n";
        echo "   Action: " . ($result['action'] ?? 'unknown') . "\n";
        if (isset($result['monday_item_id'])) {
            echo "   View at: https://chuckbeck.monday.com/boards/{$_ENV['MONDAY_BOARD_ID']}/pulses/{$result['monday_item_id']}\n";
        }
    } else {
        echo "   ✗ Sync failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "   No threads found to test\n";
}

echo "\n=== Test Complete ===\n\n";
