<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Tracking Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Email Tracking Dashboard</h1>
            <p class="text-gray-600 mt-2">Monitoring networking@veerless.com</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-600">Total Threads</div>
                <div id="stat-threads" class="text-3xl font-bold text-gray-900">-</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-600">Total Emails</div>
                <div id="stat-emails" class="text-3xl font-bold text-gray-900">-</div>
            </div>
        </div>

        <!-- Threads Table -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Threads</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">External Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Internal Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Emails</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Activity</th>
                        </tr>
                    </thead>
                    <tbody id="threads-body" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Emails Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Recent Emails</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Direction</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preview</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody id="emails-body" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        async function loadDashboard() {
            try {
                const response = await fetch('/api/dashboard');
                const data = await response.json();
                
                // Update stats
                document.getElementById('stat-threads').textContent = data.stats.total_threads;
                document.getElementById('stat-emails').textContent = data.stats.total_emails;
                
                // Update threads table
                const threadsBody = document.getElementById('threads-body');
                if (data.threads.length === 0) {
                    threadsBody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No threads yet</td></tr>';
                } else {
                    threadsBody.innerHTML = data.threads.map(thread => `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${thread.external_email}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${thread.internal_sender_email}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">${thread.subject_normalized || 'N/A'}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${thread.email_count}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full ${
                                    thread.status === 'Responded' ? 'bg-blue-100 text-blue-800' :
                                    thread.status === 'Closed' ? 'bg-gray-100 text-gray-800' :
                                    'bg-green-100 text-green-800'
                                }">${thread.status}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${formatDate(thread.last_activity_at)}</td>
                        </tr>
                    `).join('');
                }
                
                // Update emails table
                const emailsBody = document.getElementById('emails-body');
                if (data.emails.length === 0) {
                    emailsBody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No emails yet</td></tr>';
                } else {
                    emailsBody.innerHTML = data.emails.map(email => `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full ${
                                    email.direction === 'inbound' ? 'bg-blue-100 text-blue-800' :
                                    email.direction === 'outbound' ? 'bg-purple-100 text-purple-800' :
                                    'bg-gray-100 text-gray-800'
                                }">${email.direction}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${email.from_email || 'N/A'}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">${email.subject || 'N/A'}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">${truncate(email.body_preview, 50)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${formatDate(email.received_at || email.sent_at)}</td>
                        </tr>
                    `).join('');
                }
                
            } catch (error) {
                console.error('Error loading dashboard:', error);
                alert('Failed to load dashboard data');
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
