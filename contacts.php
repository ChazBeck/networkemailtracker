<?php
// Load environment and dependencies
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize SSO authentication
require_once __DIR__ . '/includes/auth-init.php';

// Include SSO header
require_once __DIR__ . '/../auth/header-with-sso.php';

// Render SSO head and header
render_sso_head('Contacts - Mail Tracker');
render_sso_header();
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
                <a href="dashboard.php" class="nav-link">Email Log</a>
                <a href="contacts.php" class="nav-link active">Contacts</a>
                <a href="email-drafter.php" class="nav-link">Email Drafter</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">

        <!-- Contacts List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Contacts</h2>
                <p class="text-sm text-gray-500 mt-1">People you've communicated with</p>
            </div>
            <div id="contacts-container" class="p-6">
                <div class="text-center text-gray-500">Loading...</div>
            </div>
        </div>
    </div>

    <script>
        async function loadContacts() {
            try {
                const response = await fetch('api/contacts');
                
                if (!response.ok) {
                    const text = await response.text();
                    console.error('API Error:', text);
                    throw new Error(`HTTP ${response.status}: ${text}`);
                }
                
                const data = await response.json();
                console.log('Contacts Data:', data);
                
                const container = document.getElementById('contacts-container');
                
                if (data.contacts.length === 0) {
                    container.innerHTML = '<div class="text-center text-gray-500">No contacts yet</div>';
                    return;
                }
                
                // Build contacts table
                container.innerHTML = `
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job Title</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Threads</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Contact</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${data.contacts.map(contact => `
                                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="viewContactThreads('${contact.email}')">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                ${contact.full_name || contact.email}
                                            </div>
                                            ${contact.first_name && contact.last_name ? 
                                                `<div class="text-xs text-gray-500">${contact.first_name} ${contact.last_name}</div>` : 
                                                ''}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">${contact.company_name || '-'}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">${contact.job_title || '-'}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-600">${contact.email}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                                ${contact.thread_count} thread${contact.thread_count !== 1 ? 's' : ''}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            ${formatDate(contact.last_contact)}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            ${getEnrichmentBadge(contact.enrichment_status)}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
                
            } catch (error) {
                console.error('Error loading contacts:', error);
                document.getElementById('contacts-container').innerHTML = 
                    `<div class="text-center text-red-500">
                        <p>Failed to load contacts</p>
                        <p class="text-sm mt-2">${error.message}</p>
                    </div>`;
            }
        }
        
        function getEnrichmentBadge(status) {
            if (!status || status === 'pending') {
                return '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">⏳ Enriching...</span>';
            } else if (status === 'complete') {
                return '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">✓ Enriched</span>';
            } else if (status === 'failed') {
                return '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">✗ Failed</span>';
            }
            return '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">-</span>';
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            const now = new Date();
            const diffTime = Math.abs(now - date);
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays === 0) {
                return 'Today';
            } else if (diffDays === 1) {
                return 'Yesterday';
            } else if (diffDays < 7) {
                return `${diffDays} days ago`;
            } else {
                return date.toLocaleDateString();
            }
        }
        
        function viewContactThreads(email) {
            // Navigate to dashboard with filter for this contact's email
            window.location.href = `dashboard.php?contact=${encodeURIComponent(email)}`;
        }
        
        // Load contacts on page load
        loadContacts();
        
        // Refresh every 30 seconds to catch new enrichments
        setInterval(loadContacts, 30000);
    </script>
</body>
</html>
