<?php
// Load environment and dependencies
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Check environment for production safety
$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';

// Initialize SSO authentication
$authInitPath = __DIR__ . '/includes/auth-init.php';
if (file_exists($authInitPath)) {
    require_once $authInitPath;
} elseif ($isProduction) {
    die('<h1>SECURITY ERROR</h1><p>Authentication system not configured. Contact administrator.</p>');
} else {
    error_log("WARNING: Running linkedin-logger.php without authentication in local development");
}

// Include SSO header with environment-aware loading
$headerPath = __DIR__ . '/../auth/header-with-sso.php';
if (file_exists($headerPath)) {
    require_once $headerPath;
    // Render SSO head and header
    render_sso_head('LinkedIn Logger - Mail Tracker');
    render_sso_header();
} elseif ($isProduction) {
    die('<h1>SECURITY ERROR</h1><p>SSO header system not configured. Contact administrator.</p>');
} else {
    // Fallback header for local development
    error_log("WARNING: Using fallback header in local development");
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Logger - Mail Tracker (Local Dev)</title>
    <style>
        .dev-warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 10px;
            margin: 10px;
            border-radius: 5px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="dev-warning">
        ⚠️ <strong>Development Mode</strong> - Running without SSO authentication
    </div>
<?php
}
?>

<link href="public/css/email-drafter.css" rel="stylesheet">

<style>
    .linkedin-form-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 30px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .form-header {
        margin-bottom: 30px;
        border-bottom: 2px solid #0077B5;
        padding-bottom: 15px;
    }
    
    .form-header h1 {
        color: #0077B5;
        margin: 0 0 10px 0;
        font-size: 28px;
    }
    
    .form-header p {
        color: #666;
        margin: 0;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
    }
    
    .form-group label .required {
        color: #dc3545;
    }
    
    .form-group input[type="text"],
    .form-group input[type="url"],
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }
    
    .form-group textarea {
        min-height: 150px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        resize: vertical;
    }
    
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #0077B5;
        box-shadow: 0 0 0 3px rgba(0,119,181,0.1);
    }
    
    .url-preview {
        margin-top: 8px;
        padding: 8px 12px;
        background: #f8f9fa;
        border-radius: 4px;
        font-size: 13px;
        color: #666;
        display: none;
    }
    
    .url-preview.show {
        display: block;
    }
    
    .url-preview strong {
        color: #0077B5;
    }
    
    .radio-group {
        display: flex;
        gap: 20px;
    }
    
    .radio-option {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .radio-option input[type="radio"] {
        width: auto;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-primary {
        background: #0077B5;
        color: white;
    }
    
    .btn-primary:hover {
        background: #005a8c;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
    }
    
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        display: none;
    }
    
    .alert.show {
        display: block;
    }
    
    .alert-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }
    
    .alert-error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }
    
    .help-text {
        font-size: 13px;
        color: #6c757d;
        margin-top: 5px;
    }
</style>

