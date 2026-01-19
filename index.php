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
use App\Services\WebhookService;
use App\Controllers\WebhookController;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get PDO instance
$db = Database::getInstance();
$logger = Logger::getInstance();

// Initialize repositories
$threadRepo = new ThreadRepository($db);
$emailRepo = new EmailRepository($db);

// Initialize services
$webhookService = new WebhookService($threadRepo, $emailRepo, $logger);

// Initialize controllers
$webhookController = new WebhookController($webhookService, $logger);

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

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

// Webhook endpoint
$router->post('/api/webhook/email', function($params) use ($webhookController) {
    $webhookController->ingest();
});

// Dispatch request
$router->dispatch($method, $uri);
