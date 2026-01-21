<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enrich') {
    header('Content-Type: application/json');
    
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? 'General inquiry';
    
    if (empty($email)) {
        echo json_encode(['error' => 'Email is required']);
        exit;
    }
    
    if (empty($_ENV['PERPLEXITY_API_KEY'])) {
        echo json_encode(['error' => 'PERPLEXITY_API_KEY not configured']);
        exit;
    }
    
    $apiKey = $_ENV['PERPLEXITY_API_KEY'];
    $model = $_ENV['PERPLEXITY_MODEL'] ?? 'sonar';
    $emailDomain = substr(strrchr($email, "@"), 1);
    
    // Build the prompt
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
    
    // Call Perplexity API
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
    
    $result = [
        'success' => false,
        'elapsed' => $elapsed,
        'httpCode' => $httpCode,
        'request' => $payload,
        'rawResponse' => $response
    ];
    
    if ($curlError) {
        $result['error'] = "cURL Error: $curlError";
        echo json_encode($result);
        exit;
    }
    
    if ($httpCode !== 200) {
        $result['error'] = "HTTP $httpCode";
        $result['apiError'] = json_decode($response, true);
        echo json_encode($result);
        exit;
    }
    
    // Parse response
    $data = json_decode($response, true);
    $result['fullResponse'] = $data;
    
    if (isset($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
        
        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*$/i', '', $content);
        $content = trim($content);
        
        $enrichedData = json_decode($content, true);
        
        if ($enrichedData) {
            $result['success'] = true;
            $result['enrichedData'] = $enrichedData;
        } else {
            $result['error'] = 'Could not parse JSON from response';
            $result['rawContent'] = $content;
        }
    } else {
        $result['error'] = 'No content in response';
    }
    
    echo json_encode($result);
    exit;
}

// Serve HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perplexity Enrichment Tester</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #667eea;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .result-section {
            margin-top: 20px;
        }
        
        .result-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .result-card h3 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge.info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .enrichment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .enrichment-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .enrichment-item strong {
            display: block;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .enrichment-item span {
            color: #333;
            font-size: 16px;
        }
        
        pre {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
        }
        
        .meta-info {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Perplexity Enrichment Tester</h1>
            <p>Test contact enrichment with real email addresses</p>
        </div>
        
        <div class="card">
            <form id="enrichForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="example@company.com" required>
                </div>
                
                <div class="form-group">
                    <label for="subject">Email Subject (optional)</label>
                    <input type="text" id="subject" name="subject" placeholder="e.g., Business partnership inquiry">
                </div>
                
                <button type="submit" class="btn" id="submitBtn">Test Enrichment</button>
            </form>
        </div>
        
        <div id="loading" class="card" style="display: none;">
            <div class="loading">
                <div class="spinner"></div>
                <p>Calling Perplexity AI...</p>
            </div>
        </div>
        
        <div id="results" style="display: none;">
            <div class="card">
                <div class="result-section">
                    <h3>
                        <span id="statusIcon">✅</span>
                        Enrichment Result
                        <span id="statusBadge" class="badge success">Success</span>
                    </h3>
                    
                    <div class="meta-info">
                        <div class="meta-item">
                            <span>⏱️</span>
                            <span id="responseTime">0ms</span>
                        </div>
                        <div class="meta-item">
                            <span>📊</span>
                            <span id="httpStatus">HTTP 200</span>
                        </div>
                        <div class="meta-item">
                            <span>🤖</span>
                            <span>Model: <?php echo $_ENV['PERPLEXITY_MODEL'] ?? 'sonar'; ?></span>
                        </div>
                    </div>
                    
                    <div id="errorMessage" style="display: none;"></div>
                    
                    <div id="enrichmentData" style="display: none;">
                        <div class="enrichment-grid" id="enrichmentGrid"></div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="result-card">
                    <h3>📤 Request Payload</h3>
                    <pre id="requestPayload"></pre>
                </div>
                
                <div class="result-card">
                    <h3>📥 API Response</h3>
                    <pre id="apiResponse"></pre>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const form = document.getElementById('enrichForm');
        const loading = document.getElementById('loading');
        const results = document.getElementById('results');
        const submitBtn = document.getElementById('submitBtn');
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const subject = document.getElementById('subject').value || 'General inquiry';
            
            // Show loading
            loading.style.display = 'block';
            results.style.display = 'none';
            submitBtn.disabled = true;
            
            // Make request
            try {
                const formData = new FormData();
                formData.append('action', 'enrich');
                formData.append('email', email);
                formData.append('subject', subject);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                // Hide loading
                loading.style.display = 'none';
                results.style.display = 'block';
                submitBtn.disabled = false;
                
                // Update meta info
                document.getElementById('responseTime').textContent = `${data.elapsed}ms`;
                document.getElementById('httpStatus').textContent = `HTTP ${data.httpCode}`;
                
                // Show results
                if (data.success) {
                    document.getElementById('statusIcon').textContent = '✅';
                    document.getElementById('statusBadge').className = 'badge success';
                    document.getElementById('statusBadge').textContent = 'Success';
                    document.getElementById('errorMessage').style.display = 'none';
                    document.getElementById('enrichmentData').style.display = 'block';
                    
                    // Display enriched data
                    const grid = document.getElementById('enrichmentGrid');
                    grid.innerHTML = '';
                    
                    const fields = [
                        { key: 'full_name', label: 'Full Name' },
                        { key: 'first_name', label: 'First Name' },
                        { key: 'last_name', label: 'Last Name' },
                        { key: 'company_name', label: 'Company' },
                        { key: 'job_title', label: 'Job Title' },
                        { key: 'company_url', label: 'Company URL' },
                        { key: 'linkedin_url', label: 'LinkedIn' },
                        { key: 'confidence', label: 'Confidence' }
                    ];
                    
                    fields.forEach(field => {
                        const value = data.enrichedData[field.key];
                        const div = document.createElement('div');
                        div.className = 'enrichment-item';
                        div.innerHTML = `
                            <strong>${field.label}</strong>
                            <span>${value || 'N/A'}</span>
                        `;
                        grid.appendChild(div);
                    });
                } else {
                    document.getElementById('statusIcon').textContent = '❌';
                    document.getElementById('statusBadge').className = 'badge error';
                    document.getElementById('statusBadge').textContent = 'Failed';
                    document.getElementById('enrichmentData').style.display = 'none';
                    
                    const errorDiv = document.getElementById('errorMessage');
                    errorDiv.style.display = 'block';
                    errorDiv.className = 'error-message';
                    errorDiv.textContent = data.error || 'Unknown error occurred';
                }
                
                // Show request and response
                document.getElementById('requestPayload').textContent = JSON.stringify(data.request, null, 2);
                document.getElementById('apiResponse').textContent = JSON.stringify(data.fullResponse || data.apiError || data.rawResponse, null, 2);
                
            } catch (error) {
                loading.style.display = 'none';
                results.style.display = 'block';
                submitBtn.disabled = false;
                
                document.getElementById('statusIcon').textContent = '❌';
                document.getElementById('statusBadge').className = 'badge error';
                document.getElementById('statusBadge').textContent = 'Error';
                document.getElementById('enrichmentData').style.display = 'none';
                
                const errorDiv = document.getElementById('errorMessage');
                errorDiv.style.display = 'block';
                errorDiv.className = 'error-message';
                errorDiv.textContent = `Request failed: ${error.message}`;
            }
        });
    </script>
</body>
</html>
