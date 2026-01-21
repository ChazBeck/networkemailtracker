<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\EnrichmentRepository;
use App\Repositories\ThreadRepository;
use App\Services\PerplexityService;
use App\Services\EnrichmentService;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "=== Testing Contact Enrichment ===\n\n";

// Check API key
if (empty($_ENV['PERPLEXITY_API_KEY'])) {
    die("❌ PERPLEXITY_API_KEY not set in .env\n");
}

echo "✓ Perplexity API key configured\n";
echo "Model: " . ($_ENV['PERPLEXITY_MODEL'] ?? 'default') . "\n\n";

// Initialize services
$db = Database::getInstance();
$logger = Logger::getInstance();
$enrichmentRepo = new EnrichmentRepository($db);
$threadRepo = new ThreadRepository($db);
$perplexityService = new PerplexityService($logger);
$enrichmentService = new EnrichmentService($enrichmentRepo, $threadRepo, $perplexityService, $logger);

// Get a thread with external email
$stmt = $db->query("
    SELECT * FROM threads 
    WHERE external_email IS NOT NULL 
    AND external_email != ''
    LIMIT 1
");
$thread = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$thread) {
    die("❌ No threads with external emails found to test\n");
}

echo "Testing enrichment for thread #{$thread['id']}\n";
echo "Email: {$thread['external_email']}\n";
echo "Subject: {$thread['subject_normalized']}\n\n";

// Check if already enriched
$existing = $enrichmentRepo->findByThreadId($thread['id']);
if ($existing) {
    echo "⚠️  Thread already has enrichment record (status: {$existing['enrichment_status']})\n";
    echo "Force re-enriching...\n\n";
}

// Enrich the contact
echo "Calling Perplexity AI...\n";
$startTime = microtime(true);

try {
    $result = $enrichmentService->enrichThread($thread, true); // Force refresh
    
    $duration = round((microtime(true) - $startTime) * 1000);
    echo "Response time: {$duration}ms\n\n";
    
    if ($result['success']) {
        echo "✅ Enrichment successful!\n\n";
        
        $data = $result['data'] ?? [];
        echo "Enriched Data:\n";
        echo "  First Name: " . ($data['first_name'] ?? 'N/A') . "\n";
        echo "  Last Name: " . ($data['last_name'] ?? 'N/A') . "\n";
        echo "  Full Name: " . ($data['full_name'] ?? 'N/A') . "\n";
        echo "  Company: " . ($data['company_name'] ?? 'N/A') . "\n";
        echo "  Company URL: " . ($data['company_url'] ?? 'N/A') . "\n";
        echo "  LinkedIn: " . ($data['linkedin_url'] ?? 'N/A') . "\n";
        echo "  Job Title: " . ($data['job_title'] ?? 'N/A') . "\n";
        echo "  Confidence: " . ($data['confidence'] ?? 'N/A') . "\n";
        
        // Check if saved to database
        $saved = $enrichmentRepo->findByThreadId($thread['id']);
        if ($saved) {
            echo "\n✅ Enrichment saved to database (ID: {$saved['id']})\n";
        }
    } else {
        echo "❌ Enrichment failed\n";
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
