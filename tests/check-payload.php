<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = App\Core\Database::getInstance();

echo "=== Raw Webhook Payload ===\n\n";

$email = $db->query('SELECT raw_payload, from_email, to_json FROM emails ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

if ($email) {
    echo "From Email (parsed): " . ($email['from_email'] ?: 'NULL') . "\n";
    echo "To JSON (parsed): " . ($email['to_json'] ?: 'NULL') . "\n\n";
    
    if ($email['raw_payload']) {
        echo "Raw Payload:\n";
        $payload = json_decode($email['raw_payload'], true);
        echo json_encode($payload, JSON_PRETTY_PRINT);
    } else {
        echo "No raw payload stored\n";
    }
} else {
    echo "No emails found in database\n";
}
