<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\MCP;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

/**
 * Class MCPClient
 *
 * Handles communication with MCP servers using HTTP requests.
 */
class MCPClient
{
    /**
     * The server URL
     */
    private string $serverUrl;

    /**
     * Request headers
     */
    private array $headers;

    /**
     * Server capabilities
     */
    private array $capabilities = [];

    /**
     * Request timeout in seconds
     */
    private int $timeout;

    /**
     * Maximum retries for failed requests
     */
    private int $maxRetries;

    /**
     * Whether to enable logging
     */
    private bool $enableLogging;

    /**
     * The communication format (jsonrpc, rest, auto)
     */
    private string $format = 'auto';

    /**
     * Guzzle HTTP client for streaming
     */
    private ?Client $streamingClient = null;

    /**
     * Create a new MCPClient instance.
     *
     * @param string $serverUrl The server URL
     * @param array $headers Request headers
     * @param int $timeout Request timeout
     * @param int $maxRetries Maximum retries
     * @param bool $enableLogging Whether to enable logging
     * @param string $format The communication format (jsonrpc, rest, auto)
     */
    public function __construct(
        string $serverUrl,
        array $headers = [],
        int $timeout = 30,
        int $maxRetries = 3,
        bool $enableLogging = true,
        string $format = 'auto'
    ) {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->enableLogging = $enableLogging;
        $this->format = $format;
    }

