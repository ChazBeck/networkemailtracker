<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validate Perplexity API key
if (empty($_ENV['PERPLEXITY_API_KEY'])) {
    die("❌ PERPLEXITY_API_KEY not configured in .env\n");
}

$apiKey = $_ENV['PERPLEXITY_API_KEY'];
$model = $_ENV['PERPLEXITY_MODEL'] ?? 'sonar';

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║     PERPLEXITY ENRICHMENT TESTER (Interactive)          ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Model: $model\n";
echo "Type 'exit' or 'quit' to stop\n";
echo str_repeat("=", 60) . "\n\n";

while (true) {
    // Get email from user
    echo "Enter email address to enrich: ";
    $email = trim(fgets(STDIN));
    
    if (strtolower($email) === 'exit' || strtolower($email) === 'quit' || empty($email)) {
        echo "\nGoodbye!\n";
        break;
    }
    
    // Optional subject
    echo "Subject (press Enter to skip): ";
    $subject = trim(fgets(STDIN));
    if (empty($subject)) {
        $subject = "General inquiry";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n";
    
    // Build the prompt
    $emailDomain = substr(strrchr($email, "@"), 1);
    
    $prompt = "Research and provide information about the person with email address: {$email}\n\n";
    $prompt .= "Email subject: {$subject}\n";
    $prompt .= "\nProvide the following information in JSON format:\n";
    $prompt .= "{\n";
    $prompt .= '  "first_name": "string or null",'."\n";
    $prompt .= '  "last_name": "string or null",'."\n";
    $prompt .= '  "full_name": "string or null",'."\n";
    $prompt .= '  "company_name": "string or null (company associated with '.$emailDomain.')",'."\n";
    $prompt .= '  "company_url": "string or null (official website)",'."\n";
    $prompt .= '  "linkedin_url": "string or null (LinkedIn profile URL)",'."\n";
    $prompt .= '  "job_title": "string or null",'."\n";
    $prompt .= '  "confidence": 0.0-1.0 (how confident you are in this data)'."\n";
    $prompt .= "}\n\n";
    $prompt .= "Example response:\n";
    $prompt .= "{\n";
    $prompt .= '  "first_name": "Chris",'."\n";
    $prompt .= '  "last_name": "Beck",'."\n";
    $prompt .= '  "full_name": "Chris Beck",'."\n";
    $prompt .= '  "company_name": "Beck Industries",'."\n";
    $prompt .= '  "company_url": "https://beck.com",'."\n";
    $prompt .= '  "linkedin_url": "https://linkedin.com/in/chrisbeck",'."\n";
    $prompt .= '  "job_title": "CEO",'."\n";
    $prompt .= '  "confidence": 0.85'."\n";
    $prompt .= "}\n\n";
    $prompt .= "Only return the JSON object, no additional text. If you cannot find information, use null for that field.";
    
    // Build API payload
    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a contact enrichment assistant. Always respond with valid JSON only, no markdown formatting or additional text.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.1,
        'max_tokens' => 500
    ];
    
    echo "📤 REQUEST TO PERPLEXITY:\n";
    echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";
    
    // Call Perplexity API
    echo "⏳ Calling Perplexity AI...\n";
    $startTime = microtime(true);
    
    $ch = curl_init('https://api.perplexity.ai/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $elapsed = round((microtime(true) - $startTime) * 1000);
    
    echo "⏱️  Response time: {$elapsed}ms\n";
    echo "📊 HTTP Status: $httpCode\n\n";
    
    if ($curlError) {
        echo "❌ cURL Error: $curlError\n\n";
        continue;
    }
    
    if ($httpCode !== 200) {
        echo "❌ API Error:\n";
        echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT) . "\n\n";
        continue;
    }
    
    // Parse response
    $data = json_decode($response, true);
    
    echo "📥 FULL RESPONSE FROM PERPLEXITY:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    
    if (isset($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
        
        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*$/i', '', $content);
        $content = trim($content);
        
        echo "📋 EXTRACTED ENRICHMENT DATA:\n";
        $enrichedData = json_decode($content, true);
        
        if ($enrichedData) {
            echo json_encode($enrichedData, JSON_PRETTY_PRINT) . "\n";
            
            echo "\n✅ ENRICHMENT SUMMARY:\n";
            echo "  Name: " . ($enrichedData['full_name'] ?? 'N/A') . "\n";
            echo "  Company: " . ($enrichedData['company_name'] ?? 'N/A') . "\n";
            echo "  Title: " . ($enrichedData['job_title'] ?? 'N/A') . "\n";
            echo "  LinkedIn: " . ($enrichedData['linkedin_url'] ?? 'N/A') . "\n";
            echo "  Confidence: " . ($enrichedData['confidence'] ?? 'N/A') . "\n";
        } else {
            echo "❌ Could not parse JSON from content:\n$content\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}
