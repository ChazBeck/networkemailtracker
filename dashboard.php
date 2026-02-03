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
    error_log("WARNING: Running dashboard.php without authentication in local development");
}

// Include SSO header with environment-aware loading
$headerPath = __DIR__ . '/../auth/header-with-sso.php';
if (file_exists($headerPath)) {
    require_once $headerPath;
    // Render SSO head and header
    render_sso_head('Email Tracking Dashboard - Mail Tracker');
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
    <title>Dashboard - Mail Tracker (Local Dev)</title>
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
<?php } ?>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Email Drafter CSS for Navigation Styling -->
<link href="public/css/email-drafter.css" rel="stylesheet">

<body class="bg-gray-50">

    <!-- Navigation Bar -->
    <nav class="nav">
        <div class="nav-container">
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link active">Dashboard</a>
                <a href="contacts.php" class="nav-link">Contacts</a>
                <a href="email-drafter.php" class="nav-link">Email Drafter</a>
                <a href="linkedin-logger.php" class="nav-link">LinkedIn Logger</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">

        <!-- Threads with Emails -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-900">Threads & Messages</h2>
                <div class="flex gap-4 items-center">
                    <select id="channelFilter" onchange="filterThreads()" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="all">All Channels</option>
                        <option value="email">📧 Email Only</option>
                        <option value="linkedin">💼 LinkedIn Only</option>
                    </select>
                </div>
            </div>
            <div id="threads-container" class="p-6">
                <div class="text-center text-gray-500">Loading...</div>
            </div>
        </div>
    </div>

    <!-- Email Detail Modal -->
    <div id="email-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Email Details</h3>
                <button onclick="closeEmailModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="email-modal-content" class="text-center text-gray-500">
                Loading...
            </div>
        </div>
    </div>

    <script>
        async function loadDashboard() {
            try {
                // Use relative path for API call
                const response = await fetch('/networkemailtracking/api/dashboard');
                
                console.log('API Response status:', response.status);
                console.log('API Response headers:', response.headers);
                
                if (!response.ok) {
                    const text = await response.text();
                    console.error('API Error:', text);
                    throw new Error(`HTTP ${response.status}: ${text}`);
                }
                
                const data = await response.json();
                console.log('API Data:', data);
                
                // Store data globally for filtering
                allThreadsData = data;
                
                const container = document.getElementById('threads-container');
                
                if (data.threads.length === 0) {
                    container.innerHTML = '<div class="text-center text-gray-500">No threads yet</div>';
                    return;
                }
                
                // Initial render with all threads
                renderThreads(data.threads, data);
                
            } catch (error) {
                console.error('Error loading dashboard:', error);
                document.getElementById('threads-container').innerHTML = 
                    `<div class="text-center text-red-500">
                        <p>Failed to load dashboard data</p>
                        <p class="text-sm mt-2">${error.message}</p>
                        <p class="text-xs mt-2">Check browser console for details</p>
                    </div>`;
            }
        }
        
        function getLinkBadge(emailId, linksByEmail) {
            if (!linksByEmail || !linksByEmail[emailId]) {
                return '';
            }
            
            const linkData = linksByEmail[emailId];
            const hasClicks = linkData.clicks > 0;
            
            if (hasClicks) {
                return `<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800" title="${linkData.clicks} clicks on ${linkData.count} link(s)">
                    🔗 ${linkData.count} link${linkData.count !== 1 ? 's' : ''} · ${linkData.clicks} click${linkData.clicks !== 1 ? 's' : ''}
                </span>`;
            } else {
                return `<span class="px-2 py-1 text-xs rounded-full bg-orange-100 text-orange-800" title="${linkData.count} tracked link(s), not clicked yet">
                    🔗 ${linkData.count} link${linkData.count !== 1 ? 's' : ''} · no clicks
                </span>`;
            }
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return date.toLocaleString();
        }
        
        function truncate(str, length) {
            if (!str) return 'N/A';
            return str.length > length ? str.substring(0, length) + '...' : str;
        }
        
        // View email in modal
        async function viewEmail(emailId) {
            const modal = document.getElementById('email-modal');
            const content = document.getElementById('email-modal-content');
            
            // Show modal with loading state
            modal.classList.remove('hidden');
            content.innerHTML = '<div class="text-center text-gray-500">Loading email...</div>';
            
            try {
                const response = await fetch(`/networkemailtracking/api/emails/${emailId}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const data = await response.json();
                const email = data.email;
                const links = data.links || [];
                
                // Format recipients
                const toRecipients = email.to_json ? JSON.parse(email.to_json).map(r => r.emailAddress?.address || r).join(', ') : 'N/A';
                const ccRecipients = email.cc_json ? JSON.parse(email.cc_json).map(r => r.emailAddress?.address || r).join(', ') : '';
                const bccRecipients = email.bcc_json ? JSON.parse(email.bcc_json).map(r => r.emailAddress?.address || r).join(', ') : '';
                
                content.innerHTML = `
                    <div class="space-y-4 text-left">
                        <!-- Email Header -->
                        <div class="bg-gray-50 p-4 rounded-lg space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-1 text-xs rounded-full ${
                                    email.direction === 'inbound' ? 'bg-blue-100 text-blue-800' :
                                    email.direction === 'outbound' ? 'bg-purple-100 text-purple-800' :
                                    'bg-gray-100 text-gray-800'
                                }">${email.direction}</span>
                                <span class="text-xs text-gray-500">${formatDate(email.received_at || email.sent_at)}</span>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-gray-700">From:</span>
                                <span class="text-sm text-gray-900">${email.from_email || 'N/A'}</span>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-gray-700">To:</span>
                                <span class="text-sm text-gray-900">${toRecipients}</span>
                            </div>
                            ${ccRecipients ? `
                                <div>
                                    <span class="text-sm font-semibold text-gray-700">CC:</span>
                                    <span class="text-sm text-gray-900">${ccRecipients}</span>
                                </div>
                            ` : ''}
                            ${bccRecipients ? `
                                <div>
                                    <span class="text-sm font-semibold text-gray-700">BCC:</span>
                                    <span class="text-sm text-gray-900">${bccRecipients}</span>
                                </div>
                            ` : ''}
                            <div>
                                <span class="text-sm font-semibold text-gray-700">Subject:</span>
                                <span class="text-sm text-gray-900">${email.subject || 'No Subject'}</span>
                            </div>
                        </div>
                        
                        <!-- Tracked Links -->
                        ${links.length > 0 ? `
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-900 mb-2">🔗 Tracked Links (${links.length})</h4>
                                <div class="space-y-2">
                                    ${links.map(link => `
                                        <div class="bg-white p-2 rounded text-xs">
                                            <div class="font-mono text-blue-600 truncate" title="${link.original_url}">
                                                ${link.original_url}
                                            </div>
                                            <div class="text-gray-500 mt-1">
                                                Short URL: <a href="${link.yourls_short_url}" target="_blank" class="text-blue-600 hover:underline">${link.yourls_short_url}</a>
                                                · Clicks: <span class="font-semibold ${link.clicks > 0 ? 'text-green-600' : 'text-gray-400'}">${link.clicks}</span>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        <!-- Email Body -->
                        <div class="border-t pt-4">
                            <h4 class="text-sm font-semibold text-gray-900 mb-2">Email Content:</h4>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 max-h-96 overflow-y-auto">
                                <div class="whitespace-pre-wrap text-sm text-gray-800">${email.body_text || 'No body content available'}</div>
                            </div>
                        </div>
                    </div>
                `;
                
            } catch (error) {
                console.error('Error loading email:', error);
                content.innerHTML = `
                    <div class="text-center text-red-500">
                        <p>Failed to load email</p>
                        <p class="text-sm mt-2">${error.message}</p>
                    </div>
                `;
            }
        }
        
        // View LinkedIn message in modal
        async function viewLinkedInMessage(messageId) {
            const modal = document.getElementById('email-modal');
            const content = document.getElementById('email-modal-content');
            
            // Show modal with loading state
            modal.classList.remove('hidden');
            content.innerHTML = '<div class="text-center text-gray-500">Loading message...</div>';
            
            try {
                // Find message in the cached data
                const message = allThreadsData.linkedin_messages.find(m => m.id === messageId);
                
                if (!message) {
                    throw new Error('Message not found');
                }
                
                content.innerHTML = `
                    <div class="space-y-4">
                        <!-- Message Header -->
                        <div class="border-b pb-4">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="px-3 py-1 text-sm rounded-full bg-blue-100 text-blue-800">💼 LinkedIn Message</span>
                                <span class="px-2 py-1 text-xs rounded-full ${
                                    message.direction === 'inbound' ? 'bg-blue-100 text-blue-800' :
                                    message.direction === 'outbound' ? 'bg-purple-100 text-purple-800' :
                                    'bg-gray-100 text-gray-800'
                                }">${message.direction}</span>
                            </div>
                            <div class="space-y-2 text-sm">
                                <div><span class="font-semibold text-gray-700">Sender:</span> <span class="text-gray-900">${message.sender_email}</span></div>
                                <div><span class="font-semibold text-gray-700">Date:</span> <span class="text-gray-900">${formatDate(message.sent_at)}</span></div>
                                <div><span class="font-semibold text-gray-700">Thread ID:</span> <span class="text-gray-900">#${message.thread_id}</span></div>
                            </div>
                        </div>
                        
                        <!-- Message Body -->
                        <div class="border-t pt-4">
                            <h4 class="text-sm font-semibold text-gray-900 mb-2">Message:</h4>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 max-h-96 overflow-y-auto">
                                <div class="whitespace-pre-wrap text-sm text-gray-800">${message.message_text || 'No message content'}</div>
                            </div>
                        </div>
                    </div>
                `;
                
            } catch (error) {
                console.error('Error loading LinkedIn message:', error);
                content.innerHTML = `
                    <div class="text-center text-red-500">
                        <p>Failed to load message</p>
                        <p class="text-sm mt-2">${error.message}</p>
                    </div>
                `;
            }
        }
        
        function closeEmailModal() {
            document.getElementById('email-modal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('email-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEmailModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEmailModal();
            }
        });
        
        // Global data storage for filtering
        let allThreadsData = null;
        
        // Filter threads by channel
        function filterThreads() {
            if (!allThreadsData) return;
            
            const filter = document.getElementById('channelFilter').value;
            const container = document.getElementById('threads-container');
            
            let filteredThreads = allThreadsData.threads;
            if (filter !== 'all') {
                filteredThreads = allThreadsData.threads.filter(t => t.channel === filter);
            }
            
            if (filteredThreads.length === 0) {
                container.innerHTML = '<div class="text-center text-gray-500">No threads found for this filter</div>';
                return;
            }
            
            // Re-render with filtered threads
            renderThreads(filteredThreads, allThreadsData);
        }
        
        // Separate render function for reuse
        function renderThreads(threads, data) {
            const container = document.getElementById('threads-container');
            
            // Group emails by thread_id for email threads
            const emailsByThread = {};
            data.emails.forEach(email => {
                if (!emailsByThread[email.thread_id]) {
                    emailsByThread[email.thread_id] = [];
                }
                emailsByThread[email.thread_id].push(email);
            });
            
            // Group LinkedIn messages by thread_id
            const linkedInMessagesByThread = {};
            (data.linkedin_messages || []).forEach(msg => {
                if (!linkedInMessagesByThread[msg.thread_id]) {
                    linkedInMessagesByThread[msg.thread_id] = [];
                }
                linkedInMessagesByThread[msg.thread_id].push(msg);
            });
            
            container.innerHTML = threads.map(thread => {
                const isLinkedIn = thread.channel === 'linkedin';
                const messages = isLinkedIn ? linkedInMessagesByThread[thread.id] || [] : emailsByThread[thread.id] || [];
                const enrichmentKey = `${thread.channel}_${thread.id}`;
                const enrichment = data.enrichments[enrichmentKey];
                
                // Build contact display
                const companyName = enrichment?.company_name || 'Unknown Company';
                const contactName = enrichment?.full_name || thread.contact_identifier;
                const channelIcon = isLinkedIn ? '💼' : '📧';
                const channelLabel = isLinkedIn ? 'LinkedIn' : 'Email';
                
                return `
                    <div class="mb-6 border border-gray-200 rounded-lg">
                        <!-- Thread Header -->
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="px-2 py-1 text-xs rounded-full ${
                                            isLinkedIn ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'
                                        }">${channelIcon} ${channelLabel}</span>
                                        <div class="text-lg font-bold text-gray-900">
                                            ${companyName}
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-700">
                                        <span class="font-semibold">${contactName}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Owner: ${thread.owner} · ${thread.email_count} message${thread.email_count !== 1 ? 's' : ''}
                                    </div>
                                </div>
                                <div class="text-right ml-4">
                                    <span class="px-2 py-1 text-xs rounded-full ${
                                        thread.status === 'Responded' ? 'bg-blue-100 text-blue-800' :
                                        thread.status === 'Closed' ? 'bg-gray-100 text-gray-800' :
                                        'bg-green-100 text-green-800'
                                    }">${thread.status}</span>
                                    ${enrichment ? '<div class="text-xs text-green-600 mt-1">✓ Enriched</div>' : ''}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Messages Preview -->
                        <div class="divide-y divide-gray-100">
                            ${messages.length === 0 ? 
                                '<div class="px-4 py-3 text-sm text-gray-500">No messages loaded</div>' :
                                messages.map(msg => {
                                    if (isLinkedIn) {
                                        // LinkedIn message rendering
                                        return `
                                            <div class="px-4 py-3 hover:bg-gray-50 cursor-pointer" onclick="viewLinkedInMessage(${msg.id})">
                                                <div class="flex justify-between items-start mb-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="px-2 py-1 text-xs rounded-full ${
                                                            msg.direction === 'inbound' ? 'bg-blue-100 text-blue-800' :
                                                            msg.direction === 'outbound' ? 'bg-purple-100 text-purple-800' :
                                                            'bg-gray-100 text-gray-800'
                                                        }">${msg.direction}</span>
                                                        <span class="text-sm font-medium text-gray-900">${msg.sender_email || 'N/A'}</span>
                                                    </div>
                                                    <span class="text-xs text-gray-500">${formatDate(msg.sent_at)}</span>
                                                </div>
                                                <div class="text-sm text-gray-700">${truncate(msg.message_text || 'No message', 150)}</div>
                                            </div>
                                        `;
                                    } else {
                                        // Email message rendering
                                        return `
                                            <div class="px-4 py-3 hover:bg-gray-50 cursor-pointer" onclick="viewEmail(${msg.id})">
                                                <div class="flex justify-between items-start mb-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="px-2 py-1 text-xs rounded-full ${
                                                            msg.direction === 'inbound' ? 'bg-blue-100 text-blue-800' :
                                                            msg.direction === 'outbound' ? 'bg-purple-100 text-purple-800' :
                                                            'bg-gray-100 text-gray-800'
                                                        }">${msg.direction}</span>
                                                        <span class="text-sm font-medium text-gray-900">${msg.from_email || 'N/A'}</span>
                                                        ${getLinkBadge(msg.id, data.links_by_email)}
                                                    </div>
                                                    <span class="text-xs text-gray-500">${formatDate(msg.received_at || msg.sent_at)}</span>
                                                </div>
                                                <div class="text-sm text-gray-700">${msg.subject || 'No Subject'}</div>
                                                ${msg.body_preview ? `<div class="text-xs text-gray-500 mt-1">${truncate(msg.body_preview, 100)}</div>` : ''}
                                            </div>
                                        `;
                                    }
                                }).join('')
                            }
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        // Load dashboard on page load
        loadDashboard();
        
        // Refresh every 30 seconds
        setInterval(loadDashboard, 30000);
    </script>
</body>
</html>
