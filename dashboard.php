<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Tracking Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
?>
    <!-- Header with Logo -->
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <!-- Logo placeholder -->
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                    <span class="text-white font-bold text-xl">MT</span>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Mail Tracker</h1>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Bar -->
    <nav class="bg-white border-b border-gray-200">
        <div class="container mx-auto px-4">
            <div class="flex space-x-8">
                <a href="dashboard.php" class="px-4 py-4 text-blue-600 border-b-2 border-blue-600 font-medium">
                    Email Log
                </a>
                <a href="email-drafter.php" class="px-4 py-4 text-gray-600 hover:text-gray-900 font-medium">
                    Email Drafter
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">

        <!-- Threads with Emails -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Threads & Messages</h2>
            </div>
            <div id="threads-container" class="p-6">
                <div class="text-center text-gray-500">Loading...</div>
            </div>
        </div>
    </div>

    <script>
        async function loadDashboard() {
            try {
                // Use relative path for API call
                const response = await fetch('api/dashboard');
                
                console.log('API Response status:', response.status);
                console.log('API Response headers:', response.headers);
                
                if (!response.ok) {
                    const text = await response.text();
                    console.error('API Error:', text);
                    throw new Error(`HTTP ${response.status}: ${text}`);
                }
                
                const data = await response.json();
                console.log('API Data:', data);
                
                const container = document.getElementById('threads-container');
                
                if (data.threads.length === 0) {
                    container.innerHTML = '<div class="text-center text-gray-500">No threads yet</div>';
                    return;
                }
                
                // Group emails by thread_id
                const emailsByThread = {};
                data.emails.forEach(email => {
                    if (!emailsByThread[email.thread_id]) {
                        emailsByThread[email.thread_id] = [];
                    }
                    emailsByThread[email.thread_id].push(email);
                });
                
                // Build nested view
                container.innerHTML = data.threads.map(thread => {
                    const threadEmails = emailsByThread[thread.id] || [];
                    const enrichment = data.enrichments[thread.id];
                    
                    // Build contact display - use enrichment if available
                    const companyName = enrichment?.company_name || 'Unknown Company';
                    const contactName = enrichment?.full_name || thread.external_email;
                    const firstName = enrichment?.first_name || '';
                    const lastName = enrichment?.last_name || '';
                    const jobTitle = enrichment?.job_title || '';
                    
                    return `
                        <div class="mb-6 border border-gray-200 rounded-lg">
                            <!-- Thread Header -->
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <!-- Company Name (Top Level) -->
                                        <div class="text-lg font-bold text-gray-900 mb-2">
                                            ${companyName}
                                        </div>
                                        
                                        <!-- Contact Info -->
                                        <div class="text-sm text-gray-700">
                                            ${firstName || lastName ? 
                                                `<span class="font-semibold">${firstName} ${lastName}</span>` : 
                                                `<span class="font-semibold">${contactName}</span>`
                                            }
                                            ${jobTitle ? `<span class="text-gray-500"> · ${jobTitle}</span>` : ''}
                                        </div>
                                        
                                        <!-- Email & Subject -->
                                        <div class="text-xs text-gray-500 mt-1">
                                            ${thread.external_email} · ${thread.subject_normalized || 'No Subject'}
                                        </div>
                                    </div>
                                    <div class="text-right ml-4">
                                        <span class="px-2 py-1 text-xs rounded-full ${
                                            thread.status === 'Responded' ? 'bg-blue-100 text-blue-800' :
                                            thread.status === 'Closed' ? 'bg-gray-100 text-gray-800' :
                                            'bg-green-100 text-green-800'
                                        }">${thread.status}</span>
                                        <div class="text-xs text-gray-500 mt-1">${thread.email_count} message${thread.email_count !== 1 ? 's' : ''}</div>
                                        ${enrichment ? '<div class="text-xs text-green-600 mt-1">✓ Enriched</div>' : ''}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Thread Messages -->
                            <div class="divide-y divide-gray-100">
                                ${threadEmails.length === 0 ? 
                                    '<div class="px-4 py-3 text-sm text-gray-500">No messages loaded</div>' :
                                    threadEmails.map(email => `
                                        <div class="px-4 py-3 hover:bg-gray-50">
                                            <div class="flex justify-between items-start mb-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2 py-1 text-xs rounded-full ${
                                                        email.direction === 'inbound' ? 'bg-blue-100 text-blue-800' :
                                                        email.direction === 'outbound' ? 'bg-purple-100 text-purple-800' :
                                                        'bg-gray-100 text-gray-800'
                                                    }">${email.direction}</span>
                                                    <span class="text-sm font-medium text-gray-900">${email.from_email || 'N/A'}</span>
                                                </div>
                                                <span class="text-xs text-gray-500">${formatDate(email.received_at || email.sent_at)}</span>
                                            </div>
                                            <div class="text-sm text-gray-700">${email.subject || 'No Subject'}</div>
                                            ${email.body_preview ? `<div class="text-xs text-gray-500 mt-1">${truncate(email.body_preview, 100)}</div>` : ''}
                                        </div>
                                    `).join('')
                                }
                            </div>
                        </div>
                    `;
                }).join('');
                
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
        
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return date.toLocaleString();
        }
        
        function truncate(str, length) {
            if (!str) return 'N/A';
            return str.length > length ? str.substring(0, length) + '...' : str;
        }
        
        // Load dashboard on page load
        loadDashboard();
        
        // Refresh every 30 seconds
        setInterval(loadDashboard, 30000);
    </script>
</body>
</html>
