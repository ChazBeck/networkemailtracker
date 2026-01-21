<?php

/**
 * Front Controller - Main entry point for all HTTP requests
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EnrichmentRepository;
use App\Repositories\MondaySyncRepository;
use App\Services\WebhookService;
use App\Services\EnrichmentService;
use App\Services\PerplexityService;
use App\Services\MondayService;
use App\Controllers\WebhookController;
use App\Controllers\DashboardController;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get PDO instance
$db = Database::getInstance();
$logger = Logger::getInstance();

// Initialize repositories
$threadRepo = new ThreadRepository($db);
$emailRepo = new EmailRepository($db);
$enrichmentRepo = new EnrichmentRepository($db);
$syncRepo = new MondaySyncRepository($db);

// Initialize services
$webhookService = new WebhookService($threadRepo, $emailRepo, $logger);
$perplexityService = new PerplexityService($logger);
$enrichmentService = new EnrichmentService($enrichmentRepo, $threadRepo, $perplexityService, $logger);
$mondayService = new MondayService($syncRepo, $threadRepo, $enrichmentRepo, $emailRepo, $logger);

// Initialize controllers
$webhookController = new WebhookController($webhookService, $logger, $mondayService, $enrichmentService);
$dashboardController = new DashboardController($threadRepo, $emailRepo, $enrichmentRepo);

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip base path if running in subdirectory
// Try to detect base path from SCRIPT_NAME
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptName !== '/' && strpos($uri, $scriptName) === 0) {
    $uri = substr($uri, strlen($scriptName));
}
if (empty($uri)) {
    $uri = '/';
}

// Initialize router
$router = new Router();

// Health check endpoint
$router->get('/health', function($params) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'message' => 'Email Tracking API is running',
        'timestamp' => date('c')
    ]);
});

// Dashboard
$router->get('/', function($params) {
    require __DIR__ . '/dashboard.php';
});

$router->get('/dashboard', function($params) {
    require __DIR__ . '/dashboard.php';
});

// Dashboard API
$router->get('/api/dashboard', function($params) use ($dashboardController) {
    $dashboardController->getData();
});

// Webhook endpoint
$router->post('/api/webhook/email', function($params) use ($webhookController) {
    $webhookController->ingest();
});

// Dispatch request
$router->dispatch($method, $uri);
