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
use RuntimeException;

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
     * Configurable endpoint paths
     */
    private array $paths = [
        'health' => '/health',
        'resources' => '/resources',
        'call' => '/call',
        'info' => '/info',
        'stats' => '/stats',
        'stream' => '/stream',
        'events' => '/events',
    ];

    /**
     * Full stream URL override (use as-is if set)
     */
    private ?string $fullStreamUrl = null;

    /**
     * Streaming HTTP method (GET or POST)
     */
    private string $streamMethod = 'POST';

    /**
     * Whether to send JSON body on streaming requests (POST) or use query params (GET)
     */
    private bool $streamSendJsonBody = true;

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
     * @param int|null $timeout Request timeout
     * @param int|null $maxRetries Maximum retries
     * @param bool $enableLogging Whether to enable logging
     */
    public function __construct(
        string   $serverUrl,
        array    $headers = [],
        int|null $timeout = null,
        int|null $maxRetries = null,
        bool     $enableLogging = false
    ) {
        $timeout ??= 30;
        $maxRetries ??= 3;
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
     * Set configurable endpoint paths.
     */
    public function setPaths(array $paths): self
    {
        // Merge while preserving keys
        $this->paths = array_merge($this->paths, $paths);
        return $this;
    }

    /**
     * Set full stream URL (override base + path)
     */
    public function setFullStreamUrl(?string $url): self
    {
        $this->fullStreamUrl = $url ? rtrim($url, '/') : null;
        return $this;
    }

    /**
     * Set streaming method (GET|POST)
     */
    public function setStreamMethod(string $method): self
    {
        $method = strtoupper($method);
        if (in_array($method, ['GET','POST'])) {
            $this->streamMethod = $method;
        }
        return $this;
    }

    /**
     * Set whether to send JSON body for stream requests
     */
    public function setStreamSendJsonBody(bool $flag): self
    {
        $this->streamSendJsonBody = $flag;
        return $this;
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
                ->get($this->serverUrl . ($this->paths['health'] ?? '/health'));

            if ($this->enableLogging) {
                Log::info('MCP connection test', [
                    'server' => $this->serverUrl,
                    'status' => $response->status(),
                    'success' => $response->successful()
                ]);
            }

            return $response->successful();
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
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get($this->serverUrl . ($this->paths['resources'] ?? '/resources'));

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
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->headers)
                    ->post($this->serverUrl . ($this->paths['call'] ?? '/call'), [
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
                ->get($this->serverUrl . ($this->paths['info'] ?? '/info'));

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
                ->get($this->serverUrl . ($this->paths['stats'] ?? '/stats'));

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
     * List MCP tools via JSON-RPC (tools/list).
     *
     * @return array
     */
    public function listTools(): array
    {
        try {
            $payload = [
                'jsonrpc' => '2.0',
                'id' => 'tools-list-' . uniqid(),
                'method' => 'tools/list',
                'params' => []
            ];

            $resp = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->post($this->serverUrl, $payload);

            if (!$resp->successful()) {
                if ($this->enableLogging) {
                    Log::warning('MCP listTools non-success response', [
                        'server' => $this->serverUrl,
                        'status' => $resp->status(),
                        'body' => $resp->body(),
                    ]);
                }
                return [];
            }

            $json = $resp->json() ?? [];
            if (isset($json['error'])) {
                if ($this->enableLogging) {
                    Log::warning('MCP listTools JSON-RPC error', [
                        'server' => $this->serverUrl,
                        'error' => $json['error'],
                    ]);
                }
                return [];
            }

            $result = $json['result'] ?? [];
            return $result['tools'] ?? [];
        } catch (Exception $e) {
            if ($this->enableLogging) {
                Log::warning('MCP listTools failed', [
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
            $url = $this->fullStreamUrl ?? ($this->serverUrl . ($this->paths['stream'] ?? '/stream'));
            if (strtoupper($this->streamMethod) === 'GET') {
                $response = $this->streamingClient->get($url, [
                    RequestOptions::QUERY => array_merge([
                        'resource' => $resourceName,
                    ], $parameters),
                    RequestOptions::STREAM => true,
                ]);
            } else {
                $response = $this->streamingClient->post($url, [
                    RequestOptions::JSON => $this->streamSendJsonBody ? [
                        'resource' => $resourceName,
                        'parameters' => $parameters
                    ] : null,
                    RequestOptions::QUERY => $this->streamSendJsonBody ? null : array_merge([
                        'resource' => $resourceName,
                    ], $parameters),
                    RequestOptions::STREAM => true,
                ]);
            }

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
     * @throws GuzzleException
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
            $response = $this->streamingClient->get($this->serverUrl . ($this->paths['events'] ?? '/events'), [
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
     * Stream resource with callback for real-time processing.
     *
     * @param string $resourceName The resource name
     * @param array|null $parameters The resource parameters
     * @param callable|null $callback Callback function for each chunk
     * @return void
     * @throws Exception
     */
    public function streamResourceWithCallback(string $resourceName, array|null $parameters = null, callable|null $callback = null): void
    {
        $parameters ??= [];
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
        } catch (Exception $e) {
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


    /**
     * Performs a debugging operation by probing various endpoints and returns the report.
     *
     * @param array $options Optional settings for customizing the debug process:
     *                       - include_headers (bool): Whether to include request/response headers in the report. Default is true.
     *                       - max_body (int): Maximum length for response bodies before truncation. Default is 2000 characters.
     *                       - probe (array): List of probes to execute (e.g., 'health', 'info', 'resources', 'stats'). Default contains all.
     *                       - probe_stream (bool): Whether to execute stream-based probing. Default is false.
     *                       - stream_max_events (int): Maximum number of events to capture during stream-based probing. Default is 3.
     * @return array The generated debug report containing details of each probe, including requests, responses, durations, and errors.
     * @throws RuntimeException|ConnectionException If an unsupported HTTP method is encountered during probing.
     */

    public function debug(array $options = []): array
    {
        $includeHeaders = (bool)($options['include_headers'] ?? true);
        $maxBody = (int)($options['max_body'] ?? 2000);
        $probe = $options['probe'] ?? ['tools','resources','capabilities'];
        $probeStream = (bool)($options['probe_stream'] ?? false);
        $streamMaxEvents = (int)($options['stream_max_events'] ?? 3);

        $report = [
            'server_url' => $this->serverUrl,
            'stream' => [
                'full_stream_url' => $this->fullStreamUrl,
                'method' => $this->streamMethod,
                'send_json_body' => $this->streamSendJsonBody,
            ],
            'probes' => [],
        ];

        $http = fn() => \Illuminate\Support\Facades\Http::timeout($this->timeout)->withHeaders($this->headers);

        // JSON-RPC debug method
        $doJSONRPC = function (string $label, string $method, array $params = []) use (&$report, $http, $includeHeaders, $maxBody) {
            $start = microtime(true);
            try {
                $payload = [
                    'jsonrpc' => '2.0',
                    'id' => uniqid(),
                    'method' => $method,
                    'params' => $params
                ];

                $reqInfo = [
                    'method' => 'POST',
                    'url' => $this->serverUrl,
                    'jsonrpc_method' => $method,
                    'jsonrpc_params' => $params,
                ];
                if ($includeHeaders) { $reqInfo['headers'] = $this->headers; }

                $response = $http()->post($this->serverUrl, $payload);
                $durationMs = (int) ((microtime(true) - $start) * 1000);

                $body = $response->body();
                $jsonResponse = $response->json();

                $report['probes'][$label] = [
                    'request' => $reqInfo,
                    'response' => [
                        'status' => $response->status(),
                        'ok' => $response->successful(),
                        'headers' => $includeHeaders ? $response->headers() : null,
                        'jsonrpc_response' => $jsonResponse,
                        'has_result' => isset($jsonResponse['result']),
                        'has_error' => isset($jsonResponse['error']),
                        'body' => strlen($body) > $maxBody ? substr($body, 0, $maxBody) . '...<truncated>' : $body,
                    ],
                    'duration_ms' => $durationMs,
                ];
            } catch (\Throwable $e) {
                $durationMs = (int) ((microtime(true) - $start) * 1000);
                $report['probes'][$label] = [
                    'request' => [ 'method' => 'POST', 'url' => $this->serverUrl, 'jsonrpc_method' => $method ],
                    'error' => $e->getMessage(),
                    'duration_ms' => $durationMs,
                ];
            }
        };

        // Tools list (MCP standard)
        if (in_array('tools', $probe, true)) {
            $doJSONRPC('tools_list', 'tools/list', []);
        }

        // Resources list (MCP standard)
        if (in_array('resources', $probe, true)) {
            $doJSONRPC('resources_list', 'resources/list', []);
        }

        // Capabilities (MCP standard)
        if (in_array('capabilities', $probe, true)) {
            $doJSONRPC('initialize', 'initialize', [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [],
                'clientInfo' => ['name' => 'MCP-Debug-Client', 'version' => '1.0.0']
            ]);
        }

        // Streaming probe (SSE)
        if ($probeStream && $this->fullStreamUrl) {
            $streamInfo = [
                'request' => [
                    'method' => $this->streamMethod,
                    'url' => $this->fullStreamUrl,
                    'headers' => $includeHeaders ? array_merge($this->headers, [
                        'Accept' => 'text/event-stream',
                        'Cache-Control' => 'no-cache'
                    ]) : null,
                ],
            ];

            $start = microtime(true);
            try {
                // Debug streaming con timeout seguro
                $events = $this->debugStreamingSafe($this->fullStreamUrl, 5, $streamMaxEvents);
                $streamInfo['response'] = [
                    'ok' => true,
                    'events_captured' => count($events),
                    'events' => $events,
                ];
            } catch (\Throwable $e) {
                $streamInfo['error'] = $e->getMessage();
            } finally {
                $streamInfo['duration_ms'] = (int) ((microtime(true) - $start) * 1000);
            }

            $report['probes']['sse_stream'] = $streamInfo;
        }

        return $report;
    }

    /**
     * Debug streaming de forma segura con timeout
     */
    private function debugStreamingSafe(string $url, int $timeoutSec, int $maxEvents): array
    {
        $events = [];
        $startTime = time();

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", [
                        'Accept: text/event-stream',
                        'Cache-Control: no-cache',
                        'User-Agent: MCP-Debug-Client/1.0'
                    ]),
                    'timeout' => $timeoutSec
                ]
            ]);

            $stream = fopen($url, 'r', false, $context);
            if (!$stream) {
                throw new \Exception('Failed to open SSE stream');
            }

            stream_set_timeout($stream, 1); // 1 segundo por línea

            while (!feof($stream) && count($events) < $maxEvents) {
                if ((time() - $startTime) >= $timeoutSec) {
                    $events[] = '[TIMEOUT] Reached ' . $timeoutSec . 's timeout';
                    break;
                }

                $line = fgets($stream);
                if ($line !== false) {
                    $trimmed = trim($line);
                    if (!empty($trimmed) && $trimmed !== ':') {
                        $events[] = $trimmed;
                    }
                }
            }

            fclose($stream);
        } catch (\Throwable $e) {
            $events[] = '[ERROR] ' . $e->getMessage();
        }

        return $events;
    }
}
