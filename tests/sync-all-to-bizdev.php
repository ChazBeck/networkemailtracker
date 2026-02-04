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

echo "=== Syncing All Enriched Contacts to BizDev Pipeline ===\n\n";

$db = Database::getInstance();
$logger = new Logger("bizdev-bulk");
$logger->pushHandler(new StreamHandler(__DIR__ . "/../logs/bizdev-bulk-sync.log", Logger::DEBUG));

$syncRepo = new MondaySyncRepository($db);
$threadRepo = new ThreadRepository($db);
$emailRepo = new EmailRepository($db);
$enrichmentRepo = new EnrichmentRepository($db);
$bizDevSyncRepo = new BizDevSyncRepository($db);

$mondayService = new MondayService($syncRepo, $threadRepo, $enrichmentRepo, $emailRepo, $logger, null, null, null, null, null, $bizDevSyncRepo);

$stmt = $db->query("
    SELECT * FROM contact_enrichment 
    WHERE enrichment_status = 'complete'
    AND (first_name IS NOT NULL OR company_name IS NOT NULL)
    ORDER BY enriched_at DESC
");

$enrichments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($enrichments);

if ($total === 0) {
    echo "No enriched contacts found.\n";
    exit(0);
}

echo "Found {$total} enriched contacts to sync.\n\n";

$success = 0;
$failed = 0;
$skipped = 0;
$errors = [];

foreach ($enrichments as $index => $enrichment) {
    $num = $index + 1;
    $name = $enrichment["full_name"] ?: $enrichment["external_email"] ?: $enrichment["external_linkedin_url"];
    $company = $enrichment["company_name"] ?: "Unknown";
    
    echo "[{$num}/{$total}] {$company} - {$name}... ";
    
    try {
        if ($enrichment["thread_id"]) {
            $thread = $threadRepo->findById($enrichment["thread_id"]);
            if ($thread) {
                $enrichment["internal_sender_email"] = $thread["internal_sender_email"];
                $enrichment["last_activity_at"] = $thread["last_activity_at"];
            }
        } elseif ($enrichment["linkedin_thread_id"]) {
            $stmt = $db->prepare("SELECT * FROM linkedin_threads WHERE id = ?");
            $stmt->execute([$enrichment["linkedin_thread_id"]]);
            $linkedInThread = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($linkedInThread) {
                $enrichment["internal_sender_email"] = $linkedInThread["internal_sender_email"];
                $enrichment["last_activity_at"] = $linkedInThread["last_activity_at"];
            }
        }
        
        $result = $mondayService->syncToBizDevPipeline($enrichment);
        
        if ($result["success"]) {
            if ($result["action"] === "already_synced") {
                echo "SKIPPED (already synced)\n";
                $skipped++;
            } else {
                echo "SUCCESS (ID: {$result["monday_item_id"]})\n";
                $success++;
            }
        } else {
            echo "FAILED: {$result["error"]}\n";
            $failed++;
            $errors[] = [
                "enrichment_id" => $enrichment["id"],
                "name" => $name,
                "error" => $result["error"]
            ];
        }
        
        usleep(100000);
    } catch (Exception $e) {
        echo "EXCEPTION: {$e->getMessage()}\n";
        $failed++;
        $errors[] = [
            "enrichment_id" => $enrichment["id"],
            "name" => $name,
            "error" => $e->getMessage()
        ];
    }
}

echo "\n=== Summary ===\n";
echo "Total: {$total}\n";
echo "Success: {$success}\n";
echo "Skipped: {$skipped}\n";
echo "Failed: {$failed}\n";

if (!empty($errors)) {
    echo "\n=== Errors ===\n";
    foreach ($errors as $error) {
        echo "  Enrichment #{$error["enrichment_id"]} ({$error["name"]}): {$error["error"]}\n";
    }
}

echo "\nDone!\n";
