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

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "=== Testing Monday.com API Connection ===\n\n";

$apiKey = $_ENV['MONDAY_API_KEY'] ?? null;
$boardId = $_ENV['MONDAY_BOARD_ID'] ?? null;

if (!$apiKey) {
    die("❌ MONDAY_API_KEY not set in .env\n");
}

if (!$boardId) {
    die("❌ MONDAY_BOARD_ID not set in .env\n");
}

echo "API Key: " . substr($apiKey, 0, 20) . "...\n";
echo "Board ID: $boardId\n\n";

// Test query - just get board name
$query = 'query { boards(ids: ' . $boardId . ') { name } }';

$ch = curl_init('https://api.monday.com/v2');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $apiKey,
        'Content-Type: application/json',
        'API-Version: 2024-10'
    ],
    CURLOPT_POSTFIELDS => json_encode(['query' => $query])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";

if ($httpCode !== 200) {
    echo "❌ API request failed\n";
    echo "Response: $response\n";
    exit(1);
}

$data = json_decode($response, true);

if (isset($data['errors'])) {
    echo "❌ API returned errors:\n";
    print_r($data['errors']);
    exit(1);
}

if (isset($data['data']['boards'][0]['name'])) {
    echo "✅ API connection successful!\n";
    echo "Board name: " . $data['data']['boards'][0]['name'] . "\n";
} else {
    echo "❌ Unexpected response format:\n";
    print_r($data);
    exit(1);
}

echo "\n=== Testing thread sync ===\n\n";

// Test syncing an existing thread
$db = Database::getInstance();
$logger = new Logger('test');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$syncRepo = new MondaySyncRepository($db);
$threadRepo = new ThreadRepository($db);
$emailRepo = new EmailRepository($db);
$enrichmentRepo = new EnrichmentRepository($db);
$mondayService = new MondayService($syncRepo, $threadRepo, $enrichmentRepo, $emailRepo, $logger);

// Get a thread with external email
$stmt = $db->query("SELECT * FROM threads WHERE external_email IS NOT NULL AND external_email != '' LIMIT 1");
$thread = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$thread) {
    echo "❌ No threads with external emails found to test\n";
    exit(1);
}

echo "Testing sync with thread #{$thread['id']} ({$thread['external_email']})\n\n";

try {
    $result = $mondayService->syncThread($thread);
    
    if ($result['success']) {
        echo "✅ Sync successful!\n";
        echo "Monday Item ID: {$result['monday_item_id']}\n";
        echo "Action: {$result['action']}\n";
    } else {
        echo "❌ Sync failed: {$result['error']}\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
}

echo "\n=== Test Complete ===\n";
