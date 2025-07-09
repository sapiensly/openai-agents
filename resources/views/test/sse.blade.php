<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SSE Streaming Test - Laravel OpenAI Agents</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: #1a202c;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            color: #2d3748;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1a202c;
        }

        .header .subtitle {
            font-size: 1.1rem;
            color: #4a5568;
            font-weight: 400;
        }

        .main-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .status-banner {
            background: #f7fafc;
            color: #2d3748;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #e2e8f0;
        }

        .status-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-icon {
            font-size: 1.5rem;
            animation: pulse 2s infinite;
        }

        .status-text {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
        }

        .csrf-status {
            background: #edf2f7;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4a5568;
            border: 1px solid #cbd5e0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #f7fafc;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .info-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .info-card h4 {
            color: #2d3748;
            font-size: 1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .info-card p {
            color: #4a5568;
            font-size: 0.875rem;
        }

        .mode-toggle {
            background: #f7fafc;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }

        .mode-toggle h4 {
            color: #2d3748;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .mode-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .mode-btn {
            padding: 10px 20px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
            color: #4a5568;
        }

        .mode-btn:hover {
            background: #edf2f7;
            border-color: #a0aec0;
        }

        .mode-btn.active {
            background: #2d3748;
            color: white;
            border-color: #2d3748;
        }

        .form-section {
            background: #f7fafc;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: white;
            color: #2d3748;
        }

        .form-control:focus {
            outline: none;
            border-color: #4a5568;
            box-shadow: 0 0 0 3px rgba(74, 85, 104, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            color: #2d3748;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-primary {
            background: #2d3748;
            color: white;
            border-color: #2d3748;
        }

        .btn-primary:hover {
            background: #1a202c;
            border-color: #1a202c;
        }

        .btn-secondary {
            background: #718096;
            color: white;
            border-color: #718096;
        }

        .btn-secondary:hover {
            background: #4a5568;
            border-color: #4a5568;
        }

        .btn-success {
            background: #38a169;
            color: white;
            border-color: #38a169;
        }

        .btn-success:hover {
            background: #2f855a;
            border-color: #2f855a;
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
            border-color: #e53e3e;
        }

        .btn-danger:hover {
            background: #c53030;
            border-color: #c53030;
        }

        .output-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .output-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .output-title {
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .output {
            padding: 25px;
            min-height: 400px;
            max-height: 600px;
            overflow-y: auto;
            font-family: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            background: #fafafa;
            color: #2d3748;
        }

        .output.normal-mode {
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            line-height: 1.8;
            background: white;
            padding: 30px;
        }

        .conversation {
            display: none;
        }

        .conversation.active {
            display: block;
        }

        .message {
            margin-bottom: 25px;
            padding: 20px;
            border-radius: 8px;
            max-width: 85%;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            background: #2d3748;
            color: white;
            margin-left: auto;
            text-align: right;
        }

        .message.assistant {
            background: #f7fafc;
            color: #2d3748;
            margin-right: auto;
            border: 1px solid #e2e8f0;
        }

        .message.assistant .typing-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #cbd5e0;
            border-top: 2px solid #2d3748;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        .stats {
            background: #f7fafc;
            padding: 30px;
            border-radius: 8px;
            margin-top: 30px;
            border: 1px solid #e2e8f0;
        }

        .stats h3 {
            color: #2d3748;
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
        }

        .stat-item {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .stat-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #718096;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .error {
            background: #fed7d7;
            color: #c53030;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid #feb2b2;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            color: #718096;
            font-size: 0.875rem;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .main-card {
                padding: 25px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Scrollbar Styling */
        .output::-webkit-scrollbar {
            width: 8px;
        }

        .output::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .output::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .output::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SSE Streaming Test</h1>
            <p class="subtitle">Laravel OpenAI Agents - Real-time AI Conversations</p>
        </div>

        <div class="main-card">
            <div class="status-banner">
                <div class="status-info">
                    <span class="status-icon" id="statusIcon">🔴</span>
                    <span class="status-text" id="statusText">Status: Disconnected</span>
                </div>
                <div class="csrf-status">
                    CSRF: {{ csrf_token() ? 'Active' : 'Missing' }}
                </div>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <h4>Endpoint</h4>
                    <p><code>/agents/chat-stream</code></p>
                </div>
                <div class="info-card">
                    <h4>Protocol</h4>
                    <p>Server-Sent Events (SSE)</p>
                </div>
                <div class="info-card">
                    <h4>Streaming</h4>
                    <p>Real-time text generation</p>
                </div>
                <div class="info-card">
                    <h4>Metrics</h4>
                    <p>Live performance statistics</p>
                </div>
            </div>

            <div class="mode-toggle">
                <h4>Display Mode</h4>
                <div class="mode-buttons">
                    <button class="mode-btn active" onclick="setMode('debug')">Debug Mode</button>
                    <button class="mode-btn" onclick="setMode('normal')">Normal Mode</button>
                </div>
                <small>Debug mode shows technical details. Normal mode displays text like a real conversation.</small>
            </div>

            <div class="form-section">
                <div class="form-group">
                    <label for="message">Message:</label>
                    <input type="text" id="message" class="form-control" value="Tell me a short story about a robot learning to paint" placeholder="Enter your message here...">
                </div>
                
                <div class="form-group">
                    <label for="system">System Prompt (optional):</label>
                    <textarea id="system" class="form-control" placeholder="Enter system prompt...">You are a helpful AI assistant. Keep responses concise and engaging.</textarea>
                </div>
                
                <div class="button-group">
                    <button id="startBtn" class="btn btn-primary" onclick="startStreaming()">
                        Start Streaming
                    </button>
                    <button id="stopBtn" class="btn btn-danger" onclick="stopStreaming()" disabled>
                        Stop Streaming
                    </button>
                    <button class="btn btn-secondary" onclick="clearOutput()">
                        Clear Output
                    </button>
                    <button class="btn btn-secondary" onclick="testConnection()">
                        Test Connection
                    </button>
                </div>
            </div>

            <div class="output-container">
                <div class="output-header">
                    <div class="output-title">
                        <span id="outputTitle">Debug Output</span>
                    </div>
                </div>
                
                <!-- Debug Mode Output -->
                <div id="debugOutput" class="output">Ready to start streaming...\n\nClick "Start Streaming" to begin the demo.</div>
                
                <!-- Normal Mode Output -->
                <div id="normalOutput" class="output normal-mode conversation">
                    <div class="message user">
                        <div id="userMessage">Your message will appear here...</div>
                    </div>
                    <div class="message assistant">
                        <div id="assistantMessage">
                            <span class="typing-indicator"></span>
                            <span id="assistantText">Waiting for response...</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="stats" class="stats" style="display: none;">
                <h3>Streaming Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="chunkCount">0</div>
                        <div class="stat-label">Chunks</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalTime">0s</div>
                        <div class="stat-label">Duration</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="chunksPerSecond">0</div>
                        <div class="stat-label">Chunks/sec</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalChars">0</div>
                        <div class="stat-label">Characters</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Laravel OpenAI Agents - SSE Streaming Test | Built with Laravel Blade</p>
    </div>

    <script>
        let eventSource = null;
        let startTime = null;
        let chunkCount = 0;
        let totalChars = 0;
        let statsInterval = null;
        let csrfToken = null;
        let currentMode = 'debug';
        let currentResponse = '';

        // Get CSRF token from meta tag or cookie
        function getCsrfToken() {
            // Try to get from meta tag first
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                return metaTag.getAttribute('content');
            }
            
            // Try to get from cookie
            const cookies = document.cookie.split(';');
            for (let cookie of cookies) {
                const [name, value] = cookie.trim().split('=');
                if (name === 'XSRF-TOKEN') {
                    return decodeURIComponent(value);
                }
            }
            
            return null;
        }

        function setMode(mode) {
            currentMode = mode;
            
            // Update button states
            document.querySelectorAll('.mode-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show/hide outputs
            const debugOutput = document.getElementById('debugOutput');
            const normalOutput = document.getElementById('normalOutput');
            const outputTitle = document.getElementById('outputTitle');
            
            if (mode === 'debug') {
                debugOutput.style.display = 'block';
                normalOutput.style.display = 'none';
                outputTitle.innerHTML = 'Debug Output';
            } else {
                debugOutput.style.display = 'none';
                normalOutput.style.display = 'block';
                outputTitle.innerHTML = 'Conversation';
            }
            
            // Update user message in normal mode
            if (mode === 'normal') {
                const userMessage = document.getElementById('userMessage');
                const messageInput = document.getElementById('message');
                userMessage.textContent = messageInput.value || 'Your message...';
            }
        }

        function updateStatus(message, type) {
            const status = document.querySelector('.status-banner');
            const statusIcon = document.getElementById('statusIcon');
            const statusText = document.getElementById('statusText');
            
            statusText.textContent = `Status: ${message}`;
            
            // Update icon and colors based on status
            switch(type) {
                case 'connected':
                    statusIcon.textContent = '🟢';
                    status.style.background = '#f0fff4';
                    status.style.borderColor = '#9ae6b4';
                    break;
                case 'streaming':
                    statusIcon.textContent = '🔄';
                    status.style.background = '#fffbeb';
                    status.style.borderColor = '#fbd38d';
                    break;
                case 'disconnected':
                    statusIcon.textContent = '🔴';
                    status.style.background = '#f7fafc';
                    status.style.borderColor = '#e2e8f0';
                    break;
            }
        }

        function appendToOutput(text, type = 'normal') {
            const output = document.getElementById('debugOutput');
            const timestamp = new Date().toLocaleTimeString();
            const prefix = type === 'error' ? '❌' : type === 'success' ? '✅' : type === 'info' ? 'ℹ️' : type === 'debug' ? '🔍' : '';
            output.textContent += `[${timestamp}] ${prefix} ${text}\n`;
            output.scrollTop = output.scrollHeight;
        }

        function updateNormalMode(text, isComplete = false) {
            const assistantText = document.getElementById('assistantText');
            const typingIndicator = document.querySelector('.typing-indicator');
            
            if (isComplete) {
                // Hide typing indicator when complete
                if (typingIndicator) {
                    typingIndicator.style.display = 'none';
                }
            } else {
                // Show typing indicator while streaming
                if (typingIndicator) {
                    typingIndicator.style.display = 'inline-block';
                }
            }
            
            assistantText.textContent = text;
        }

        function updateStats() {
            if (startTime) {
                const elapsed = (Date.now() - startTime) / 1000;
                document.getElementById('totalTime').textContent = `${elapsed.toFixed(1)}s`;
                document.getElementById('chunksPerSecond').textContent = (chunkCount / elapsed).toFixed(2);
            }
            document.getElementById('chunkCount').textContent = chunkCount;
            document.getElementById('totalChars').textContent = totalChars;
        }

        function testConnection() {
            appendToOutput('Testing connection to SSE endpoint...', 'info');
            
            const testData = new FormData();
            testData.append('message', 'Hello');
            testData.append('_token', getCsrfToken());
            
            fetch('/agents/chat-stream', {
                method: 'POST',
                body: testData,
                headers: {
                    'Accept': 'text/event-stream',
                    'Cache-Control': 'no-cache',
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                }
            })
            .then(response => {
                if (response.ok) {
                    appendToOutput('✅ Connection test successful!', 'success');
                } else {
                    appendToOutput(`❌ Connection test failed: ${response.status}`, 'error');
                }
            })
            .catch(error => {
                appendToOutput(`❌ Connection test error: ${error.message}`, 'error');
            });
        }

        function startStreaming() {
            const message = document.getElementById('message').value.trim();
            const system = document.getElementById('system').value.trim();
            
            if (!message) {
                alert('Please enter a message');
                return;
            }

            // Get CSRF token
            csrfToken = getCsrfToken();
            if (!csrfToken) {
                appendToOutput('Warning: No CSRF token found. Trying without it...', 'info');
            }

            // Reset state
            chunkCount = 0;
            totalChars = 0;
            currentResponse = '';
            startTime = Date.now();
            
            // Clear outputs based on mode
            if (currentMode === 'debug') {
                document.getElementById('debugOutput').textContent = '';
            } else {
                document.getElementById('assistantText').textContent = '';
                document.querySelector('.typing-indicator').style.display = 'inline-block';
            }
            
            document.getElementById('stats').style.display = 'block';
            
            // Update UI
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            updateStatus('Connecting...', 'streaming');
            
            if (currentMode === 'debug') {
                appendToOutput('Connecting to SSE endpoint...', 'info');
            }

            // Create EventSource
            const url = '/agents/chat-stream';
            const data = new FormData();
            data.append('message', message);
            if (system) {
                data.append('system', system);
            }
            
            // Add CSRF token if available
            if (csrfToken) {
                data.append('_token', csrfToken);
            }

            // Use fetch with streaming
            fetch(url, {
                method: 'POST',
                body: data,
                headers: {
                    'Accept': 'text/event-stream',
                    'Cache-Control': 'no-cache',
                    'X-CSRF-TOKEN': csrfToken || '',
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                updateStatus('Connected - Streaming...', 'connected');
                if (currentMode === 'debug') {
                    appendToOutput('Connected successfully!', 'success');
                }
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                
                function readStream() {
                    return reader.read().then(({ done, value }) => {
                        if (done) {
                            updateStatus('Stream completed', 'connected');
                            if (currentMode === 'debug') {
                                appendToOutput('Stream completed', 'success');
                            } else {
                                updateNormalMode(currentResponse, true);
                            }
                            stopStreaming();
                            return;
                        }
                        
                        const chunk = decoder.decode(value);
                        const lines = chunk.split('\n');
                        
                        lines.forEach(line => {
                            if (line.startsWith('data: ')) {
                                try {
                                    const data = JSON.parse(line.substring(6));
                                    
                                    switch (data.type) {
                                        case 'connected':
                                            if (currentMode === 'debug') {
                                                appendToOutput(`Connected: ${data.message}`, 'success');
                                            }
                                            break;
                                        case 'debug':
                                            if (currentMode === 'debug') {
                                                appendToOutput(`Debug: ${data.message}`, 'debug');
                                            }
                                            break;
                                        case 'chunk':
                                            if (currentMode === 'debug') {
                                                appendToOutput(data.chunk, 'normal');
                                            } else {
                                                currentResponse += data.chunk;
                                                updateNormalMode(currentResponse);
                                            }
                                            chunkCount++;
                                            totalChars += data.chunk.length;
                                            break;
                                        case 'done':
                                            if (currentMode === 'debug') {
                                                appendToOutput('Stream finished', 'success');
                                                if (data.stats) {
                                                    appendToOutput(`Stats: ${JSON.stringify(data.stats, null, 2)}`, 'info');
                                                }
                                            } else {
                                                updateNormalMode(currentResponse, true);
                                            }
                                            break;
                                        case 'error':
                                            if (currentMode === 'debug') {
                                                appendToOutput(`Error: ${data.message}`, 'error');
                                            } else {
                                                updateNormalMode(`Error: ${data.message}`, true);
                                            }
                                            break;
                                    }
                                } catch (e) {
                                    if (currentMode === 'debug') {
                                        appendToOutput(`Raw data: ${line}`, 'info');
                                    }
                                }
                            }
                        });
                        
                        return readStream();
                    });
                }
                
                return readStream();
            })
            .catch(error => {
                updateStatus('Connection failed', 'disconnected');
                if (currentMode === 'debug') {
                    appendToOutput(`Error: ${error.message}`, 'error');
                } else {
                    updateNormalMode(`Error: ${error.message}`, true);
                }
                stopStreaming();
            });

            // Start stats update interval
            statsInterval = setInterval(updateStats, 100);
        }

        function stopStreaming() {
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }
            
            if (statsInterval) {
                clearInterval(statsInterval);
                statsInterval = null;
            }
            
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            updateStatus('Disconnected', 'disconnected');
        }

        function clearOutput() {
            if (currentMode === 'debug') {
                document.getElementById('debugOutput').textContent = 'Ready to start streaming...\n\nClick "Start Streaming" to begin the demo.\n';
            } else {
                document.getElementById('assistantText').textContent = 'Waiting for response...';
                document.querySelector('.typing-indicator').style.display = 'inline-block';
            }
            document.getElementById('stats').style.display = 'none';
            chunkCount = 0;
            totalChars = 0;
            currentResponse = '';
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', stopStreaming);
    </script>
</body>
</html> 