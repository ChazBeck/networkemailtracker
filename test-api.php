<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $threadRepo = new ThreadRepository($db);
    $emailRepo = new EmailRepository($db);
    
    $threads = $threadRepo->getAllWithEmailCount();
    $recentEmails = $emailRepo->getRecent(50);
    
    echo json_encode([
        'status' => 'success',
        'threads' => $threads,
        'emails' => $recentEmails,
        'stats' => [
            'total_threads' => count($threads),
            'total_emails' => array_sum(array_column($threads, 'email_count'))
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
