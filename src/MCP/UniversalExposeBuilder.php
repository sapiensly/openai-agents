<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\MCP;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Sapiensly\OpenaiAgents\Agent;

class UniversalExposeBuilder
{
    private Agent $agent;
    private string $serverName;

    private array $allow = [];
    private array $deny = [];
    private string $prefix = '';
    private array $sources = ['tools','resources']; // 'tools' (JSON-RPC), 'resources' (REST)
    private string $mode = 'auto'; // 'auto'|'call'|'stream'

    public function __construct(Agent $agent, string $serverName)
    {
        $this->agent = $agent;
        $this->serverName = $serverName;
    }

    public function allow(array $names): self { $this->allow = array_values($names); return $this; }
    public function deny(array $names): self { $this->deny = array_values($names); return $this; }
    public function prefix(string $prefix): self { $this->prefix = $prefix; return $this; }

    /**
     * @param array $sources any subset of ['tools','resources']
     */
    public function sources(array $sources): self
    {
        $valid = ['tools','resources'];
        $this->sources = array_values(array_intersect($sources, $valid));
        if (empty($this->sources)) {
            $this->sources = ['tools','resources'];
        }
        return $this;
    }

    /**
     * @param string $mode 'auto' | 'call' | 'stream'
     */
    public function mode(string $mode): self
    {
        $mode = strtolower($mode);
        if (in_array($mode, ['auto','call','stream'], true)) {
            $this->mode = $mode;
        }
        return $this;
    }

    /**
     * @throws \ReflectionException
     */
    public function apply(): Agent
    {
        $manager = $this->agent->getMCPManager();
        if (!$manager) {
            throw new Exception('MCP Manager not initialized');
        }

        $server = $manager->getServer($this->serverName);
        if (!$server) {
            throw new Exception("MCP Server '{$this->serverName}' not found");
        }

        // 1) Tools via JSON-RPC (tools/list)
        if (in_array('tools', $this->sources, true)) {
            $tools = [];
            try {
                $tools = $server->getClient()->listTools();
            } catch (\Throwable $e) {
                // ignore if JSON-RPC not supported
            }

            foreach ($tools as $t) {
                $name = $t['name'] ?? null;
                if (!$name) { continue; }
                if (!$this->passesFilters($name)) { continue; }

                $finalName = $this->prefix . $name;
                $description = $t['description'] ?? '';
                $params = $t['inputSchema']['properties'] ?? [];

                // Tool proxy que llama tools/call por JSON-RPC
                $tool = MCPTool::withProcessor(
                    $finalName,
                    new MCPResource($name, $description, 'tool://' . $name, $params, []),
                    $server,
                    function (array $parameters) use ($server, $name) {
                        $client = $server->getClient();
                        $payload = [
                            'jsonrpc' => '2.0',
                            'id' => 'call-' . uniqid(),
                            'method' => 'tools/call',
                            'params' => [
                                'name' => $name,
                                'arguments' => $parameters
                            ]
                        ];
                        $resp = Http::timeout(30)
                            ->withHeaders($client->getHeaders())
                            ->post($client->getServerUrl(), $payload);

                        $json = $resp->json();
                        if (!$resp->successful() || isset($json['error'])) {
                            return [
                                'status' => 'error',
                                'http_status' => $resp->status(),
                                'error' => $json['error']['message'] ?? 'Unknown error calling tool',
                            ];
                        }
                        return $json['result'] ?? $json;
                    }
                );

                // Register MCPTool in the Agent (enables function_calling in chat)
                $this->agent->registerMCPTool($tool, $server->getName());
            }
        }

        // 2) Resources via REST (/resources + /call)
        if (in_array('resources', $this->sources, true)) {
            // Avoid duplicated discovery: if there are already resources or we have already attempted, do not repeat
            $resources = $server->getResources();
            $meta = $server->getMetadata();
            $alreadyAttempted = (bool)($meta['resources_discovery_attempted'] ?? false);

            if (empty($resources) && !$alreadyAttempted) {
                try {
                    $server->discoverResources();
                } catch (\Throwable $e) {
                    // ignore discovery failures
                } finally {
                    // mark as attempted to avoid future retries
                    $server->addMetadata('resources_discovery_attempted', true);
                }
                $resources = $server->getResources();
            }

            // if no resources found, skip
            if (!empty($resources)) {
                foreach ($resources as $resourceObj) {
                    // resourceObj is MCPResource
                    $name = $resourceObj->getName();
                    if (!$this->passesFilters($name)) { continue; }

                    $finalName = $this->prefix . $name;
                    $uri = $resourceObj->getUri() ?? '/';
                    $resolvedMode = $this->resolveMode($server, $uri);

                    if ($resolvedMode === 'stream') {
                        $tool = MCPTool::withProcessor(
                            $finalName,
                            $resourceObj,
                            $server,
                            function (array $parameters) use ($server, $name) {
                                $chunks = [];
                                foreach ($server->streamResource($name, $parameters) as $chunk) {
                                    $chunks[] = $chunk;
                                    if (count($chunks) >= 10) { break; } // defensivo
                                }
                                return end($chunks) ?: ($chunks[0] ?? ['message' => 'No data received']);
                            }
                        );
                    } else {
                        $tool = MCPTool::withProcessor(
                            $finalName,
                            $resourceObj,
                            $server,
                            function (array $parameters) use ($server, $name) {
                                return $server->callResource($name, $parameters);
                            }
                        );
                    }

                    // Register MCPTool in the Agent (enables function_calling in chat)
                    $this->agent->registerMCPTool($tool, $server->getName());
                }
            }
        }

        // 3) Record the exposure for persistence/hydration
        $filters = [
            'allow' => $this->allow,
            'deny' => $this->deny,
            'prefix' => $this->prefix,
            'sources' => $this->sources,
        ];
        $defaults = [ 'mode' => $this->mode ];

        // Optionally capture exposed tool names for this server after apply()
        try {
            $exposed = array_keys($this->agent->listMCPTools($this->serverName, true, true));
        } catch (\Throwable $e) {
            $exposed = [];
        }

        $this->agent->recordMCPExposure($this->serverName, $filters, $defaults, $exposed);

        return $this->agent;
    }

    private function passesFilters(string $name): bool
    {
        if (!empty($this->allow) && !$this->matchesAny($name, $this->allow)) {
            return false;
        }
        if (!empty($this->deny) && $this->matchesAny($name, $this->deny)) {
            return false;
        }
        return true;
    }

    private function matchesAny(string $name, array $patterns): bool
    {
        foreach ($patterns as $p) {
            if (fnmatch($p, $name)) { return true; }
            if (Str::contains($name, $p)) { return true; }
        }
        return false;
    }

    private function resolveMode(MCPServer $server, string $uri): string
    {
        if ($this->mode !== 'auto') {
            return $this->mode;
        }
        $isStreamy = str_contains($uri, '/sse') || str_contains($uri, '/stream');
        if ($isStreamy && $server->supportsSSE()) {
            return 'stream';
        }
        return 'call';
    }
}
