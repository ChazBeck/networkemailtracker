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
    error_log("WARNING: Running email-drafter.php without authentication in local development");
}

// Include SSO header with environment-aware loading
$headerPath = __DIR__ . '/../auth/header-with-sso.php';
if (file_exists($headerPath)) {
    require_once $headerPath;
    // Render SSO head and header
    render_sso_head('Email Drafter - Mail Tracker');
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
    <title>Email Drafter - Mail Tracker (Local Dev)</title>
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

<!-- Quill Editor CSS & JS -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<link href="public/css/email-drafter.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<style>
    /* Aptos font definition */
    .ql-font-aptos {
        font-family: Aptos, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    
    /* Set default editor font */
    .ql-editor {
        font-family: Aptos, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        font-size: 11pt;
    }
</style>

<body>

    <!-- Navigation Bar -->
    <nav class="nav">
        <div class="nav-container">
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Email Log</a>
                <a href="email-drafter.php" class="nav-link active">Email Drafter</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Email Creator -->
        <div class="card">
            <div class="card-content">
                <!-- Header with Title -->
                <div>
                    <h2 class="page-title">Email Creator</h2>
                </div>

                <!-- Name Tabs -->
                <div class="name-tabs">
                    <button class="name-tab active">Charlie</button>
                    <button class="name-tab inactive">Marcy</button>
                    <button class="name-tab inactive">Ann</button>
                    <button class="name-tab inactive">Kristen</button>
                    <button class="name-tab inactive">Katie</button>
                    <button class="name-tab inactive">Tameka</button>
                </div>

                <!-- To Field -->
                <div class="form-field">
                    <input type="email" id="toField" placeholder="To" class="form-input">
                </div>

                <!-- Subject Field -->
                <div class="form-field">
                    <input type="text" id="subjectField" placeholder="Subject" class="form-input">
                </div>

                <!-- Rich Text Editor -->
                <div class="editor-container">
                    <div id="editor"></div>
                </div>

                <!-- Send to Outlook Button -->
                <div>
                    <button id="sendToOutlookBtn" class="btn btn-primary">Send to Outlook</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Register Aptos font with Quill
        var Font = Quill.import('formats/font');
        Font.whitelist = ['aptos'];
        Quill.register(Font, true);

        // Initialize Quill editor
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['link'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['clean']
                ]
            },
            placeholder: 'Type your email content here...'
        });

        // Set default font to Aptos 11pt
        quill.format('font', 'aptos');
        quill.root.style.fontFamily = 'Aptos, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        quill.root.style.fontSize = '11pt';

        // Track active user
        let activeUser = 'charlie';

        // Function to get HTML content from editor
        function getEmailHTML() {
            return quill.root.innerHTML;
        }

        // Function to get plain text
        function getEmailText() {
            return quill.getText();
        }

        // Handle name tab clicks
        const nameTabs = document.querySelectorAll('.name-tab');
        nameTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                nameTabs.forEach(t => {
                    t.classList.remove('active');
                    t.classList.add('inactive');
                });
                
                // Add active class to clicked tab
                this.classList.remove('inactive');
                this.classList.add('active');
                
                // Update active user
                activeUser = this.textContent.toLowerCase();
            });
        });

        // Handle Send to Outlook button click
        const sendBtn = document.getElementById('sendToOutlookBtn');
        const toField = document.getElementById('toField');
        const subjectField = document.getElementById('subjectField');

        sendBtn.addEventListener('click', async function() {
            // Validate fields
            const to = toField.value.trim();
            const subject = subjectField.value.trim();
            const body = getEmailHTML();

            if (!to) {
                alert('Please enter a recipient email address');
                toField.focus();
                return;
            }

            if (!subject) {
                alert('Please enter a subject');
                subjectField.focus();
                return;
            }

            if (!body || body === '<p><br></p>') {
                alert('Please enter email content');
                return;
            }

            // Disable button and show loading state
            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';

            try {
                // Send to API (using relative path to work with subdirectory)
                const response = await fetch('api/draft/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user: activeUser,
                        to: to,
                        subject: subject,
                        body: body
                    })
                });

                // Debug: Check response status
                console.log('Response status:', response.status);
                const responseText = await response.text();
                console.log('Response text:', responseText);

                // Try to parse as JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    alert('Server error: ' + responseText.substring(0, 200));
                    console.error('Full response:', responseText);
                    return;
                }

                if (result.success) {
                    alert(result.message || 'Draft created successfully!');
                    
                    // Clear form
                    toField.value = '';
                    subjectField.value = '';
                    quill.setContents([]);
                } else {
                    alert('Error: ' + (result.error || result.message || 'Failed to create draft'));
                }

            } catch (error) {
                alert('Network error: ' + error.message);
                console.error('Error creating draft:', error);
            } finally {
                // Re-enable button
                sendBtn.disabled = false;
                sendBtn.textContent = 'Send to Outlook';
            }
        });
    </script>
</body>
</html>
