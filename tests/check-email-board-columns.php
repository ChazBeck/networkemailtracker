<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['MONDAY_API_KEY'];
$boardId = '18311868929'; // Email tracking board

$query = 'query {
  boards(ids: [' . $boardId . ']) {
    name
    columns {
      id
      title
      type
    }
  }
}';

$ch = curl_init('https://api.monday.com/v2');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: ' . $apiKey,
    'API-Version: 2024-10'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['data']['boards'][0])) {
    $board = $data['data']['boards'][0];
    echo "Board: {$board['name']}\n\n";
    echo "Columns:\n";
    foreach ($board['columns'] as $col) {
        echo "  {$col['title']}: {$col['id']} ({$col['type']})\n";
        if ($col['type'] === 'multiple-person' || $col['type'] === 'people') {
            echo "    ^^ THIS IS THE PEOPLE COLUMN!\n";
        }
    }
} else {
    echo "Error: " . json_encode($data);
}
