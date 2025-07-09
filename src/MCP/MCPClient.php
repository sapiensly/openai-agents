<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\MCP;

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
     */
    public function __construct(
        string $serverUrl,
        array $headers = [],
        int $timeout = 30,
        int $maxRetries = 3,
        bool $enableLogging = true
    ) {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->enableLogging = $enableLogging;
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
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get($this->serverUrl . '/health');

            if ($this->enableLogging) {
                Log::info('MCP connection test', [
                    'server' => $this->serverUrl,
                    'status' => $response->status(),
                    'success' => $response->successful()
                ]);
            }

            return $response->successful();
        } catch (\Exception $e) {
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
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get($this->serverUrl . '/resources');

            if ($this->enableLogging) {
                Log::info('MCP resource discovery', [
                    'server' => $this->serverUrl,
                    'status' => $response->status(),
                    'resources_count' => count($response->json() ?? [])
                ]);
            }

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [];
        } catch (\Exception $e) {
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
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->headers)
                    ->post($this->serverUrl . '/call', [
                        'resource' => $resourceName,
                        'parameters' => $parameters
                    ]);

                if ($this->enableLogging) {
                    Log::info('MCP resource call', [
                        'server' => $this->serverUrl,
                        'resource' => $resourceName,
                        'parameters' => $parameters,
                        'status' => $response->status(),
                        'success' => $response->successful()
                    ]);
                }

                if ($response->successful()) {
                    return $response->json() ?? [];
                }

                // If it's a client error (4xx), don't retry
                if ($response->status() >= 400 && $response->status() < 500) {
                    return [
                        'error' => 'Client error: ' . $response->body(),
                        'status' => $response->status()
                    ];
                }

                $lastError = 'Server error: ' . $response->body();
                $retries++;

                if ($retries <= $this->maxRetries) {
                    // Wait before retrying (exponential backoff)
                    sleep(pow(2, $retries - 1));
                }
            } catch (\Exception $e) {
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
            'error' => 'Max retries exceeded: ' . $lastError,
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
     * Stream resource with callback for real-time processing.
     *
     * @param string $resourceName The resource name
     * @param array $parameters The resource parameters
     * @param callable $callback Callback function for each chunk
     * @return void
     */
    public function streamResourceWithCallback(string $resourceName, array $parameters = [], callable $callback = null): void
    {
        foreach ($this->streamResource($resourceName, $parameters) as $chunk) {
            if ($callback) {
                $callback($chunk);
            }
        }
    }

    /**
     * Check if the server supports SSE streaming.
     *
     * @return bool
     */
    public function supportsSSE(): bool
    {
        try {
            $serverInfo = $this->getServerInfo();
            $capabilities = $serverInfo['capabilities'] ?? [];
            
            return in_array('sse', $capabilities) || in_array('streaming', $capabilities);
        } catch (\Exception $e) {
            return false;
        }
    }

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