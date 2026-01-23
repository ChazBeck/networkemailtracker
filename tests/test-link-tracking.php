<?php

/**
 * Test Link Tracking Service
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

echo "🧪 Testing Link Tracking Service\n";
echo str_repeat('=', 50) . "\n\n";

// Initialize services
$linkTrackingRepo = new LinkTrackingRepository($db, $logger);
$yourlsClient = new YourlsClient($logger);
$linkTrackingService = new LinkTrackingService($yourlsClient, $linkTrackingRepo, $logger);

// Test HTML with various link types
$testHtml = <<<HTML
<html>
<body>
    <p>Hello! Here are some links:</p>
    
    <p><a href="https://veerless.com/our-services">Our Services</a> - should become veerl.es/our-services-abc123</p>
    
    <p><a href="https://www.veerless.com/about-us">About Us</a> - should become veerl.es/about-us-xyz789</p>
    
    <p><a href="https://espn.com/nfl">ESPN NFL</a> - should become veerl.es/random123</p>
    
    <p><a href="mailto:test@example.com">Email me</a> - should stay as mailto</p>
    
    <p><a href="tel:+1234567890">Call us</a> - should stay as tel</p>
    
    <p><a href="#anchor">Anchor link</a> - should stay as anchor</p>
</body>
</html>
HTML;

echo "📄 Original HTML:\n";
echo str_repeat('-', 50) . "\n";
echo $testHtml . "\n\n";

// Process links
echo "🔄 Processing links...\n\n";
$processedHtml = $linkTrackingService->processLinks($testHtml, null);

echo "✅ Processed HTML:\n";
echo str_repeat('-', 50) . "\n";
echo $processedHtml . "\n\n";

// Show processed links
$processedLinks = $linkTrackingService->getProcessedLinks();
echo "📊 Processed Links:\n";
echo str_repeat('-', 50) . "\n";
foreach ($processedLinks as $index => $link) {
    echo ($index + 1) . ". " . $link['original_url'] . "\n";
    echo "   → " . $link['shorturl'] . "\n";
    echo "   Keyword: " . $link['keyword'] . "\n";
    if (isset($link['tracking_code'])) {
        echo "   Tracking Code: " . $link['tracking_code'] . "\n";
    }
    echo "\n";
}

echo "✨ Test complete!\n";

