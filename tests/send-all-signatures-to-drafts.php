<?php
/**
 * TEMPORARY TEST SCRIPT - DELETE AFTER REVIEW
 * 
 * Creates 6 separate drafts in Charlie's Outlook, one for each team member's signature
 * so you can review each signature individually.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Initialize database
$db = Database::getInstance();

// Get Microsoft Graph access token
$tokenUrl = 'https://login.microsoftonline.com/' . $_ENV['MS_GRAPH_TENANT_ID'] . '/oauth2/v2.0/token';
$tokenData = [
    'client_id' => $_ENV['MS_GRAPH_CLIENT_ID'],
    'scope' => 'https://graph.microsoft.com/.default',
    'client_secret' => $_ENV['MS_GRAPH_CLIENT_SECRET'],
    'grant_type' => 'client_credentials'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
$tokenResponse = curl_exec($ch);
curl_close($ch);

$tokenJson = json_decode($tokenResponse, true);
if (!isset($tokenJson['access_token'])) {
    die("Failed to get access token\n");
}

$accessToken = $tokenJson['access_token'];
$charlieEmail = $_ENV['MS_GRAPH_USER_CHARLIE'];

// Process each user's signature
$users = ['marcy', 'charlie', 'ann', 'kristen', 'katie', 'tameka'];

foreach ($users as $userName) {
    echo "\n=== Processing {$userName}'s signature ===\n";
    
    // Get signature from database
    $stmt = $db->prepare("SELECT value FROM sync_state WHERE name = ?");
    $stmt->execute(["signature_{$userName}"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result || !$result['value']) {
        echo "  ✗ No signature found\n";
        continue;
    }
    
    $signatureHtml = $result['value'];
    
    // Parse CID references
    preg_match_all('/src="cid:([^"]+)"/', $signatureHtml, $matches);
    $cidReferences = $matches[1] ?? [];
    
    // Build email body
    $emailBody = '<p style="font-family: Aptos, sans-serif; font-size: 11pt;">Test email with ' . ucfirst($userName) . '\'s signature:</p>';
    $emailBody .= '<br><br>';
    $emailBody .= $signatureHtml;
    
    // Create draft
    $subject = 'SIGNATURE TEST: ' . ucfirst($userName) . ' - ' . date('Y-m-d H:i');
    
    $draftData = [
        'subject' => $subject,
        'body' => [
            'contentType' => 'HTML',
            'content' => $emailBody
        ],
        'toRecipients' => [[
            'emailAddress' => [
                'address' => $charlieEmail
            ]
        ]]
    ];
    
    $graphUrl = "https://graph.microsoft.com/v1.0/users/{$charlieEmail}/messages";
    $ch = curl_init($graphUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($draftData));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201) {
        echo "  ✗ Failed to create draft (HTTP {$httpCode})\n";
        continue;
    }
    
    $responseData = json_decode($response, true);
    $messageId = $responseData['id'];
    echo "  ✓ Draft created (ID: {$messageId})\n";
    
    // Attach images as inline attachments
    if (!empty($cidReferences)) {
        echo "  Attaching images...\n";
        
        foreach ($cidReferences as $cid) {
            $imagePath = __DIR__ . "/../public/signatures/{$cid}.png";
            if (!file_exists($imagePath)) {
                $imagePath = __DIR__ . "/../public/signatures/{$cid}.jpg";
            }
            
            if (!file_exists($imagePath)) {
                echo "    ✗ Image not found: {$cid}\n";
                continue;
            }
            
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath);
            
            $attachmentData = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => basename($imagePath),
                'contentType' => $mimeType,
                'contentBytes' => $imageData,
                'contentId' => $cid,
                'isInline' => true
            ];
            
            $attachUrl = "https://graph.microsoft.com/v1.0/users/{$charlieEmail}/messages/{$messageId}/attachments";
            $ch = curl_init($attachUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($attachmentData));
            
            $attachResponse = curl_exec($ch);
            $attachHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($attachHttpCode === 201) {
                echo "    ✓ Attached {$cid}\n";
            } else {
                echo "    ✗ Failed to attach {$cid} (HTTP {$attachHttpCode})\n";
            }
        }
    }
    
    echo "  ✓ Complete\n";
}

echo "\n=== All Done! ===\n";
echo "Check Charlie's Outlook drafts folder - you should see 6 separate draft emails.\n";
echo "\n⚠️  REMINDER: Delete both test scripts after review:\n";
echo "  - tests/test-all-signatures.php\n";
echo "  - tests/send-all-signatures-to-drafts.php\n";
