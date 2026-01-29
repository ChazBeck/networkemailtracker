<?php

/**
 * Front Controller - Main entry point for all HTTP requests
 */

// Global error handler - convert all errors to exceptions
set_error_handler(function($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// Global exception handler - return JSON for API routes
set_exception_handler(function($e) {
    error_log("Uncaught exception: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return JSON error for API routes
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        exit;
    }
    
    // For non-API routes, show error page
    http_response_code(500);
    echo "<h1>500 Internal Server Error</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
});

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\Container;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\ConfigValidator;
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
        'MS_GRAPH_USER_CHARLIE'
    ]);
    
    // Optional configuration (log warnings but don't fail)
    $logger = Logger::getInstance();
    $configValidator->validateOptional([
        'PERPLEXITY_API_KEY',
        'MONDAY_API_KEY',
        'MONDAY_BOARD_ID',
        'YOURLS_API_URL'
    ], function($warning) use ($logger) {
        $logger->warning($warning);
    });
    
} catch (\App\Core\ConfigurationException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    die("Configuration error: " . $e->getMessage());
}

// Initialize container
$container = new Container();

// Register core services
$container->singleton('config', fn() => new Config($_ENV));
$container->singleton('db', fn() => Database::getInstance());
$container->singleton('logger', fn() => Logger::getInstance());

// Register repositories
$container->singleton('threadRepo', fn($c) => new ThreadRepository($c->get('db')));
$container->singleton('emailRepo', fn($c) => new EmailRepository($c->get('db')));
$container->singleton('enrichmentRepo', fn($c) => new EnrichmentRepository($c->get('db')));
$container->singleton('syncRepo', fn($c) => new MondaySyncRepository($c->get('db')));
$container->singleton('linkTrackingRepo', fn($c) => new LinkTrackingRepository($c->get('db'), $c->get('logger')));

// Register services
$container->singleton('webhookService', fn($c) => new WebhookService(
    $c->get('threadRepo'),
    $c->get('emailRepo'),
    $c->get('logger'),
    $c->get('linkTrackingRepo')
));

$container->singleton('perplexityService', fn($c) => new PerplexityService($c->get('logger')));

$container->singleton('enrichmentService', fn($c) => new EnrichmentService(
    $c->get('enrichmentRepo'),
    $c->get('threadRepo'),
    $c->get('perplexityService'),
    $c->get('logger')
));

$container->singleton('mondayService', fn($c) => new MondayService(
    $c->get('syncRepo'),
    $c->get('threadRepo'),
    $c->get('enrichmentRepo'),
    $c->get('emailRepo'),
    $c->get('logger')
));

// Register YOURLS services (optional)
$container->singleton('yourlsClient', function($c) {
    $config = $c->get('config');
    return $config->yourls()->isConfigured() 
        ? new YourlsClient($c->get('logger'))
        : null;
});

$container->singleton('linkTrackingService', function($c) {
    $yourlsClient = $c->get('yourlsClient');
    return $yourlsClient 
        ? new LinkTrackingService($yourlsClient, $c->get('linkTrackingRepo'), $c->get('logger'))
        : null;
});

$container->singleton('outlookDraftService', fn($c) => new OutlookDraftService(
    $c->get('logger'),
    $c->get('db'),
    $c->get('linkTrackingService')
));

// Register controllers
$container->register('webhookController', fn($c) => new WebhookController(
    $c->get('webhookService'),
    $c->get('logger'),
    $c->get('mondayService'),
    $c->get('enrichmentService')
));

$container->register('dashboardController', fn($c) => new DashboardController(
    $c->get('threadRepo'),
    $c->get('emailRepo'),
    $c->get('enrichmentRepo'),
    $c->get('linkTrackingRepo'),
    $c->get('yourlsClient')
));

$container->register('draftController', fn($c) => new DraftController(
    $c->get('outlookDraftService'),
    $c->get('logger')
));

// Get controllers from container
$webhookController = $container->get('webhookController');
$dashboardController = $container->get('dashboardController');
$draftController = $container->get('draftController');

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

// Protected Dashboard Pages (require SSO authentication)
$router->get('/', function($params) {
    // Authentication is handled within dashboard.php itself
    require __DIR__ . '/dashboard.php';
});

$router->get('/dashboard', function($params) {
    // Authentication is handled within dashboard.php itself
    require __DIR__ . '/dashboard.php';
});

// Email Drafter Page (require SSO authentication)
$router->get('/email-drafter', function($params) {
    // Authentication is handled within email-drafter.php itself
    require __DIR__ . '/email-drafter.php';
});

// Dashboard API
$router->get('/api/dashboard', function($params) use ($dashboardController) {
    $dashboardController->getData();
});

// Get single email details
$router->get('/api/emails/{id}', function($params) use ($dashboardController) {
    $dashboardController->getEmail((int)$params['id']);
});

// Contacts page
$router->get('/contacts', function($params) {
    require __DIR__ . '/contacts.php';
});

// Contacts API
$router->get('/api/contacts', function($params) use ($dashboardController) {
    $dashboardController->getContacts();
});

// Update enrichment
$router->put('/api/enrichment/{id}', function($params) use ($dashboardController) {
    $dashboardController->updateEnrichment((int)$params['id']);
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
