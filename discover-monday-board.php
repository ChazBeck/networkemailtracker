<?php
/**
 * Discover Monday.com Board Structure
 * Fetches column IDs and types from your board
 */

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = $_ENV['MONDAY_API_KEY'];
$boardId = $_ENV['MONDAY_BOARD_ID'];

echo "=== Monday.com Board Discovery ===\n\n";
echo "Board ID: $boardId\n\n";

// GraphQL query to get board structure
$query = 'query {
  boards(ids: ' . $boardId . ') {
    id
    name
    columns {
      id
      title
      type
      settings_str
    }
  }
}';

// Make API request
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

if ($httpCode !== 200) {
    die("❌ API Error: HTTP $httpCode\n$response\n");
}

$data = json_decode($response, true);

if (isset($data['errors'])) {
    die("❌ GraphQL Error: " . json_encode($data['errors'], JSON_PRETTY_PRINT) . "\n");
}

$board = $data['data']['boards'][0] ?? null;

if (!$board) {
    die("❌ Board not found\n");
}

echo "✅ Board Name: {$board['name']}\n\n";
echo "=== Columns ===\n\n";

foreach ($board['columns'] as $column) {
    echo "Column: {$column['title']}\n";
    echo "  ID: {$column['id']}\n";
    echo "  Type: {$column['type']}\n";
    
    if (!empty($column['settings_str'])) {
        $settings = json_decode($column['settings_str'], true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($settings)) {
            echo "  Settings: " . json_encode($settings, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    echo "\n";
}

echo "\n=== Recommended .env Configuration ===\n\n";

$mapping = [];
foreach ($board['columns'] as $column) {
    $title = strtolower($column['title']);
    
    // Map column titles to our app fields
    if (strpos($title, 'email subject') !== false) {
        $mapping['subject'] = $column['id'];
    } elseif (strpos($title, 'email address') !== false) {
        $mapping['email_address'] = $column['id'];
    } elseif (strpos($title, 'person') !== false) {
        $mapping['person'] = $column['id'];
    } elseif ($title === 'status') {
        $mapping['status'] = $column['id'];
    } elseif (strpos($title, 'email body') !== false) {
        $mapping['body'] = $column['id'];
    } elseif (strpos($title, 'first email') !== false) {
        $mapping['first_email'] = $column['id'];
    } elseif ($title === 'date') {
        $mapping['date'] = $column['id'];
    }
}

echo "# Add these to your .env file:\n";
echo "MONDAY_COLUMN_SUBJECT=" . ($mapping['subject'] ?? 'not_found') . "\n";
echo "MONDAY_COLUMN_EMAIL=" . ($mapping['email_address'] ?? 'not_found') . "\n";
echo "MONDAY_COLUMN_PERSON=" . ($mapping['person'] ?? 'not_found') . "\n";
echo "MONDAY_COLUMN_STATUS=" . ($mapping['status'] ?? 'not_found') . "\n";
echo "MONDAY_COLUMN_BODY=" . ($mapping['body'] ?? 'not_found') . "\n";
echo "MONDAY_COLUMN_FIRST_EMAIL=" . ($mapping['first_email'] ?? 'not_found') . "\n";
echo "MONDAY_COLUMN_DATE=" . ($mapping['date'] ?? 'not_found') . "\n";

echo "\n✅ Discovery complete!\n";
