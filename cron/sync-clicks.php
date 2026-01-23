<?php

/**
 * Sync click counts from YOURLS to local database
 * Run this as a cron job every 5-15 minutes
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Core\Database;
use App\Services\YourlsClient;

$db = Database::getInstance();
$yourls = new YourlsClient();

echo "🔄 Syncing click counts from YOURLS\n";
echo str_repeat('=', 60) . "\n\n";

// Get all tracked links
$stmt = $db->query("SELECT id, yourls_keyword, clicks FROM link_tracking");
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
$errors = 0;

foreach ($links as $link) {
    try {
        // Get stats from YOURLS
        $stats = $yourls->getStats($link['yourls_keyword']);
        
        if ($stats && isset($stats['clicks'])) {
            $newClicks = (int)$stats['clicks'];
            
            // Only update if clicks changed
            if ($newClicks != $link['clicks']) {
                $stmt = $db->prepare("UPDATE link_tracking SET clicks = ? WHERE id = ?");
                $stmt->execute([$newClicks, $link['id']]);
                
                echo "✅ Updated {$link['yourls_keyword']}: {$link['clicks']} → $newClicks clicks\n";
                $updated++;
            }
        }
    } catch (Exception $e) {
        echo "❌ Error syncing {$link['yourls_keyword']}: {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "✅ Sync complete: $updated updated, $errors errors\n";
