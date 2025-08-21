<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents;

use Illuminate\Support\ServiceProvider;
use Sapiensly\OpenaiAgents\Persistence\Contracts\AgentDefinitionStore;
use Sapiensly\OpenaiAgents\Persistence\Stores\CacheAgentDefinitionStore;
use Sapiensly\OpenaiAgents\Persistence\Stores\DatabaseAgentDefinitionStore;
use Sapiensly\OpenaiAgents\Persistence\Stores\NullAgentDefinitionStore;

class AgentDefinitionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgentDefinitionStore::class, function ($app) {
            $store = config('sapiensly-openai-agents.definitions.store', 'null');

            switch ($store) {
                case 'database':
                    if (class_exists(DatabaseAgentDefinitionStore::class)) {
                        return $app->make(DatabaseAgentDefinitionStore::class);
                    }
                    return new NullAgentDefinitionStore();

                case 'cache':
                    if (class_exists(CacheAgentDefinitionStore::class)) {
                        return $app->make(CacheAgentDefinitionStore::class);
                    }
                    return new NullAgentDefinitionStore();

                case 'null':
                default:
                    return new NullAgentDefinitionStore();
            }
        });

        /*
        // Merge default config
        $this->mergeConfigFrom(__DIR__ . '/../config/agent-definitions.php', 'agent-definitions');

        // Bind AgentDefinitionStore based on config; default to DatabaseAgentDefinitionStore
        $this->app->singleton(AgentDefinitionStore::class, function ($app) {
            $enabled = config('agent-definitions.enabled', true);
            if (!$enabled) {
                return new NullAgentDefinitionStore();
            }

            $store = config('agent-definitions.store', 'database');
            switch ($store) {
                case 'database':
                    return new DatabaseAgentDefinitionStore();
                case 'cache':
                    return new CacheAgentDefinitionStore();
                case 'null':
                default:
                    return new NullAgentDefinitionStore();
            }
        });
        */
    }

    public function boot(): void
    {
        /*
        // Publish config if requested
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/agent-definitions.php' => config_path('agent-definitions.php'),
            ], 'agent-definitions-config');

            // Publish migration
            $migration = __DIR__ . '/../database/migrations/2024_01_01_000001_create_agent_definitions_table.php';
            if (file_exists($migration)) {
                $this->publishes([
                    $migration => database_path('migrations/2024_01_01_000001_create_agent_definitions_table.php'),
                ], 'agent-definitions-migrations');
            }
        }
        */
    }
}
