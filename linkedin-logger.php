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

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Email Drafter CSS for Navigation Styling -->
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

    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow max-w-4xl mx-auto">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">📱 LinkedIn Message Logger</h2>
                <p class="text-sm text-gray-500 mt-1">Track your LinkedIn outreach and responses in one place</p>
            </div>
            <div class="p-6">
                <div id="alert" class="hidden mb-4"></div>

                <form id="linkedinForm" class="space-y-6">
                    <!-- LinkedIn URL -->
                    <div>
                        <label for="linkedinUrl" class="block text-sm font-medium text-gray-700 mb-1">
                            LinkedIn Profile URL <span class="text-red-600">*</span>
                        </label>
                        <input 
                            type="url" 
                            id="linkedinUrl" 
                            name="linkedinUrl" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="https://www.linkedin.com/in/username"
                            required
                        >
                        <p class="mt-1 text-sm text-gray-500">
                            Paste the full LinkedIn profile URL (e.g., https://www.linkedin.com/in/john-doe)
                        </p>
                        <div id="urlPreview" class="hidden mt-2 p-2 bg-blue-50 rounded text-sm text-gray-700">
                            <strong class="text-blue-700">Normalized URL:</strong> <span id="normalizedUrl"></span>
                        </div>
                    </div>

                    <!-- Message -->
                    <div>
                        <label for="messageText" class="block text-sm font-medium text-gray-700 mb-1">
                            Message <span class="text-red-600">*</span>
                        </label>
                        <textarea 
                            id="messageText" 
                            name="messageText" 
                            rows="6"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y"
                            placeholder="Paste your LinkedIn message here..."
                            required
                        ></textarea>
                        <p class="mt-1 text-sm text-gray-500">
                            Copy and paste the message you sent or received on LinkedIn
                        </p>
                    </div>

                    <!-- Direction -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Direction <span class="text-red-600">*</span>
                        </label>
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" id="directionSent" name="direction" value="outbound" checked
                                    class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-gray-700">📤 Sent (You sent this message)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" id="directionReceived" name="direction" value="inbound"
                                    class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-gray-700">📥 Received (They replied to you)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Sender Email -->
                    <div>
                        <label for="senderEmail" class="block text-sm font-medium text-gray-700 mb-1">
                            Your Email <span class="text-red-600">*</span>
                        </label>
                        <select 
                            id="senderEmail" 
                            name="senderEmail" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                        >
                            <option value="">-- Select your email --</option>
                            <option value="charlie@veerless.com">Charlie (charlie@veerless.com)</option>
                            <option value="sarah@veerless.com">Sarah (sarah@veerless.com)</option>
                            <option value="networking@veerless.com">Networking (networking@veerless.com)</option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">
                            Select who sent or received this message
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 pt-4 border-t border-gray-200">
                        <button 
                            type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 font-medium transition-colors"
                        >
                            💾 Log Message
                        </button>
                        <button 
                            type="button" 
                            onclick="resetForm()"
                            class="px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 font-medium transition-colors"
                        >
                            🔄 Clear Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
            const bgColor = type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
            alertBox.textContent = message;
            alertBox.className = `p-4 mb-4 rounded border ${bgColor}`;
            setTimeout(() => {
                alertBox.className = 'hidden mb-4';
            }, 5000);
        }

        function resetForm() {
            form.reset();
            urlPreview.classList.add('hidden');
            alertBox.className = 'hidden mb-4';
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
