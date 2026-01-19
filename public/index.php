<?php

/**
 * Front Controller - Main entry point for all HTTP requests
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Core\Database;
use App\Core\Logger;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Initialize router
$router = new Router();

// TODO: Register routes here
// Example:
// $router->get('/api/threads', function($params) {
//     // Handle request
//     header('Content-Type: application/json');
//     echo json_encode(['message' => 'List threads']);
// });

// For now, just have a basic health check
$router->get('/health', function($params) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'message' => 'Email Tracking API is running',
        'timestamp' => date('c')
    ]);
});

// Dispatch request
$router->dispatch($method, $uri);
