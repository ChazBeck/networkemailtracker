<?php

/**
 * Simple test of link tracking without DB/logging
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "🧪 Testing YOURLS API Direct\n";
echo str_repeat('=', 50) . "\n\n";

// Test 1: Veerless URL with custom keyword
$testUrl1 = "https://veerless.com/our-services";
$keyword1 = "our-services-" . substr(md5(microtime()), 0, 6);
$apiUrl1 = "https://veerl.es/yourls-api.php?signature=e150dd0e84&action=shorturl&url=" . urlencode($testUrl1) . "&keyword=" . urlencode($keyword1) . "&format=json";

echo "1️⃣ Testing Veerless URL:\n";
echo "   Original: $testUrl1\n";
echo "   Keyword: $keyword1\n";

$result1 = @file_get_contents($apiUrl1);
if ($result1) {
    $data1 = json_decode($result1, true);
    echo "   Short URL: " . ($data1['shorturl'] ?? 'ERROR') . "\n";
    echo "   Status: " . ($data1['status'] ?? 'ERROR') . "\n\n";
} else {
    echo "   ❌ Failed to connect to YOURLS API\n\n";
}

// Test 2: External URL without keyword
$testUrl2 = "https://example.com/page-" . time();
$apiUrl2 = "https://veerl.es/yourls-api.php?signature=e150dd0e84&action=shorturl&url=" . urlencode($testUrl2) . "&format=json";

echo "2️⃣ Testing External URL:\n";
echo "   Original: $testUrl2\n";

$result2 = @file_get_contents($apiUrl2);
if ($result2) {
    $data2 = json_decode($result2, true);
    echo "   Short URL: " . ($data2['shorturl'] ?? 'ERROR') . "\n";
    echo "   Keyword: " . ($data2['url']['keyword'] ?? 'N/A') . "\n";
    echo "   Status: " . ($data2['status'] ?? 'ERROR') . "\n\n";
} else {
    echo "   ❌ Failed to connect to YOURLS API\n\n";
}

// Test 3: HTML Link replacement (simplified)
echo "3️⃣ Testing HTML Link Replacement:\n";
$testHtml = '<p>Visit <a href="https://veerless.com/contact">Contact Us</a> or <a href="https://google.com">Google</a></p>';
echo "   Original: " . htmlspecialchars($testHtml) . "\n";

// Simple regex replacement for demonstration
$pattern = '/<a\s+href="([^"]+)"/i';
preg_match_all($pattern, $testHtml, $matches);

if (!empty($matches[1])) {
    foreach ($matches[1] as $url) {
        echo "   Found link: $url\n";
    }
}

echo "\n✨ API Test complete!\n";
