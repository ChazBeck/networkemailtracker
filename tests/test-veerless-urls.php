<?php

/**
 * Test Veerless URL shortening with 3-digit codes
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "🧪 Testing Veerless URL Shortening\n";
echo str_repeat('=', 60) . "\n\n";

// Test cases
$testUrls = [
    'https://veerless.com' => 'Should become: veerl.es/home-xxx',
    'https://veerless.com/' => 'Should become: veerl.es/home-xxx',
    'https://www.veerless.com' => 'Should become: veerl.es/home-xxx',
    'https://veerless.com/our-services' => 'Should become: veerl.es/our-services-xyz',
    'https://veerless.com/contact-us' => 'Should become: veerl.es/contact-us-xyz',
    'https://veerless.com/about' => 'Should become: veerl.es/about-xyz',
];

foreach ($testUrls as $url => $expected) {
    echo "📍 Testing: $url\n";
    echo "   Expected: $expected\n";
    
    // Parse URL to generate keyword
    $parsedUrl = parse_url($url);
    $path = trim($parsedUrl['path'] ?? '', '/');
    
    // Generate 3-char tracking code (simulated)
    $trackingCode = substr(md5(microtime()), 0, 3);
    
    // Create keyword
    if (empty($path)) {
        $keyword = 'home-' . $trackingCode;
    } else {
        $keyword = $path . '-' . $trackingCode;
    }
    
    // Call YOURLS API
    $apiUrl = "https://veerl.es/yourls-api.php?signature=e150dd0e84&action=shorturl&url=" 
              . urlencode($url) . "&keyword=" . urlencode($keyword) . "&format=json";
    
    $result = @file_get_contents($apiUrl);
    if ($result) {
        $data = json_decode($result, true);
        $shortUrl = $data['shorturl'] ?? 'ERROR';
        $status = $data['status'] ?? 'ERROR';
        
        if ($status === 'success') {
            echo "   ✅ Result: $shortUrl\n";
        } else {
            echo "   ⚠️  Status: $status\n";
            if (isset($data['message'])) {
                echo "   Message: " . $data['message'] . "\n";
            }
        }
    } else {
        echo "   ❌ API call failed\n";
    }
    
    echo "\n";
}

echo "✨ Test complete!\n";
