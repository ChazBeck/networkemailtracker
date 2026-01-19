<?php

require_once __DIR__ . '/vendor/autoload.php';

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

// Initialize controller
$webhookController = new WebhookController($webhookService, $logger);

// Process webhook
$webhookController->ingest();
