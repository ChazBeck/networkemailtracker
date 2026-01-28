<?php
/**
 * TEMPORARY TEST SCRIPT - DELETE AFTER REVIEW
 * 
 * Creates a single draft in Charlie's Outlook with all 6 signatures
 * to review how they look in production.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Initialize database
$db = Database::getInstance();

// Get all signatures from database and parse CID references
$users = ['marcy', 'charlie', 'ann', 'kristen', 'katie', 'tameka'];
$allSignatures = [];
$allImageAttachments = [];

foreach ($users as $userName) {
    echo "Fetching signature for {$userName}...\n";
    
    $stmt = $db->prepare("SELECT value FROM sync_state WHERE name = ?");
    $stmt->execute(["signature_{$userName}"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['value']) {
        $signatureHtml = $result['value'];
        
        // Parse CID references
        preg_match_all('/src="cid:([^"]+)"/', $signatureHtml, $matches);
        $cidReferences = $matches[1] ?? [];
        
        // Map CID to image files
        $images = [];
        foreach ($cidReferences as $cid) {
            $imagePath = __DIR__ . "/../public/signatures/{$cid}.png";
            if (!file_exists($imagePath)) {
                $imagePath = __DIR__ . "/../public/signatures/{$cid}.jpg";
            }
            if (file_exists($imagePath)) {
                $images[$cid] = $imagePath;
                $allImageAttachments[$cid] = $imagePath;
            }
        }
        
        $allSignatures[] = [
            'name' => ucfirst($userName),
            'html' => $signatureHtml,
            'images' => $images
        ];
    }
}

// Build email body with all signatures separated by horizontal rules
$emailBody = '<p style="font-family: Aptos, sans-serif; font-size: 11pt;">Below are all 6 team signatures for review:</p>';
$emailBody .= '<br>';

foreach ($allSignatures as $sig) {
    $emailBody .= '<h3 style="font-family: Aptos, sans-serif; color: #333;">' . $sig['name'] . '\'s Signature:</h3>';
    $emailBody .= $sig['html'];
    $emailBody .= '<hr style="border: 2px solid #ddd; margin: 30px 0;">';
    $emailBody .= '<br>';
}

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

// Create draft via Microsoft Graph API
$charlieEmail = $_ENV['MS_GRAPH_USER_CHARLIE'];
$subject = 'REVIEW: All Team Signatures - ' . date('Y-m-d H:i:s');

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

echo "\nCreating draft in Charlie's Outlook...\n";

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

if ($httpCode === 201) {
    $responseData = json_decode($response, true);
    $messageId = $responseData['id'];
    echo "\n✓ Draft created successfully!\n";
    echo "Message ID: {$messageId}\n";
    
    // Now attach all images as inline attachments
    if (!empty($allImageAttachments)) {
        echo "\nAttaching images as inline attachments...\n";
        
        foreach ($allImageAttachments as $cid => $imagePath) {
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
                echo "  ✓ Attached {$cid}\n";
            } else {
                echo "  ✗ Failed to attach {$cid} (HTTP {$attachHttpCode})\n";
            }
        }
    }
    
    echo "\nCheck Charlie's Outlook drafts folder to review all signatures.\n";
    echo "\n⚠️  REMINDER: Delete this script (tests/test-all-signatures.php) after review!\n";
} else {
    echo "\n✗ Error creating draft (HTTP {$httpCode})\n";
    echo $response . "\n";
}