<body>
    <!-- Navigation Bar -->
    <nav class="nav">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="dashboard.php">Mail Tracker</a>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="email-drafter.php">Email Drafter</a></li>
                <li><a href="linkedin-logger.php" class="active">LinkedIn Logger</a></li>
                <li><a href="contacts.php">Contacts</a></li>
            </ul>
        </div>
    </nav>

    <div class="linkedin-form-container">
        <div class="form-header">
            <h1>📱 LinkedIn Message Logger</h1>
            <p>Track your LinkedIn outreach and responses in one place</p>
        </div>

        <div id="alert" class="alert"></div>

        <form id="linkedinForm">
            <div class="form-group">
                <label for="linkedinUrl">
                    LinkedIn Profile URL <span class="required">*</span>
                </label>
                <input 
                    type="url" 
                    id="linkedinUrl" 
                    name="linkedinUrl" 
                    placeholder="https://www.linkedin.com/in/username"
                    required
                >
                <div class="help-text">
                    Paste the full LinkedIn profile URL (e.g., https://www.linkedin.com/in/john-doe)
                </div>
                <div id="urlPreview" class="url-preview">
                    <strong>Normalized URL:</strong> <span id="normalizedUrl"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="messageText">
                    Message <span class="required">*</span>
                </label>
                <textarea 
                    id="messageText" 
                    name="messageText" 
                    placeholder="Paste your LinkedIn message here..."
                    required
                ></textarea>
                <div class="help-text">
                    Copy and paste the message you sent or received on LinkedIn
                </div>
            </div>

            <div class="form-group">
                <label>
                    Direction <span class="required">*</span>
                </label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="directionSent" name="direction" value="outbound" checked>
                        <label for="directionSent" style="margin: 0; font-weight: normal;">📤 Sent (You sent this message)</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="directionReceived" name="direction" value="inbound">
                        <label for="directionReceived" style="margin: 0; font-weight: normal;">📥 Received (They replied to you)</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="senderEmail">
                    Your Email <span class="required">*</span>
                </label>
                <select id="senderEmail" name="senderEmail" required>
                    <option value="">-- Select your email --</option>
                    <option value="charlie@veerless.com">Charlie (charlie@veerless.com)</option>
                    <option value="sarah@veerless.com">Sarah (sarah@veerless.com)</option>
                    <option value="networking@veerless.com">Networking (networking@veerless.com)</option>
                </select>
                <div class="help-text">
                    Select who sent or received this message
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    💾 Log Message
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                    🔄 Clear Form
                </button>
            </div>
        </form>
    </div>

    <script>
        // URL normalization preview
        const linkedinUrlInput = document.getElementById('linkedinUrl');
        const urlPreview = document.getElementById('urlPreview');
        const normalizedUrlSpan = document.getElementById('normalizedUrl');

        linkedinUrlInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url) {
                // Simple client-side normalization preview
                let normalized = url.toLowerCase();
                normalized = normalized.replace(/^https?:\/\//i, 'https://');
                normalized = normalized.replace(/linkedin\.com\/([^/]+)\/(.+?)(\?.*)?$/, 'linkedin.com/$1/$2');
                normalized = normalized.replace(/\/+$/, '');
                
                if (!normalized.startsWith('https://')) {
                    normalized = 'https://' + normalized;
                }
                
                normalizedUrlSpan.textContent = normalized;
                urlPreview.classList.add('show');
            } else {
                urlPreview.classList.remove('show');
            }
        });

        // Form submission
        const form = document.getElementById('linkedinForm');
        const alertBox = document.getElementById('alert');

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = {
                linkedin_url: document.getElementById('linkedinUrl').value.trim(),
                message_text: document.getElementById('messageText').value.trim(),
                direction: document.querySelector('input[name="direction"]:checked').value,
                sender_email: document.getElementById('senderEmail').value,
                sent_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
            };

            // Validation
            if (!formData.linkedin_url || !formData.message_text || !formData.sender_email) {
                showAlert('Please fill in all required fields', 'error');
                return;
            }

            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Logging...';

            try {
                const response = await fetch('api/linkedin/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                const text = await response.text();
                console.log('Response body:', text);
                
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', e);
                    showAlert('❌ Server error: Invalid response format', 'error');
                    return;
                }

                if (response.ok && (result.message_id || result.success !== false)) {
                    showAlert('✅ Message logged successfully!', 'success');
                    setTimeout(() => {
                        resetForm();
                    }, 2000);
                } else {
                    showAlert('❌ Error: ' + (result.error || 'Failed to log message'), 'error');
                    console.error('API Error:', result);
                }
            } catch (error) {
                console.error('Network error:', error);
                showAlert('❌ Network error: ' + error.message, 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });

        function showAlert(message, type) {
            alertBox.textContent = message;
            alertBox.className = `alert alert-${type} show`;
            setTimeout(() => {
                alertBox.classList.remove('show');
            }, 5000);
        }

        function resetForm() {
            form.reset();
            urlPreview.classList.remove('show');
            alertBox.classList.remove('show');
        }
    </script>

<?php
// Include SSO footer if available
$footerPath = __DIR__ . '/../auth/footer-with-sso.php';
if (file_exists($footerPath)) {
    require_once $footerPath;
    render_sso_footer();
} else {
    ?>
</body>
</html>
<?php
}
?>
