<?php

/**
 * Front Controller - Main entry point for all HTTP requests
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\Database;
use App\Core\Logger;
use App\Core\ConfigValidator;
use App\Core\HttpClient;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EnrichmentRepository;
use App\Repositories\MondaySyncRepository;
use App\Repositories\LinkTrackingRepository;
use App\Services\WebhookService;
use App\Services\EnrichmentService;
use App\Services\PerplexityService;
use App\Services\MondayService;
use App\Services\OutlookDraftService;
use App\Services\YourlsClient;
use App\Services\LinkTrackingService;
use App\Controllers\WebhookController;
use App\Controllers\DashboardController;
use App\Controllers\DraftController;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validate configuration
try {
    $configValidator = new ConfigValidator();
    
    // Required configuration (DB_PASS can be empty for local dev with XAMPP)
    $configValidator->validateRequired([
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'MS_GRAPH_TENANT_ID',
        'MS_GRAPH_CLIENT_ID',
        'MS_GRAPH_CLIENT_SECRET',
        'MS_GRAPH_USER_CHARLIE',
        'MS_GRAPH_USER_MARCY',
        'MS_GRAPH_USER_ANN',
        'MS_GRAPH_USER_KRISTEN',
        'MS_GRAPH_USER_KATIE',
        'MS_GRAPH_USER_TAMEKA'
    ]);
    
    // Optional configuration (log warnings but don't fail)
    $logger = Logger::getInstance();
    $configValidator->validateOptional([
        'PERPLEXITY_API_KEY',
        'MONDAY_API_KEY',
        'MONDAY_BOARD_ID'
    ], function($warning) use ($logger) {
        $logger->warning($warning);
    });
    
} catch (\App\Core\ConfigurationException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    die("Configuration error: " . $e->getMessage());
}

// Get PDO instance
$db = Database::getInstance();
$logger = Logger::getInstance();

// Initialize repositories
$threadRepo = new ThreadRepository($db);
$emailRepo = new EmailRepository($db);
$enrichmentRepo = new EnrichmentRepository($db);
$syncRepo = new MondaySyncRepository($db);
$linkTrackingRepo = new LinkTrackingRepository($db, $logger);

// Initialize services
$webhookService = new WebhookService($threadRepo, $emailRepo, $logger, $linkTrackingRepo);
$perplexityService = new PerplexityService($logger);
$enrichmentService = new EnrichmentService($enrichmentRepo, $threadRepo, $perplexityService, $logger);
$mondayService = new MondayService($syncRepo, $threadRepo, $enrichmentRepo, $emailRepo, $logger);

// Initialize link tracking services (optional, requires YOURLS config)
$linkTrackingService = null;
if (!empty($_ENV['YOURLS_API_URL']) && !empty($_ENV['YOURLS_API_SIGNATURE'])) {
    $yourlsClient = new YourlsClient($logger);
    $linkTrackingService = new LinkTrackingService($yourlsClient, $linkTrackingRepo, $logger);
}

$outlookDraftService = new OutlookDraftService($logger, $linkTrackingService);

// Initialize controllers
$webhookController = new WebhookController($webhookService, $logger, $mondayService, $enrichmentService);
$dashboardController = new DashboardController($threadRepo, $emailRepo, $enrichmentRepo, $linkTrackingRepo);
$draftController = new DraftController($outlookDraftService, $logger);

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

// Webhook endpoints
// Primary email webhook (Microsoft 365 via Power Automate)
$router->post('/api/webhook/email', function($params) use ($webhookController) {
    $webhookController->ingest();
});

// Draft email creation endpoint
$router->post('/api/draft/create', function($params) use ($draftController) {
    $draftController->create();
});

// Future webhook endpoints can be added here:
// $router->post('/api/webhook/slack', function($params) use ($slackWebhookController) {
//     $slackWebhookController->ingest();
// });
// $router->post('/api/webhook/salesforce', function($params) use ($salesforceWebhookController) {
//     $salesforceWebhookController->ingest();
// });

// Dispatch request
$router->dispatch($method, $uri);
