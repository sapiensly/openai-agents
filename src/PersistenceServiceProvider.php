<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents;

use Illuminate\Support\ServiceProvider;
use Sapiensly\OpenaiAgents\Persistence\Contracts\ConversationStore;
use Sapiensly\OpenaiAgents\Persistence\Strategies\ContextStrategy;
use Sapiensly\OpenaiAgents\Persistence\Strategies\RecentMessagesStrategy;
use Sapiensly\OpenaiAgents\Persistence\Stores\NullStore;

class PersistenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge default config
        $this->mergeConfigFrom(__DIR__ . '/../config/agent-persistence.php', 'agent-persistence');

        // Bind ConversationStore based on config; default to NullStore to avoid dependencies
        $this->app->singleton(ConversationStore::class, function ($app) {
            $default = config('agent-persistence.default', 'null');
            switch ($default) {
                case 'database':
                    // If DatabaseStore exists, bind it; otherwise fallback to NullStore
                    if (class_exists('Sapiensly\\OpenaiAgents\\Persistence\\Stores\\DatabaseStore')) {
                        return $app->make('Sapiensly\\OpenaiAgents\\Persistence\\Stores\\DatabaseStore');
                    }
                    return new NullStore();
                case 'cache':
                    if (class_exists('Sapiensly\\OpenaiAgents\\Persistence\\Stores\\CacheStore')) {
                        return $app->make('Sapiensly\\OpenaiAgents\\Persistence\\Stores\\CacheStore');
                    }
                    return new NullStore();
                case 'null':
                default:
                    return new NullStore();
            }
        });

        // Bind ContextStrategy
        $this->app->singleton(ContextStrategy::class, function ($app) {
            $strategy = config('agent-persistence.context.strategy');
            if (is_string($strategy) && class_exists($strategy)) {
                return $app->make($strategy);
            }
            return new RecentMessagesStrategy();
        });
    }

    public function boot(): void
    {
        // Publish config and (optionally) migrations
        $this->publishes([
            __DIR__ . '/../config/agent-persistence.php' => config_path('agent-persistence.php'),
        ], 'agent-persistence-config');

        $migration = __DIR__ . '/../database/migrations/2024_01_01_000000_create_agent_conversations_tables.php';
        if (file_exists($migration)) {
            $this->publishes([
                $migration => database_path('migrations/2024_01_01_000000_create_agent_conversations_tables.php'),
            ], 'agent-persistence-migrations');
        }
    }
}
