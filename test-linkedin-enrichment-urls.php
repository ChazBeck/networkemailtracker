<?php
/**
 * Test LinkedIn Enrichment for specific URLs
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\PerplexityService;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$logger = new MonologLogger('test');
$logger->pushHandler(new StreamHandler('php://stdout', MonologLogger::DEBUG));
$perplexityService = new PerplexityService($logger);

$urls = [
    'https://www.linkedin.com/in/charlesrbeck/',
    'https://www.linkedin.com/in/grantcgibson/'
];

foreach ($urls as $url) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "Testing: {$url}\n";
    echo str_repeat("=", 80) . "\n\n";
    
    try {
        $result = $perplexityService->enrichContactFromLinkedIn($url);
        
        echo "✅ SUCCESS\n\n";
        echo "JSON Response:\n";
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Test completed\n";
echo str_repeat("=", 80) . "\n";
