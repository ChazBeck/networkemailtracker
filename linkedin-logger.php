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

<!-- Email Drafter CSS for consistent styling -->
<link href="public/css/email-drafter.css" rel="stylesheet">

<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="nav">
        <div class="nav-container">
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="contacts.php" class="nav-link">Contacts</a>
                <a href="email-drafter.php" class="nav-link">Email Drafter</a>
                <a href="linkedin-logger.php" class="nav-link active">LinkedIn Logger</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-content">
                <div id="alert" class="hidden mb-4"></div>

                <!-- Name Tabs -->
                <div class="name-tabs">
                    <button class="name-tab active" data-email="charlie@veerless.com">Charlie</button>
                    <button class="name-tab inactive" data-email="marcy@veerless.com">Marcy</button>
                    <button class="name-tab inactive" data-email="ann@veerless.com">Ann</button>
                    <button class="name-tab inactive" data-email="kristen@veerless.com">Kristen</button>
                    <button class="name-tab inactive" data-email="katie@veerless.com">Katie</button>
                    <button class="name-tab inactive" data-email="tameka@veerless.com">Tameka</button>
                </div>

                <form id="linkedinForm">
                    <!-- LinkedIn URL -->
                    <div class="form-field">
                        <label for="linkedinUrl" style="display: block; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">
                            LinkedIn Profile URL <span style="color: #dc2626;">*</span>
                        </label>
                        <input 
                            type="url" 
                            id="linkedinUrl" 
                            name="linkedinUrl" 
                            class="form-input"
                            placeholder="https://www.linkedin.com/in/username"
                            required
                        >
                        <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #6b7280;">
                            Paste the full LinkedIn profile URL (e.g., https://www.linkedin.com/in/john-doe)
                        </p>
                        <div id="urlPreview" class="hidden" style="margin-top: 0.5rem; padding: 0.5rem; background: #eff6ff; border-radius: 0.25rem; font-size: 0.875rem; color: #1f2937;">
                            <strong style="color: #2563eb;">Normalized URL:</strong> <span id="normalizedUrl"></span>
                        </div>
                    </div>

                    <!-- Message -->
                    <div class="form-field">
                        <label for="messageText" style="display: block; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">
                            Message <span style="color: #dc2626;">*</span>
                        </label>
                        <textarea 
                            id="messageText" 
                            name="messageText" 
                            rows="6"
                            class="form-input"
                            style="resize: vertical; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"
                            placeholder="Paste your LinkedIn message here..."
                            required
                        ></textarea>
                        <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #6b7280;">
                            Copy and paste the message you sent or received on LinkedIn
                        </p>
                    </div>

                    <!-- Direction -->
                    <div class="form-field">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">
                            Direction <span style="color: #dc2626;">*</span>
                        </label>
                        <div style="display: flex; gap: 1.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" id="directionSent" name="direction" value="outbound" checked>
                                <span style="font-size: 0.875rem; color: #374151;">📤 Sent (You sent this message)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" id="directionReceived" name="direction" value="inbound">
                                <span style="font-size: 0.875rem; color: #374151;">📥 Received (They replied to you)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div>
                        <button 
                            type="submit" 
                            class="btn btn-primary"
                        >
                            💾 Log Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Track active user
        let activeUser = 'charlie';
        let activeSenderEmail = 'charlie@veerless.com';

        // Name tab switching
        document.querySelectorAll('.name-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.name-tab').forEach(t => {
                    t.classList.remove('active');
                    t.classList.add('inactive');
                });
                
                // Add active class to clicked tab
                this.classList.remove('inactive');
                this.classList.add('active');
                
                // Update active user
                activeUser = this.textContent.toLowerCase();
                activeSenderEmail = this.dataset.email;
            });
        });

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
                urlPreview.classList.remove('hidden');
            } else {
                urlPreview.classList.add('hidden');
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
                sender_email: activeSenderEmail,
                sent_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
            };

            // Validation
            if (!formData.linkedin_url || !formData.message_text) {
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
            const styles = type === 'success' 
                ? 'padding: 1rem; margin-bottom: 1rem; border-radius: 0.25rem; background-color: #d1fae5; border: 1px solid #6ee7b7; color: #065f46;'
                : 'padding: 1rem; margin-bottom: 1rem; border-radius: 0.25rem; background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;';
            alertBox.textContent = message;
            alertBox.style.cssText = styles;
            alertBox.classList.remove('hidden');
            setTimeout(() => {
                alertBox.classList.add('hidden');
            }, 5000);
        }

        function resetForm() {
            form.reset();
            urlPreview.classList.add('hidden');
            alertBox.classList.add('hidden');
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
