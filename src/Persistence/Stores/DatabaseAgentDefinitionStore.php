<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Stores;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Sapiensly\OpenaiAgents\Persistence\Contracts\AgentDefinitionStore;
use Sapiensly\OpenaiAgents\Persistence\Models\AgentDefinition;

class DatabaseAgentDefinitionStore implements AgentDefinitionStore
{
    protected string $cachePrefix = 'agent_def:';
    protected int $cacheTtl = 3600;
    protected bool $useTags = false;

    public function __construct()
    {
        $config = config('sapiensly-openai-agents.definitions.stores.database', []);
        $cacheConfig = $config['cache'] ?? [];
        $this->cacheTtl = $cacheConfig['ttl'] ?? 86400;
        $this->cachePrefix = $cacheConfig['prefix'] ?? 'agent_def:';
        $this->useTags = (bool) ($cacheConfig['tags'] ?? false);

    }

    public function save(string $agentId, array $definition): void
    {
        DB::transaction(function () use ($agentId, $definition) {
            $agentDefinition = AgentDefinition::updateOrCreate(
                ['name' => $agentId],
                [
                    'id' => $definition['id'] ?? $agentId,
                    'options' => $definition['options'] ?? [],
                    'instructions' => $definition['instructions'] ?? null,
                    'tools' => $definition['tools'] ?? [],
                    'metadata' => $definition['metadata'] ?? [],
                ]
            );
        });

        // Clear cache
        $this->flushCache($agentId);
    }

    public function load(string $agentId): ?array
    {
        $key = $this->cachePrefix . $agentId;
        $useTags =  $this->useTags;

        $remember = function () use ($agentId) {
            $definition = AgentDefinition::findByName($agentId);
            if (!$definition) {
                return null;
            }

            return [
                'id' => $definition->id,
                'options' => $definition->options ?? [],
                'instructions' => $definition->instructions,
                'tools' => $definition->tools ?? [],
                'metadata' => $definition->metadata ?? [],
                'created_at' => $definition->created_at?->toISOString(),
                'updated_at' => $definition->updated_at?->toISOString(),
            ];
        };

        if ($useTags && method_exists(Cache::getStore(), 'tags')) {
            return Cache::tags(['agent_definition:' . $agentId])->remember($key, $this->cacheTtl, $remember);
        }

        return Cache::remember($key, $this->cacheTtl, $remember);
    }

    public function delete(string $agentId): void
    {
        DB::transaction(function () use ($agentId) {
            AgentDefinition::where('name', $agentId)->delete();
        });

        $this->flushCache($agentId);
    }

    /**
     * List all saved agent definitions.
     */
    public function list(): array
    {
        $key = $this->cachePrefix . 'all_names';
        $useTags =  $this->useTags;

        $remember = function () {
            return AgentDefinition::getAllNames();
        };

        if ($useTags && method_exists(Cache::getStore(), 'tags')) {
            return Cache::tags(['agent_definitions'])->remember($key, $this->cacheTtl, $remember);
        }

        return Cache::remember($key, $this->cacheTtl, $remember);
    }

    protected function flushCache(string $agentId): void
    {
        $useTags =  $this->useTags;

        if ($useTags && method_exists(Cache::getStore(), 'tags')) {
            Cache::tags(['agent_definition:' . $agentId, 'agent_definitions'])->flush();
        } else {
            Cache::forget($this->cachePrefix . $agentId);
            Cache::forget($this->cachePrefix . 'all_names');
        }
    }
}