    /**
     * List available tools via MCP protocol.
     *
     * @return array
     */
    public function listTools(): array
    {
        try {
            // 1. Initialize connection
            $initResponse = $this->sendMCPRequest('initialize', []);

            if (!$initResponse || isset($initResponse['error'])) {
                if ($this->enableLogging) {
                    Log::error('MCP initialize failed', [
                        'server' => $this->serverUrl,
                        'response' => $initResponse
                    ]);
                }
                return [];
            }

            // 2. List tools
            $toolsResponse = $this->sendMCPRequest('tools/list', []);

            if (!$toolsResponse || isset($toolsResponse['error'])) {
                if ($this->enableLogging) {
                    Log::error('MCP tools/list failed', [
                        'server' => $this->serverUrl,
                        'response' => $toolsResponse
                    ]);
                }
                return [];
            }

            $tools = $toolsResponse['tools'] ?? [];

            if ($this->enableLogging) {
                Log::info('MCP tools discovered', [
                    'server' => $this->serverUrl,
                    'tools_count' => count($tools)
                ]);
            }

            return $tools;
        } catch (\Exception $e) {
            if ($this->enableLogging) {
                Log::error('MCP listTools failed', [
                    'server' => $this->serverUrl,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Send MCP request (supports both HTTP and SSE).
     *
     * @param string $method
     * @param array $params
     * @return array|null
     * @throws ConnectionException
     */
    private function sendMCPRequest(string $method, array $params = []): ?array
    {
        if ($this->enableLogging) {
            Log::info('MCP request initiated', [
                'server' => $this->serverUrl,
                'method' => $method,
                'format' => $this->format,
                'supports_sse' => $this->supportsSSE()
            ]);
        }

        // Check if this is an SSE endpoint
        if ($this->supportsSSE()) {
            if ($this->enableLogging) {
                Log::info('Using SSE endpoint', [
                    'server' => $this->serverUrl,
                    'method' => $method
                ]);
            }
            return $this->sendSSERequest($this->buildPayload($method, $params));
        }
    
        // Handle different formats based on $this->format
        switch ($this->format) {
            case 'jsonrpc':
                if ($this->enableLogging) {
                    Log::info('Using JSON-RPC format only', [
                        'server' => $this->serverUrl,
                        'method' => $method
                    ]);
                }
                return $this->sendJsonRpcRequest($method, $params);
            
            case 'rest':
                if ($this->enableLogging) {
                    Log::info('Using REST format only', [
                        'server' => $this->serverUrl,
                        'method' => $method
                    ]);
                }
                return $this->sendRestRequest($method, $params);
            
            case 'auto':
            default:
                if ($this->enableLogging) {
                    Log::info('Using auto format (JSON-RPC first, then REST)', [
                        'server' => $this->serverUrl,
                        'method' => $method
                    ]);
                }
                
                // Try JSON-RPC first, fallback to REST
                $jsonRpcResult = $this->sendJsonRpcRequest($method, $params);
                if ($jsonRpcResult !== null) {
                    if ($this->enableLogging) {
                        Log::info('JSON-RPC request successful', [
                            'server' => $this->serverUrl,
                            'method' => $method
                        ]);
                    }
                    return $jsonRpcResult;
                }
                
                if ($this->enableLogging) {
                    Log::info('JSON-RPC failed, trying REST fallback', [
                        'server' => $this->serverUrl,
                        'method' => $method
                    ]);
                }
                
                // If JSON-RPC fails, try REST
                $restResult = $this->sendRestRequest($method, $params);
                
                if ($this->enableLogging) {
                    Log::info('REST fallback result', [
                        'server' => $this->serverUrl,
                        'method' => $method,
                        'success' => $restResult !== null
                    ]);
                }
                
                return $restResult;
        }
    }

    /**
     * Send JSON-RPC 2.0 request
     */
    private function sendJsonRpcRequest(string $method, array $params = []): ?array
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => $method,
            'params' => $params
        ];

        if ($this->enableLogging) {
            Log::info('Sending JSON-RPC request', [
                'server' => $this->serverUrl,
                'method' => $method,
                'payload_id' => $payload['id']
            ]);
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers)
            ->post($this->serverUrl, $payload);

        if ($this->enableLogging) {
            Log::info('JSON-RPC response received', [
                'server' => $this->serverUrl,
                'method' => $method,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'content_type' => $response->header('Content-Type')
            ]);
        }

        if ($response->successful()) {
            $json = $response->json();
            
            // Validate JSON-RPC response format
            if (isset($json['jsonrpc']) && isset($json['id'])) {
                if ($this->enableLogging) {
                    Log::info('Valid JSON-RPC response format', [
                        'server' => $this->serverUrl,
                        'method' => $method,
                        'response_id' => $json['id'],
                        'has_error' => isset($json['error'])
                    ]);
                }
                return $json;
            }
            
            if ($this->enableLogging) {
                Log::warning('Response successful but not JSON-RPC format', [
                    'server' => $this->serverUrl,
                    'method' => $method,
                    'response_keys' => array_keys($json)
                ]);
            }
            
            // If successful but not JSON-RPC format, return as-is
            return $json;
        }

        if ($this->enableLogging) {
            Log::error('JSON-RPC request failed', [
                'server' => $this->serverUrl,
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        }

        return null;
    }

    /**
     * Send REST request
     */
    private function sendRestRequest(string $method, array $params = []): ?array
    {
        $payload = [
            'method' => $method,
            'params' => $params
        ];

        if ($this->enableLogging) {
            Log::info('Sending REST request', [
                'server' => $this->serverUrl,
                'method' => $method
            ]);
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers)
            ->post($this->serverUrl, $payload);

        if ($this->enableLogging) {
            Log::info('REST response received', [
                'server' => $this->serverUrl,
                'method' => $method,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'content_type' => $response->header('Content-Type')
            ]);
        }

        if ($response->successful()) {
            if ($this->enableLogging) {
                Log::info('REST request successful', [
                    'server' => $this->serverUrl,
                    'method' => $method
                ]);
            }
            return $response->json();
        }

        if ($this->enableLogging) {
            Log::error('REST request failed', [
                'server' => $this->serverUrl,
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        }

        return null;
    }

    /**
     * Build payload based on communication format
     */
    private function buildPayload(string $method, array $params = []): array
    {
        switch ($this->format) {
            case 'jsonrpc':
                return [
                    'jsonrpc' => '2.0',
                    'id' => uniqid(),
                    'method' => $method,
                    'params' => $params
                ];
            case 'rest':
                return [
                    'method' => $method,
                    'params' => $params
                ];
            case 'auto':
            default:
                // For SSE, use JSON-RPC format as default
                return [
                    'jsonrpc' => '2.0',
                    'id' => uniqid(),
                    'method' => $method,
                    'params' => $params
                ];
        }
    }

    /**
     * Send request via Server-Sent Events.
     */
    private function sendSSERequest(array $payload): ?array
    {
        // ✅ NUEVO: Para SSE, usar formato simple (no JSON-RPC 2.0 completo)
        $ssePayload = [
            'method' => $payload['method'],
        ];
        
        // Solo agregar params si existen y no están vacíos
        if (!empty($payload['params'])) {
            $ssePayload['params'] = $payload['params'];
        }

        if ($this->enableLogging) {
            Log::info('Starting SSE request with multiple fallbacks', [
                'server' => $this->serverUrl,
                'method' => $payload['method'] ?? 'unknown',
                'sse_payload' => $ssePayload
            ]);
        }

        // ✅ ESTRATEGIA 1: SSE real (GET con query params) - Como MCP Inspector
        $result = $this->trySSEConnection($ssePayload);
        if ($result !== null) {
            return $result;
        }

        // ✅ ESTRATEGIA 2: SSE POST con formato simple
        $result = $this->trySSEPost($ssePayload, false);
        if ($result !== null) {
            return $result;
        }

        // ✅ ESTRATEGIA 3: SSE POST con JSON-RPC completo
        $result = $this->trySSEPost($payload, true);
        if ($result !== null) {
            return $result;
        }

        // ✅ ESTRATEGIA 4: HTTP JSON-RPC fallback
        $result = $this->tryHttpJsonRpc($payload);
        if ($result !== null) {
            return $result;
        }

        if ($this->enableLogging) {
            Log::error('All SSE strategies failed', [
                'server' => $this->serverUrl,
                'method' => $payload['method'] ?? 'unknown'
            ]);
        }

        return null;
    }

    /**
     * ESTRATEGIA 1: SSE real con GET request (como MCP Inspector)
     */
    private function trySSEConnection(array $ssePayload): ?array
    {
        try {
            if ($this->enableLogging) {
                Log::info('Trying SSE GET connection', [
                    'server' => $this->serverUrl,
                    'method' => $ssePayload['method']
                ]);
            }

            if (!$this->streamingClient) {
                $this->streamingClient = new Client([
                    'timeout' => $this->timeout,
                    'headers' => [
                        'Accept' => 'text/event-stream',
                        'Cache-Control' => 'no-cache',
                        'Connection' => 'keep-alive'
                    ]
                ]);
            }

            // Enviar como query parameters
            $response = $this->streamingClient->get($this->serverUrl, [
                'query' => [
                    'message' => json_encode($ssePayload),
                    'method' => $ssePayload['method']
                ],
                'stream' => true
            ]);

            if ($response->getStatusCode() !== 200) {
                if ($this->enableLogging) {
                    Log::warning('SSE GET failed', [
                        'server' => $this->serverUrl,
                        'status' => $response->getStatusCode()
                    ]);
                }
                return null;
            }

            $result = $this->parseSSEStream($response->getBody(), $ssePayload['method']);
            
            if ($this->enableLogging) {
                Log::info('SSE GET result', [
                    'server' => $this->serverUrl,
                    'method' => $ssePayload['method'],
                    'success' => $result !== null
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            if ($this->enableLogging) {
                Log::warning('SSE GET connection failed', [
                    'server' => $this->serverUrl,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * ESTRATEGIA 2 & 3: SSE POST requests
     */
    private function trySSEPost(array $payload, bool $useFullJsonRpc = false): ?array
    {
        try {
            $strategy = $useFullJsonRpc ? 'SSE POST with full JSON-RPC' : 'SSE POST with simple format';
            
            if ($this->enableLogging) {
                Log::info("Trying {$strategy}", [
                    'server' => $this->serverUrl,
                    'method' => $payload['method']
                ]);
            }

            $headers = [
                'Accept' => 'text/event-stream',
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache'
            ];

            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->post($this->serverUrl, $payload);

            if (!$response->successful()) {
                if ($this->enableLogging) {
                    Log::warning("{$strategy} failed", [
                        'server' => $this->serverUrl,
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 200)
                    ]);
                }
                return null;
            }

            // Check content type
            $contentType = $response->header('Content-Type') ?: '';
            
            if (str_contains($contentType, 'application/json')) {
                // Direct JSON response
                $result = $response->json();
                
                if ($this->enableLogging) {
                    Log::info("{$strategy} success (JSON)", [
                        'server' => $this->serverUrl,
                        'method' => $payload['method']
                    ]);
                }
                
                return $result;
            }

            if (str_contains($contentType, 'text/event-stream') || str_contains($contentType, 'text/plain')) {
                // SSE format response
                $result = $this->parseSSEResponse($response->body(), $payload['method']);
                
                if ($this->enableLogging) {
                    Log::info("{$strategy} success (SSE)", [
                        'server' => $this->serverUrl,
                        'method' => $payload['method']
                    ]);
                }
                
                return $result;
            }

            if ($this->enableLogging) {
                Log::warning("{$strategy} unexpected content type", [
                    'server' => $this->serverUrl,
                    'content_type' => $contentType
                ]);
            }

            return null;

        } catch (\Exception $e) {
            if ($this->enableLogging) {
                Log::warning("{$strategy} failed", [
                    'server' => $this->serverUrl,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * ESTRATEGIA 4: HTTP JSON-RPC fallback
     */
    private function tryHttpJsonRpc(array $payload): ?array
    {
        try {
            if ($this->enableLogging) {
                Log::info('Trying HTTP JSON-RPC fallback', [
                    'server' => $this->serverUrl,
                    'method' => $payload['method']
                ]);
            }

            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->post($this->serverUrl, $payload);

            if ($response->successful()) {
                $result = $response->json();
                
                if ($this->enableLogging) {
                    Log::info('HTTP JSON-RPC fallback success', [
                        'server' => $this->serverUrl,
                        'method' => $payload['method']
                    ]);
                }
                
                return $result;
            }

            if ($this->enableLogging) {
                Log::warning('HTTP JSON-RPC fallback failed', [
                    'server' => $this->serverUrl,
                    'status' => $response->status()
                ]);
            }

            return null;

        } catch (\Exception $e) {
            if ($this->enableLogging) {
                Log::warning('HTTP JSON-RPC fallback failed', [
                    'server' => $this->serverUrl,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Parse SSE stream (for real SSE connections)
     */
    private function parseSSEStream($body, string $method): ?array
    {
        $result = null;
        $timeout = time() + $this->timeout;

        while (!$body->eof() && time() < $timeout) {
            $line = trim($body->read(1024));
            
            if (empty($line)) {
                usleep(10000); // 10ms
                continue;
            }

            // Parse SSE format
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                
                if ($data === '[DONE]') {
                    break;
                }
                
                $decoded = json_decode($data, true);
                if ($decoded !== null) {
                    $result = $decoded;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Parse SSE response (for HTTP responses in SSE format)
     */
    private function parseSSEResponse(string $body, string $method): ?array
    {
        $lines = explode("\n", $body);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                
                if ($data && $data !== '[DONE]') {
                    $decoded = json_decode($data, true);
                    if ($decoded !== null) {
                        return $decoded;
                    }
                }
            }
        }

        // If no SSE format found, try to parse entire body as JSON
        $decoded = json_decode($body, true);
        return $decoded;
    }

    /**
     * Check if server supports SSE.
     *
     * @return bool
     */
    public function supportsSSE(): bool
    {
        $hasSseUrl = str_contains($this->serverUrl, '/sse');
        $hasSseHeaders = isset($this->headers['Accept']) && 
                        str_contains($this->headers['Accept'], 'text/event-stream');
        $forceJsonRpc = isset($this->headers['X-Force-JSON-RPC']) && 
                       $this->headers['X-Force-JSON-RPC'] === 'true';
        
        if ($this->enableLogging) {
            Log::debug('SSE support check', [
                'server' => $this->serverUrl,
                'has_sse_url' => $hasSseUrl,
                'has_sse_headers' => $hasSseHeaders,
                'force_jsonrpc' => $forceJsonRpc,
                'result' => !$forceJsonRpc && ($hasSseUrl || $hasSseHeaders)
            ]);
        }
        
        // Si se fuerza JSON-RPC, no usar SSE
        if ($forceJsonRpc) {
            return false;
        }
        
        return $hasSseUrl || $hasSseHeaders;
    }

    /**
     * Get the server URL.
     *
     * @return string
     */
    public function getServerUrl(): string
    {
        return $this->serverUrl;
    }

    /**
     * Get request headers.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set request headers.
     *
     * @param array $headers The headers to set
     * @return self
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Add a single header.
     *
     * @param string $key The header key
     * @param string $value The header value
     * @return self
     */
    public function addHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Get server capabilities.
     *
     * @return array
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Set server capabilities.
     *
     * @param array $capabilities The server capabilities
     * @return self
     */
    public function setCapabilities(array $capabilities): self
    {
        $this->capabilities = $capabilities;
        return $this;
    }

    /**
     * Test connection to the MCP server.
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $initResponse = $this->sendMCPRequest('initialize', []);
        
            if ($this->enableLogging) {
                Log::info('MCP connection test', [
                    'server' => $this->serverUrl,
                    'success' => $initResponse !== null && !isset($initResponse['error'])
                ]);
            }
    
            return $initResponse !== null && !isset($initResponse['error']);
        } catch (Exception $e) {
            if ($this->enableLogging) {
                Log::error('MCP connection test failed', [
                    'server' => $this->serverUrl,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }

    /**
     * Discover server resources.
     *
     * @return array
     */
    public function discoverResources(): array
    {
        try {
            $resourcesResponse = $this->sendMCPRequest('resources/list', []);
        
            if ($this->enableLogging) {
                Log::info('MCP resource discovery', [
                    'server' => $this->serverUrl,
                    'success' => $resourcesResponse !== null && !isset($resourcesResponse['error']),
                    'resources_count' => count($resourcesResponse['resources'] ?? [])
                ]);
            }
    
            if ($resourcesResponse && !isset($resourcesResponse['error'])) {
                return $resourcesResponse['resources'] ?? [];
            }

            return [];
        } catch (Exception $e) {
            if ($this->enableLogging) {
                Log::error('MCP resource discovery failed', [
                    'server' => $this->serverUrl,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Call a resource on the MCP server.
     *
     * @param string $resourceName The resource name
     * @param array $parameters The resource parameters
     * @return array
     */
    public function callResource(string $resourceName, array $parameters = []): array
    {
        $retries = 0;
        $lastError = null;

        while ($retries <= $this->maxRetries) {
            try {
                $response = $this->sendMCPRequest('resources/call', [
                    'name' => $resourceName,
                    'arguments' => $parameters
                ]);
    
                if ($this->enableLogging) {
                    Log::info('MCP resource call', [
                        'server' => $this->serverUrl,
                        'resource' => $resourceName,
                        'parameters' => $parameters,
                        'success' => $response !== null && !isset($response['error'])
                    ]);
                }
    
                if ($response && !isset($response['error'])) {
                    return $response['result'] ?? $response;
                }
    
                $lastError = $response['error'] ?? 'Unknown error';
                $retries++;

                if ($retries <= $this->maxRetries) {
                    // Wait before retrying (exponential backoff)
                    sleep(pow(2, $retries - 1));
                }
            } catch (Exception $e) {
                $lastError = 'Network error: ' . $e->getMessage();
                $retries++;

                if ($this->enableLogging) {
                    Log::error('MCP resource call failed', [
                        'server' => $this->serverUrl,
                        'resource' => $resourceName,
                        'retry' => $retries,
                        'error' => $e->getMessage()
                    ]);
                }

                if ($retries <= $this->maxRetries) {
                    sleep(pow(2, $retries - 1));
                }
            }
        }

        return [
            'error' => $lastError,
            'status' => 'error'
        ];
    }

    /**
     * Get server information.
     *
     * @return array
     */
    public function getServerInfo(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get($this->serverUrl . '/info');

            if ($this->enableLogging) {
                Log::info('MCP server info', [
                    'server' => $this->serverUrl,
                    'status' => $response->status(),
                    'success' => $response->successful()
                ]);
            }

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [];
        } catch (Exception $e) {
            if ($this->enableLogging) {
                Log::error('MCP server info failed', [
                    'server' => $this->serverUrl,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Validate server capabilities.
     *
     * @param array $requiredCapabilities The required capabilities
     * @return array Array of missing capabilities
     */
    public function validateCapabilities(array $requiredCapabilities): array
    {
        $serverInfo = $this->getServerInfo();
        $serverCapabilities = $serverInfo['capabilities'] ?? [];
        $missingCapabilities = [];

        foreach ($requiredCapabilities as $capability) {
            if (!in_array($capability, $serverCapabilities)) {
                $missingCapabilities[] = $capability;
            }
        }

        return $missingCapabilities;
    }

    /**
     * Check if the server supports a specific capability.
     *
     * @param string $capability The capability to check
     * @return bool
     */
    public function supportsCapability(string $capability): bool
    {
        $serverInfo = $this->getServerInfo();
        $serverCapabilities = $serverInfo['capabilities'] ?? [];
        return in_array($capability, $serverCapabilities);
    }

    /**
     * Get server statistics.
     *
     * @return array
     */
    public function getServerStats(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get($this->serverUrl . '/stats');

            if ($this->enableLogging) {
                Log::info('MCP server stats', [
                    'server' => $this->serverUrl,
                    'status' => $response->status(),
                    'success' => $response->successful()
                ]);
            }

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [];
        } catch (Exception $e) {
            if ($this->enableLogging) {
                Log::error('MCP server stats failed', [
                    'server' => $this->serverUrl,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Enable or disable logging.
     *
     * @param bool $enable Whether to enable logging
     * @return self
     */
    public function setLogging(bool $enable): self
    {
        $this->enableLogging = $enable;
        return $this;
    }

    /**
     * Set request timeout.
     *
     * @param int $timeout The timeout in seconds
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set maximum retries.
     *
     * @param int $maxRetries The maximum number of retries
     * @return self
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /**
     * Convert the client to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'server_url' => $this->serverUrl,
            'headers' => $this->headers,
            'capabilities' => $this->capabilities,
            'timeout' => $this->timeout,
            'max_retries' => $this->maxRetries,
            'enable_logging' => $this->enableLogging,
        ];
    }

    /**
     * Stream a resource with Server-Sent Events (SSE).
     *
     * @param string $resourceName The resource name
     * @param array $parameters The resource parameters
     * @return iterable
     * @throws Exception
     */
    public function streamResource(string $resourceName, array $parameters = []): iterable
    {
        if (!$this->streamingClient) {
            $this->streamingClient = new Client([
                'timeout' => 0, // No timeout for streaming
                'headers' => array_merge($this->headers, [
                    'Accept' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                ])
            ]);
        }

        try {
            $response = $this->streamingClient->post($this->serverUrl . '/stream', [
                RequestOptions::JSON => [
                    'resource' => $resourceName,
                    'parameters' => $parameters
                ],
                RequestOptions::STREAM => true,
            ]);

            $body = $response->getBody();

            if ($this->enableLogging) {
                Log::info('MCP SSE stream started', [
                    'server' => $this->serverUrl,
                    'resource' => $resourceName,
                    'parameters' => $parameters
                ]);
            }

            while (!$body->eof()) {
                $line = trim($body->read(1024));

                if (empty($line)) {
                    continue;
                }

                // Parse SSE format
                if (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6); // Remove 'data: ' prefix

                    if ($data === '[DONE]') {
                        if ($this->enableLogging) {
                            Log::info('MCP SSE stream completed');
                        }
                        break;
                    }

                    $decoded = json_decode($data, true);
                    if ($decoded !== null) {
                        yield $decoded;
                    }
                }
            }
        } catch (Exception $e) {
            if ($this->enableLogging) {
                Log::error('MCP SSE stream failed', [
                    'server' => $this->serverUrl,
                    'resource' => $resourceName,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }

    /**
     * Subscribe to server events via SSE.
     *
     * @param string $eventType The event type to subscribe to
     * @param array $filters Optional filters for the events
     * @return iterable
     * @throws Exception|GuzzleException
     */
    public function subscribeToEvents(string $eventType, array $filters = []): iterable
    {
        if (!$this->streamingClient) {
            $this->streamingClient = new Client([
                'timeout' => 0,
                'headers' => array_merge($this->headers, [
                    'Accept' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                ])
            ]);
        }

        try {
            $response = $this->streamingClient->get($this->serverUrl . '/events', [
                RequestOptions::QUERY => array_merge([
                    'event_type' => $eventType
                ], $filters),
                RequestOptions::STREAM => true,
            ]);

            $body = $response->getBody();

            if ($this->enableLogging) {
                Log::info('MCP event subscription started', [
                    'server' => $this->serverUrl,
                    'event_type' => $eventType,
                    'filters' => $filters
                ]);
            }

            while (!$body->eof()) {
                $line = trim($body->read(1024));

                if (empty($line)) {
                    continue;
                }

                // Parse SSE format
                if (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6);

                    if ($data === '[DONE]') {
                        if ($this->enableLogging) {
                            Log::info('MCP event subscription ended');
                        }
                        break;
                    }

                    $decoded = json_decode($data, true);
                    if ($decoded !== null) {
                        yield $decoded;
                    }
                }
            }
        } catch (Exception $e) {
            if ($this->enableLogging) {
                Log::error('MCP event subscription failed', [
                    'server' => $this->serverUrl,
                    'event_type' => $eventType,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }

    /**
     * Stream resource with callback.
     *
     * @param string $resourceName The resource name
     * @param array $parameters The resource parameters
     * @param callable|null $callback The callback function
     * @return void
     * @throws GuzzleException
     * @throws Exception
     */
    public function streamResourceWithCallback(string $resourceName, array $parameters = [], ?callable $callback = null): void
    {
        try {
            $streamingClient = $this->getStreamingClient();

            $response = $streamingClient->get($this->serverUrl . '/stream/' . $resourceName, [
                'headers' => $this->headers,
                'query' => $parameters,
                'stream' => true,
                'timeout' => $this->timeout
            ]);

            $body = $response->getBody();

            while (!$body->eof()) {
                $chunk = $body->read(1024);
                if ($chunk && $callback !== null) {
                    $callback($chunk);
                }
            }

            if ($this->enableLogging) {
                Log::info('MCP resource streaming completed', [
                    'server' => $this->serverUrl,
                    'resource' => $resourceName,
                    'parameters' => $parameters
                ]);
            }

        } catch (Exception $e) {
            if ($this->enableLogging) {
                Log::error('MCP resource streaming failed', [
                    'server' => $this->serverUrl,
                    'resource' => $resourceName,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }

    /*

    public function supportsSSE(): bool
    {
        try {
            $serverInfo = $this->getServerInfo();
            $capabilities = $serverInfo['capabilities'] ?? [];

            return in_array('sse', $capabilities) || in_array('streaming', $capabilities);
        } catch (Exception $e) {
            return false;
        }
    }
    */

    /**
     * Get streaming client instance.
     *
     * @return Client|null
     */
    public function getStreamingClient(): ?Client
    {
        return $this->streamingClient;
    }

    /**
     * Set streaming client.
     *
     * @param Client $client The streaming client
     * @return self
     */
    public function setStreamingClient(Client $client): self
    {
        $this->streamingClient = $client;
        return $this;
    }
}
