<?php

require_once __DIR__ . "/../vendor/autoload.php";

use App\Core\Database;
use App\Services\MondayService;
use App\Repositories\MondaySyncRepository;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EnrichmentRepository;
use App\Repositories\BizDevSyncRepository;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/..");
$dotenv->load();

echo "=== Testing BizDev Pipeline Sync ===\n\n";

$db = Database::getInstance();
$logger = new Logger("bizdev-test");
$logger->pushHandler(new StreamHandler("php://stdout", Logger::DEBUG));

$syncRepo = new MondaySyncRepository($db);
$threadRepo = new ThreadRepository($db);
$emailRepo = new EmailRepository($db);
$enrichmentRepo = new EnrichmentRepository($db);
$bizDevSyncRepo = new BizDevSyncRepository($db);

$mondayService = new MondayService($syncRepo, $threadRepo, $enrichmentRepo, $emailRepo, $logger, null, null, null, null, null, $bizDevSyncRepo);

$enrichment = $enrichmentRepo->findById(1);
if (!$enrichment) { die("Enrichment not found\n"); }

echo "Contact: {$enrichment["full_name"]}\n";
echo "Company: {$enrichment["company_name"]}\n\n";

if ($enrichment["thread_id"]) {
    $thread = $threadRepo->findById($enrichment["thread_id"]);
    if ($thread) {
        $enrichment["internal_sender_email"] = $thread["internal_sender_email"];
        $enrichment["last_activity_at"] = $thread["last_activity_at"];
    }
}

echo "Syncing...\n";
$result = $mondayService->syncToBizDevPipeline($enrichment);
if ($result["success"]) {
    echo "\nSUCCESS! Item ID: {$result["monday_item_id"]}\n";
    echo "https://veerless.monday.com/boards/7045235564/pulses/{$result["monday_item_id"]}\n";
} else {
    echo "\nFAILED: {$result["error"]}\n";
}
