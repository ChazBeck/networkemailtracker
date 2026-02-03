<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">API Test Page</h1>
        
        <!-- Test Dashboard API -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Test Dashboard API</h2>
            <button onclick="testDashboard()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Test GET /api/dashboard
            </button>
            <pre id="dashboard-result" class="mt-4 p-4 bg-gray-100 rounded text-xs overflow-auto max-h-96"></pre>
        </div>

        <!-- Test LinkedIn Submit API -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Test LinkedIn Submit API</h2>
            <div class="space-y-3 mb-4">
                <input type="text" id="linkedin-url" placeholder="LinkedIn URL" value="https://www.linkedin.com/in/testuser" class="w-full px-3 py-2 border rounded">
                <textarea id="message-text" placeholder="Message" rows="3" class="w-full px-3 py-2 border rounded">Test message from API test page</textarea>
                <select id="direction" class="w-full px-3 py-2 border rounded">
                    <option value="outbound">Sent (Outbound)</option>
                    <option value="inbound">Received (Inbound)</option>
                </select>
                <select id="sender" class="w-full px-3 py-2 border rounded">
                    <option value="charlie@veerless.com">charlie@veerless.com</option>
                    <option value="sarah@veerless.com">sarah@veerless.com</option>
                    <option value="networking@veerless.com">networking@veerless.com</option>
                </select>
            </div>
            <button onclick="testLinkedInSubmit()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                Test POST /api/linkedin/submit
            </button>
            <pre id="linkedin-result" class="mt-4 p-4 bg-gray-100 rounded text-xs overflow-auto max-h-96"></pre>
        </div>

        <!-- Test LinkedIn Threads API -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Test LinkedIn Threads API</h2>
            <button onclick="testLinkedInThreads()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                Test GET /api/linkedin/threads
            </button>
            <pre id="threads-result" class="mt-4 p-4 bg-gray-100 rounded text-xs overflow-auto max-h-96"></pre>
        </div>

        <!-- Raw cURL Commands -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Direct cURL Tests</h2>
            <div class="space-y-2 text-sm">
                <p class="font-mono bg-gray-100 p-2 rounded">curl http://localhost/networkemailtracking/api/dashboard</p>
                <p class="font-mono bg-gray-100 p-2 rounded">curl http://localhost/networkemailtracking/api/linkedin/threads</p>
                <p class="font-mono bg-gray-100 p-2 rounded break-all">curl -X POST http://localhost/networkemailtracking/api/linkedin/submit -H "Content-Type: application/json" -d '{"linkedin_url":"https://www.linkedin.com/in/test","message_text":"Test","direction":"outbound","sender_email":"charlie@veerless.com"}'</p>
            </div>
        </div>
    </div>

    <script>
        function displayResult(elementId, title, response, data, error = null) {
            const el = document.getElementById(elementId);
            let output = `=== ${title} ===\n\n`;
            
            if (error) {
                output += `❌ ERROR:\n${error}\n\n`;
            }
            
            output += `Status: ${response.status} ${response.statusText}\n`;
            output += `URL: ${response.url}\n\n`;
            
            output += `Headers:\n`;
            for (let [key, value] of response.headers.entries()) {
                output += `  ${key}: ${value}\n`;
            }
            output += `\n`;
            
            if (data) {
                output += `Response Body:\n`;
                output += JSON.stringify(data, null, 2);
            }
            
            el.textContent = output;
        }

        async function testDashboard() {
            const resultEl = document.getElementById('dashboard-result');
            resultEl.textContent = 'Loading...';
            
            try {
                const response = await fetch('/networkemailtracking/api/dashboard');
                const text = await response.text();
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    displayResult('dashboard-result', 'Dashboard API Test', response, null, 
                        `Failed to parse JSON. Response text:\n${text.substring(0, 500)}`);
                    return;
                }
                
                displayResult('dashboard-result', 'Dashboard API Test', response, data);
            } catch (error) {
                document.getElementById('dashboard-result').textContent = `❌ Network Error:\n${error.message}`;
            }
        }

        async function testLinkedInSubmit() {
            const resultEl = document.getElementById('linkedin-result');
            resultEl.textContent = 'Submitting...';
            
            const payload = {
                linkedin_url: document.getElementById('linkedin-url').value,
                message_text: document.getElementById('message-text').value,
                direction: document.getElementById('direction').value,
                sender_email: document.getElementById('sender').value
            };
            
            try {
                const response = await fetch('/networkemailtracking/api/linkedin/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                const text = await response.text();
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    displayResult('linkedin-result', 'LinkedIn Submit API Test', response, null, 
                        `Failed to parse JSON. Response text:\n${text.substring(0, 500)}`);
                    return;
                }
                
                displayResult('linkedin-result', 'LinkedIn Submit API Test', response, data);
            } catch (error) {
                document.getElementById('linkedin-result').textContent = `❌ Network Error:\n${error.message}`;
            }
        }

        async function testLinkedInThreads() {
            const resultEl = document.getElementById('threads-result');
            resultEl.textContent = 'Loading...';
            
            try {
                const response = await fetch('/networkemailtracking/api/linkedin/threads');
                const text = await response.text();
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    displayResult('threads-result', 'LinkedIn Threads API Test', response, null, 
                        `Failed to parse JSON. Response text:\n${text.substring(0, 500)}`);
                    return;
                }
                
                displayResult('threads-result', 'LinkedIn Threads API Test', response, data);
            } catch (error) {
                document.getElementById('threads-result').textContent = `❌ Network Error:\n${error.message}`;
            }
        }
    </script>
</body>
</html>
