<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\MCP;

use Sapiensly\OpenaiAgents\Agent;

/**
 * ExposeBuilder
 *
 * Fluent builder to expose discovered MCP resources as proxy tools with filters.
 */
class ExposeBuilder
{
    private Agent $agent;
    private string $serverName;
    private array $allow = [];
    private array $deny = [];
    private string $prefix = '';
    private ?string $mode = null; // 'call' | 'stream' | null (auto)

    public function __construct(Agent $agent, string $serverName)
    {
        $this->agent = $agent;
        $this->serverName = $serverName;
    }

    public function allow(array $names): self
    {
        $this->allow = $names;
        return $this;
    }

    public function deny(array $names): self
    {
        $this->deny = $names;
        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function mode(string $mode): self
    {
        $mode = strtolower($mode);
        if (in_array($mode, ['call', 'stream'])) {
            $this->mode = $mode;
        }
        return $this;
    }

    /**
    * Apply the exposure registering proxy tools on the Agent.
    */
    public function apply(): Agent
    {
        $manager = $this->agent->getMCPManager();
        if (!$manager) {
            $manager = new MCPManager();
            $this->agent->setMCPManager($manager);
        }
        $server = $manager->getServer($this->serverName);
        if (!$server) {
            throw new \RuntimeException("Server '{$this->serverName}' not found");
        }

        $resources = $server->getResources();
        foreach ($resources as $res) {
            $name = $res->getName();
            // allow filter
            if (!empty($this->allow) && !in_array($name, $this->allow, true)) {
                continue;
            }
            // deny filter (supports patterns via fnmatch if pattern contains wildcard)
            $denied = false;
            foreach ($this->deny as $pattern) {
                if ($pattern === $name || (str_contains($pattern, '*') && fnmatch($pattern, $name))) {
                    $denied = true;
                    break;
                }
            }
            if ($denied) {
                continue;
            }

            $def = [
                'server' => $this->serverName,
                'name' => ($this->prefix !== '' ? $this->prefix . $name : $name),
                'description' => $res->getDescription(),
                'uri' => $res->getUri(),
                'parameters' => $res->getParameters(),
                'schema' => $res->getSchema(),
            ];

            // determine mode
            $mode = $this->mode;
            if ($mode === null) {
                $meta = $res->getMetadata();
                if (($meta['stream'] ?? false) || str_contains($res->getUri(), '/sse') || str_contains($res->getUri(), '/stream') || $server->supportsSSE()) {
                    $mode = 'stream';
                } else {
                    $mode = 'call';
                }
            }

            // Delegate to agent's extended registerMCPTool
            $this->agent->registerMCPTool($def, $this->serverName, ['mode' => $mode]);
        }

        return $this->agent;
    }
}
