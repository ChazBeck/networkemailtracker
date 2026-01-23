<?php

/**
 * Test link tracking with protocol-less URLs
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\LinkTrackingRepository;
use App\Services\YourlsClient;
use App\Services\LinkTrackingService;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$logger = Logger::getInstance();
$db = Database::getInstance();

echo "🧪 Testing Link Tracking with Protocol-less URLs\n";
echo str_repeat('=', 60) . "\n\n";

// Initialize services
$linkTrackingRepo = new LinkTrackingRepository($db, $logger);
$yourlsClient = new YourlsClient($logger);
$linkTrackingService = new LinkTrackingService($yourlsClient, $linkTrackingRepo, $logger);

// Test HTML from Quill editor (how it might look)
$testHtml = <<<HTML
<html>
<body>
    <p>Check out these sites:</p>
    <p><a href="espn.com">ESPN</a></p>
    <p><a href="www.cnn.com">CNN</a></p>
    <p><a href="veerless.com/about">About Us</a></p>
    <p><a href="https://google.com">Google</a></p>
</body>
</html>
HTML;

echo "📄 Original HTML from Quill:\n";
echo str_repeat('-', 60) . "\n";
echo $testHtml . "\n\n";

// Process links
echo "🔄 Processing links...\n\n";
try {
    $processedHtml = $linkTrackingService->processLinks($testHtml, null);
    
    echo "✅ Processed HTML:\n";
    echo str_repeat('-', 60) . "\n";
    echo $processedHtml . "\n\n";
    
    // Show processed links
    $processedLinks = $linkTrackingService->getProcessedLinks();
    echo "📊 Processed Links:\n";
    echo str_repeat('-', 60) . "\n";
    if (empty($processedLinks)) {
        echo "❌ No links were processed!\n";
    } else {
        foreach ($processedLinks as $index => $link) {
            echo ($index + 1) . ". Short URL: " . $link['shorturl'] . "\n";
            echo "   Keyword: " . $link['keyword'] . "\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "✨ Test complete!\n";
