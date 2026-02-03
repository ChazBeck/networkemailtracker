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
    error_log("WARNING: Running contacts.php without authentication in local development");
}

// Include SSO header with environment-aware loading
$headerPath = __DIR__ . '/../auth/header-with-sso.php';
if (file_exists($headerPath)) {
    require_once $headerPath;
    // Render SSO head and header
    render_sso_head('Contacts - Mail Tracker');
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
    <title>Contacts - Mail Tracker (Local Dev)</title>
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

    <!-- Edit Contact Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Edit Contact Information</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="editForm" onsubmit="saveContact(event)">
                <input type="hidden" id="edit-enrichment-id">
                <input type="hidden" id="edit-email">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" id="edit-first-name" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" id="edit-last-name" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" id="edit-full-name" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                        <input type="text" id="edit-company-name" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                        <input type="text" id="edit-job-title" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Company URL</label>
                        <input type="url" id="edit-company-url" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="https://">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">LinkedIn URL</label>
                        <input type="url" id="edit-linkedin-url" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="https://linkedin.com/in/">
                    </div>
                    
                    <div class="text-sm text-gray-500 bg-gray-50 p-3 rounded">
                        <strong>Email:</strong> <span id="edit-email-display"></span>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" 
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentContacts = []; // Store contacts for editing
        
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
                
                // Store contacts for editing
                currentContacts = data.contacts;
                
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${data.contacts.map((contact, index) => `
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap cursor-pointer" onclick="viewContactThreads('${contact.email}')">
                                            <div class="text-sm font-medium text-gray-900">
                                                ${contact.full_name || contact.email}
                                            </div>
                                            ${contact.first_name && contact.last_name ? 
                                                `<div class="text-xs text-gray-500">${contact.first_name} ${contact.last_name}</div>` : 
                                                ''}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap cursor-pointer" onclick="viewContactThreads('${contact.email}')">
                                            <div class="text-sm text-gray-900">${contact.company_name || '-'}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap cursor-pointer" onclick="viewContactThreads('${contact.email}')">
                                            <div class="text-sm text-gray-900">${contact.job_title || '-'}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap cursor-pointer" onclick="viewContactThreads('${contact.email}')">
                                            <div class="text-sm text-gray-600">${contact.email}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap cursor-pointer" onclick="viewContactThreads('${contact.email}')">
                                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                                ${contact.thread_count} thread${contact.thread_count !== 1 ? 's' : ''}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 cursor-pointer" onclick="viewContactThreads('${contact.email}')">
                                            ${formatDate(contact.last_contact)}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap cursor-pointer" onclick="viewContactThreads('${contact.email}')">
                                            ${getEnrichmentBadge(contact.enrichment_status)}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <button onclick="event.stopPropagation(); openEditModal(${index})" 
                                                class="text-blue-600 hover:text-blue-900 font-medium mr-3">
                                                Edit
                                            </button>
                                            <button onclick="event.stopPropagation(); syncToMonday('${contact.email}', ${index})" 
                                                class="text-green-600 hover:text-green-900 font-medium"
                                                id="sync-btn-${index}">
                                                Sync
                                            </button>
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
        
        function openEditModal(contactIndex) {
            const contact = currentContacts[contactIndex];
            
            // We need to get the enrichment ID - fetch it from the contact's thread
            // For now, we'll need to add enrichment_id to the contact query response
            // Let's populate the form with contact data
            document.getElementById('edit-email').value = contact.email;
            document.getElementById('edit-email-display').textContent = contact.email;
            document.getElementById('edit-first-name').value = contact.first_name || '';
            document.getElementById('edit-last-name').value = contact.last_name || '';
            document.getElementById('edit-full-name').value = contact.full_name || '';
            document.getElementById('edit-company-name').value = contact.company_name || '';
            document.getElementById('edit-job-title').value = contact.job_title || '';
            document.getElementById('edit-company-url').value = contact.company_url || '';
            document.getElementById('edit-linkedin-url').value = contact.linkedin_url || '';
            
            // Store the enrichment ID if available
            if (contact.enrichment_id) {
                document.getElementById('edit-enrichment-id').value = contact.enrichment_id;
            }
            
            // Show modal
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        async function saveContact(event) {
            event.preventDefault();
            
            const enrichmentId = document.getElementById('edit-enrichment-id').value;
            const email = document.getElementById('edit-email').value;
            
            // If no enrichment ID, we need to create one first
            if (!enrichmentId) {
                alert('Cannot edit contact without enrichment data. Please wait for enrichment to complete.');
                return;
            }
            
            const data = {
                first_name: document.getElementById('edit-first-name').value || null,
                last_name: document.getElementById('edit-last-name').value || null,
                full_name: document.getElementById('edit-full-name').value || null,
                company_name: document.getElementById('edit-company-name').value || null,
                job_title: document.getElementById('edit-job-title').value || null,
                company_url: document.getElementById('edit-company-url').value || null,
                linkedin_url: document.getElementById('edit-linkedin-url').value || null
            };
            
            try {
                const response = await fetch(`api/enrichment/${enrichmentId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(`HTTP ${response.status}: ${text}`);
                }
                
                const result = await response.json();
                console.log('Update result:', result);
                
                // Close modal and reload contacts
                closeEditModal();
                await loadContacts();
                
                alert('Contact updated successfully!');
            } catch (error) {
                console.error('Error saving contact:', error);
                alert('Failed to save contact: ' + error.message);
            }
        }
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeEditModal();
            }
        });
        
        async function syncToMonday(email, index) {
            const btn = document.getElementById(`sync-btn-${index}`);
            const originalText = btn.textContent;
            btn.textContent = 'Syncing...';
            btn.disabled = true;
            
            try {
                const response = await fetch('api/monday/sync-contact', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email: email })
                });
                
                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(`HTTP ${response.status}: ${text}`);
                }
                
                const result = await response.json();
                console.log('Sync result:', result);
                
                // Show success feedback
                btn.textContent = '✓ Synced';
                btn.classList.remove('text-green-600', 'hover:text-green-900');
                btn.classList.add('text-gray-400');
                
                // Reset after 3 seconds
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('text-gray-400');
                    btn.classList.add('text-green-600', 'hover:text-green-900');
                    btn.disabled = false;
                }, 3000);
            } catch (error) {
                console.error('Error syncing to Monday:', error);
                btn.textContent = '✗ Error';
                btn.classList.remove('text-green-600');
                btn.classList.add('text-red-600');
                
                // Reset after 3 seconds
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('text-red-600');
                    btn.classList.add('text-green-600', 'hover:text-green-900');
                    btn.disabled = false;
                }, 3000);
            }
        }
        
        // Load contacts on page load
        loadContacts();
    </script>
</body>
</html>
