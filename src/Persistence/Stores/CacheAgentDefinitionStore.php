<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Stores;

use Illuminate\Support\Facades\Cache;
use Sapiensly\OpenaiAgents\Persistence\Contracts\AgentDefinitionStore;

/**
 * Cache-based store for agent definitions using Laravel's cache system.
 */
class CacheAgentDefinitionStore implements AgentDefinitionStore
{
    protected string $prefix;
    protected int $ttl;

    public function __construct()
    {
        $this->prefix = config('sapiensly-openai-agents.definitions.stores.cache.prefix', 'agent_def:');
        $this->ttl = config('sapiensly-openai-agents.definitions.stores.cache.ttl', 86400);
    }


    public function save(string $agentId, array $definition): void
    {
        $key = $this->getCacheKey($agentId);
        Cache::put($key, $definition, $this->ttl);
    }

    public function load(string $agentId): ?array
    {
        $key = $this->getCacheKey($agentId);
        return Cache::get($key);
    }

    public function delete(string $agentId): void
    {
        $key = $this->getCacheKey($agentId);
        Cache::forget($key);
    }

    /**
     * List all saved agent definitions.
     */
    public function list(): array
    {
        // Note: This is a simplified implementation
        // In production, you might want to use Redis SCAN or maintain a separate index
        return [];
    }

    protected function getCacheKey(string $agentId): string
    {
        return $this->prefix . md5($agentId);
    }

    public function retrieve(string $name): ?array
    {
        return Cache::get($this->prefix . $name);
    }

    public function exists(string $name): bool
    {
        return Cache::has($this->prefix . $name);
    }

    public function listAll(): array
    {
        // Cache doesn't provide a good way to list all keys with a prefix
        // This is a limitation of cache-based storage
        return [];
    }

}
