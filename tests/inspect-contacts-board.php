<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['MONDAY_API_KEY'];
$newBoardId = '18397890457'; // The new contacts/business board

echo "=== Inspecting Monday.com Board ===\n\n";

$query = '{
  boards(ids: ' . $newBoardId . ') {
    name
    description
    columns {
      id
      title
      type
      settings_str
    }
  }
}';

$ch = curl_init('https://api.monday.com/v2');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $apiKey,
        'Content-Type: application/json',
        'API-Version: 2024-10'
    ],
    CURLOPT_POSTFIELDS => json_encode(['query' => $query])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n\n";

$data = json_decode($response, true);

if (isset($data['data']['boards'][0])) {
    $board = $data['data']['boards'][0];
    echo "Board Name: " . $board['name'] . "\n";
    echo "Description: " . ($board['description'] ?? 'None') . "\n\n";
    echo "Columns:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($board['columns'] as $column) {
        echo sprintf("  %-30s (ID: %-20s Type: %s)\n", 
            $column['title'], 
            $column['id'], 
            $column['type']
        );
    }
    echo str_repeat("-", 80) . "\n\n";
    
    echo "Column IDs for .env:\n";
    foreach ($board['columns'] as $column) {
        $envKey = 'CONTACTS_BOARD_' . strtoupper(str_replace([' ', '-'], '_', $column['title']));
        echo "$envKey={$column['id']}\n";
    }
} else {
    echo "Error or board not found:\n";
    print_r($data);
}

echo "\n=== Inspection Complete ===\n";
