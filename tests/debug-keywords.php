<?php

/**
 * Debug keyword generation
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "🔍 Debug Keyword Generation\n";
echo str_repeat('=', 60) . "\n\n";

$testUrl = "https://veerless.com/our-services";
$parsedUrl = parse_url($testUrl);
$path = trim($parsedUrl['path'] ?? '', '/');

echo "Original URL: $testUrl\n";
echo "Parsed path: '$path'\n\n";

// Simulate tracking code
$trackingCode = 'xyz';

// Create keyword
$keyword = $path . '-' . $trackingCode;
echo "1. Initial keyword: '$keyword'\n";

// Sanitize - step by step
$keyword = str_replace('/', '-', $keyword);
echo "2. After slash replace: '$keyword'\n";

$keyword = strtolower($keyword);
echo "3. After lowercase: '$keyword'\n";

$keyword = preg_replace('/[^a-z0-9-]/', '', $keyword);
echo "4. After regex cleanup: '$keyword'\n\n";

// Test the regex pattern directly
$testStrings = [
    'our-services-xyz',
    'contact-us-abc',
    'home-123',
    'test--double-xyz'
];

echo "Testing regex pattern '/[^a-z0-9-]/':\n";
foreach ($testStrings as $test) {
    $result = preg_replace('/[^a-z0-9-]/', '', $test);
    echo "  '$test' → '$result'\n";
}

// Now test actual YOURLS call
echo "\n🌐 Testing YOURLS API with keyword: '$keyword'\n";
$apiUrl = "https://veerl.es/yourls-api.php?signature=e150dd0e84&action=shorturl&url=" 
          . urlencode($testUrl) . "&keyword=" . urlencode($keyword) . "&format=json";

echo "API URL: $apiUrl\n\n";

$result = @file_get_contents($apiUrl);
if ($result) {
    $data = json_decode($result, true);
    echo "Response:\n";
    print_r($data);
} else {
    echo "❌ API call failed\n";
}
