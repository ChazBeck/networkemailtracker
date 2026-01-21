<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Repositories\ThreadRepository;
use App\Repositories\EnrichmentRepository;
use App\Services\PerplexityService;
use App\Services\EnrichmentService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get email from command line or use default
$testEmail = $argv[1] ?? 'cbeck@beck.com';
$testSubject = $argv[2] ?? 'Business partnership inquiry';

echo "=== Testing Contact Enrichment (Custom) ===\n\n";

// Validate Perplexity API key
if (empty($_ENV['PERPLEXITY_API_KEY'])) {
    die("❌ PERPLEXITY_API_KEY not configured in .env\n");
}

echo "✓ Perplexity API key configured\n";
echo "Model: " . ($_ENV['PERPLEXITY_MODEL'] ?? 'not set') . "\n\n";

// Setup database connection
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'email_tracking_local';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';

try {
    $db = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Setup logger
$logger = new Logger('test_enrichment');
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/test-enrichment.log', Logger::DEBUG));

// Initialize repositories and services
$threadRepo = new ThreadRepository($db);
$enrichmentRepo = new EnrichmentRepository($db);
$perplexityService = new PerplexityService($logger);
$enrichmentService = new EnrichmentService($enrichmentRepo, $threadRepo, $perplexityService, $logger);

echo "Testing enrichment for: $testEmail\n";
echo "Subject: $testSubject\n\n";

// Create a mock thread for testing (not saved to database)
$mockThread = [
    'id' => 99999, // Fake ID that won't exist
    'conversation_id' => 'test-' . uniqid(),
    'subject_normalized' => $testSubject,
    'external_email' => $testEmail,
    'last_activity_at' => date('Y-m-d H:i:s')
];

echo "Calling Perplexity AI...\n";
$startTime = microtime(true);

try {
    // Call Perplexity directly without database
    $result = $perplexityService->enrichContact($testEmail, [
        'subject' => $testSubject,
        'body_preview' => ''
    ]);
    
    $elapsed = round((microtime(true) - $startTime) * 1000);
    echo "Response time: {$elapsed}ms\n\n";
    
    if ($result['success']) {
        echo "✅ Enrichment successful!\n\n";
        
        echo "Enriched Data:\n";
        echo "  First Name: " . ($result['data']['first_name'] ?? 'N/A') . "\n";
        echo "  Last Name: " . ($result['data']['last_name'] ?? 'N/A') . "\n";
        echo "  Full Name: " . ($result['data']['full_name'] ?? 'N/A') . "\n";
        echo "  Company: " . ($result['data']['company_name'] ?? 'N/A') . "\n";
        echo "  Company URL: " . ($result['data']['company_url'] ?? 'N/A') . "\n";
        echo "  LinkedIn: " . ($result['data']['linkedin_url'] ?? 'N/A') . "\n";
        echo "  Job Title: " . ($result['data']['job_title'] ?? 'N/A') . "\n";
        echo "  Confidence: " . ($result['data']['confidence'] ?? 'N/A') . "\n\n";
        
        echo "Raw Prompt:\n";
        echo str_repeat('-', 60) . "\n";
        echo $result['raw_prompt'] . "\n";
        echo str_repeat('-', 60) . "\n\n";
        
        echo "Raw Response:\n";
        echo str_repeat('-', 60) . "\n";
        echo substr($result['raw_response'], 0, 500) . (strlen($result['raw_response']) > 500 ? '...' : '') . "\n";
        echo str_repeat('-', 60) . "\n";
        
    } else {
        echo "❌ Enrichment failed\n";
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
    }
    
} catch (\Exception $e) {
    $elapsed = round((microtime(true) - $startTime) * 1000);
    echo "Response time: {$elapsed}ms\n\n";
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "\nUsage: php test-enrichment-custom.php [email] [subject]\n";
echo "Example: php test-enrichment-custom.php sarah@techcorp.com 'RE: Product demo request'\n";
