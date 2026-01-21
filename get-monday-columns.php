<?php
/**
 * Fetch Monday.com board columns and their IDs
 * Run this to discover column IDs for your board
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = $_ENV['MONDAY_API_KEY'] ?? '';
$boardId = $_ENV['MONDAY_BOARD_ID'] ?? '';

if (empty($apiKey) || empty($boardId)) {
    die("❌ MONDAY_API_KEY and MONDAY_BOARD_ID must be set in .env\n");
}

echo "🔍 Fetching columns from Monday.com board: $boardId\n\n";

// GraphQL query to get board columns
$query = 'query {
  boards(ids: ' . $boardId . ') {
    name
    columns {
      id
      title
      type
      settings_str
    }
  }
}';

// Call Monday API
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
    die("❌ Monday API error: HTTP $httpCode - $response\n");
}

$data = json_decode($response, true);

if (isset($data['errors'])) {
    die("❌ Monday GraphQL error: " . json_encode($data['errors'], JSON_PRETTY_PRINT) . "\n");
}

$board = $data['data']['boards'][0] ?? null;

if (!$board) {
    die("❌ Board not found\n");
}

echo "📋 Board: " . $board['name'] . "\n";
echo str_repeat("=", 80) . "\n\n";

echo "Available Columns:\n";
echo str_repeat("-", 80) . "\n";

foreach ($board['columns'] as $column) {
    printf("%-40s %-20s %s\n", 
        $column['title'], 
        "({$column['type']})", 
        $column['id']
    );
}

echo "\n" . str_repeat("=", 80) . "\n\n";

// Auto-suggest mappings based on column titles
echo "💡 Suggested .env configuration:\n";
echo str_repeat("-", 80) . "\n";

$mappings = [
    'Subject' => 'MONDAY_COLUMN_SUBJECT',
    'Email' => 'MONDAY_COLUMN_EMAIL',
    'Body' => 'MONDAY_COLUMN_BODY',
    'First Email' => 'MONDAY_COLUMN_FIRST_EMAIL',
    'First Email Date' => 'MONDAY_COLUMN_FIRST_EMAIL',
    'Status' => 'MONDAY_COLUMN_STATUS',
    'Date' => 'MONDAY_COLUMN_DATE',
    'Last Activity' => 'MONDAY_COLUMN_DATE',
    'First Name' => 'MONDAY_COLUMN_FIRST_NAME',
    'Last Name' => 'MONDAY_COLUMN_LAST_NAME',
    'Company' => 'MONDAY_COLUMN_COMPANY',
    'Job Title' => 'MONDAY_COLUMN_JOB_TITLE',
    'Message ID' => 'MONDAY_COLUMN_MESSAGE_ID',
    'Conversation ID' => 'MONDAY_COLUMN_CONVERSATION_ID',
];

$found = [];

foreach ($board['columns'] as $column) {
    $title = $column['title'];
    
    // Try exact match
    if (isset($mappings[$title])) {
        $envVar = $mappings[$title];
        $found[$envVar] = $column['id'];
        echo "{$envVar}={$column['id']}\n";
    } else {
        // Try partial match
        foreach ($mappings as $searchTerm => $envVar) {
            if (stripos($title, $searchTerm) !== false && !isset($found[$envVar])) {
                $found[$envVar] = $column['id'];
                echo "{$envVar}={$column['id']}  # Matched: {$title}\n";
                break;
            }
        }
    }
}

// Show missing mappings
echo "\n⚠️  Columns not auto-matched (add these manually if needed):\n";
$missing = array_diff($mappings, array_keys($found));
foreach ($missing as $envVar) {
    echo "# {$envVar}=\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "✅ Copy the suggested configuration above to your .env file\n";
