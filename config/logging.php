<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;

$logLevel = match($_ENV['LOG_LEVEL'] ?? 'debug') {
    'debug' => Logger::DEBUG,
    'info' => Logger::INFO,
    'warning' => Logger::WARNING,
    'error' => Logger::ERROR,
    default => Logger::INFO,
};

$logger = new Logger('email_tracking');

// Rotating file handler
$logPath = __DIR__ . '/../' . ($_ENV['LOG_PATH'] ?? 'logs/app.log');
$handler = new RotatingFileHandler($logPath, 14, $logLevel);
$handler->setFormatter(new JsonFormatter());
$logger->pushHandler($handler);

// Console output for development
if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
    $consoleHandler = new StreamHandler('php://stdout', $logLevel);
    $logger->pushHandler($consoleHandler);
}

return $logger;
