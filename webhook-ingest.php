<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Repositories\MondaySyncRepository;
use App\Repositories\EnrichmentRepository;
use App\Services\WebhookService;
use App\Services\MondayService;
use App\Services\PerplexityService;
use App\Services\EnrichmentService;
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
$mondaySyncRepo = new MondaySyncRepository($db);
$enrichmentRepo = new EnrichmentRepository($db);

// Initialize services
$webhookService = new WebhookService($threadRepo, $emailRepo, $logger);
$mondayService = new MondayService($mondaySyncRepo, $threadRepo, $logger);
$perplexityService = new PerplexityService($logger);
$enrichmentService = new EnrichmentService($enrichmentRepo, $threadRepo, $perplexityService, $logger);

// Initialize controller
$webhookController = new WebhookController($webhookService, $logger, $mondayService, $enrichmentService);

// Process webhook
$webhookController->ingest();
