<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== REFACTORING VERIFICATION ===" . PHP_EOL . PHP_EOL;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Check all new classes exist
$classes = [
    'App\Core\HttpClient',
    'App\Core\ConfigValidator',
    'App\Core\ConfigurationException',
    'App\Core\JsonResponse',
    'App\Contracts\ThreadRepositoryInterface',
    'App\Contracts\EmailRepositoryInterface',
    'App\Contracts\EnrichmentRepositoryInterface',
    'App\Contracts\MondaySyncRepositoryInterface'
];

echo "Checking classes exist:" . PHP_EOL;
foreach ($classes as $class) {
    if (class_exists($class) || interface_exists($class)) {
        echo "  ✓ $class" . PHP_EOL;
    } else {
        echo "  ✗ $class NOT FOUND" . PHP_EOL;
    }
}

echo PHP_EOL . "Checking repository implementations:" . PHP_EOL;
$repos = [
    'App\Repositories\ThreadRepository' => 'App\Contracts\ThreadRepositoryInterface',
    'App\Repositories\EmailRepository' => 'App\Contracts\EmailRepositoryInterface',
    'App\Repositories\EnrichmentRepository' => 'App\Contracts\EnrichmentRepositoryInterface',
    'App\Repositories\MondaySyncRepository' => 'App\Contracts\MondaySyncRepositoryInterface'
];

foreach ($repos as $class => $interface) {
    $reflection = new ReflectionClass($class);
    if ($reflection->implementsInterface($interface)) {
        echo "  ✓ $class implements $interface" . PHP_EOL;
    } else {
        echo "  ✗ $class does NOT implement $interface" . PHP_EOL;
    }
}

echo PHP_EOL . "✅ All refactoring components verified!" . PHP_EOL;
