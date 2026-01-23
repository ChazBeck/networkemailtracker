<?php

/**
 * Test URL normalization
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "🧪 Testing URL Normalization\n";
echo str_repeat('=', 60) . "\n\n";

$testUrls = [
    'espn.com' => 'https://espn.com',
    'www.espn.com' => 'https://www.espn.com',
    'veerless.com/about' => 'https://veerless.com/about',
    'http://example.com' => 'http://example.com',
    'https://google.com' => 'https://google.com',
    'ftp://files.example.com' => 'ftp://files.example.com',
];

foreach ($testUrls as $input => $expected) {
    // Simulate normalization
    $url = trim($input);
    if (preg_match('/^[a-z]+:\/\//i', $url)) {
        $normalized = $url;
    } else {
        $normalized = 'https://' . $url;
    }
    
    $status = $normalized === $expected ? '✅' : '❌';
    echo "$status Input: '$input'\n";
    echo "   Expected: $expected\n";
    echo "   Got:      $normalized\n\n";
}

// Test with YOURLS API
echo "\n🌐 Testing with YOURLS API:\n";
echo str_repeat('=', 60) . "\n\n";

$testCases = [
    ['url' => 'espn.com', 'normalized' => 'https://espn.com'],
    ['url' => 'veerless.com/contact', 'normalized' => 'https://veerless.com/contact'],
];

foreach ($testCases as $test) {
    echo "Testing: {$test['url']}\n";
    echo "Normalized: {$test['normalized']}\n";
    
    $keyword = 'test-' . substr(md5(microtime()), 0, 6);
    $apiUrl = "https://veerl.es/yourls-api.php?signature=e150dd0e84&action=shorturl&url=" 
              . urlencode($test['normalized']) . "&keyword=" . urlencode($keyword) . "&format=json";
    
    $result = @file_get_contents($apiUrl);
    if ($result) {
        $data = json_decode($result, true);
        if ($data['status'] === 'success') {
            echo "✅ Success: {$data['shorturl']}\n";
        } else {
            echo "❌ Error: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "❌ API call failed\n";
    }
    echo "\n";
}

echo "✨ Test complete!\n";
