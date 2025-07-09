<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents;

use Illuminate\Support\ServiceProvider;
use Sapiensly\OpenaiAgents\Handoff\HandoffOrchestrator;
use Sapiensly\OpenaiAgents\Metrics\MetricsCollector;
use Sapiensly\OpenaiAgents\Registry\AgentRegistry;
use Sapiensly\OpenaiAgents\Security\SecurityManager;
use Sapiensly\OpenaiAgents\State\ArrayConversationStateManager;
use Sapiensly\OpenaiAgents\State\ConversationStateManager;
use Sapiensly\OpenaiAgents\State\RedisConversationStateManager;
use Sapiensly\OpenaiAgents\Tracing\Tracing;
use Sapiensly\OpenaiAgents\Console\Commands\DateQuestionCommand;
use Sapiensly\OpenaiAgents\Console\Commands\ToolTestCommand;
use Sapiensly\OpenaiAgents\Providers\ModelProviderManager;
use Sapiensly\OpenaiAgents\Lifecycle\AgentLifecycleManager;
use Sapiensly\OpenaiAgents\Lifecycle\AgentPool;
use Sapiensly\OpenaiAgents\Lifecycle\HealthChecker;
// use Sapiensly\OpenaiAgents\Http\HttpServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/agents.php', 'agents'
        );

        $this->app->singleton(AgentManager::class, function ($app) {
            return new AgentManager(config('agents'));
        });

        // Register the agent facade
        $this->app->singleton('agent', function ($app) {
            return $app->make(AgentManager::class);
        });

        $this->app->singleton('agent.manager', function ($app) {
            return $app->make(AgentManager::class);
        });

        $this->app->singleton(Tracing::class, function ($app) {
            $config = $app['config']['agents.tracing'];
            if (!($config['enabled'] ?? false)) {
                return new Tracing();
            }
            return new Tracing($config['processors'] ?? []);
        });

        // Register OpenAI Client
        $this->app->singleton(\OpenAI\Client::class, function ($app) {
            return (new \OpenAI\Factory())->withApiKey(config('agents.api_key'))->make();
        });

        // Register ModelProviderManager
        $this->app->singleton(ModelProviderManager::class, function ($app) {
            return new ModelProviderManager([
                'default' => config('agents.provider', 'openai'),
            ]);
        });

        // Register lifecycle management services
        $this->registerLifecycleServices();

        // Register advanced handoff services
        $this->registerAdvancedHandoffServices();

        // Register HTTP services (test routes, views, etc.)
        // $this->app->register(HttpServiceProvider::class);
    }

    /**
     * Register services for lifecycle management.
     */
    private function registerLifecycleServices(): void
    {
        // Register AgentPool
        $this->app->singleton(AgentPool::class, function ($app) {
            $config = $app['config']['agents.lifecycle.pool'] ?? [];
            return new AgentPool($config);
        });

        // Register HealthChecker
        $this->app->singleton(HealthChecker::class, function ($app) {
            $config = $app['config']['agents.lifecycle.health'] ?? [];
            return new HealthChecker($config);
        });

        // Register AgentLifecycleManager
        $this->app->singleton(AgentLifecycleManager::class, function ($app) {
            $config = $app['config']['agents.lifecycle'] ?? [];
            return new AgentLifecycleManager(
                $app->make(AgentManager::class),
                $config
            );
        });
    }

    /**
     * Register services for advanced handoff.
     */
    private function registerAdvancedHandoffServices(): void
    {
        // Register AgentRegistry
        $this->app->singleton(AgentRegistry::class, function ($app) {
            return new AgentRegistry();
        });

        // Register ConversationStateManager
        $this->app->singleton(ConversationStateManager::class, function ($app) {
            $config = $app['config']['agents'];
            $provider = $config['handoff']['state']['provider'] ?? 'array';

            return match($provider) {
                'redis' => new RedisConversationStateManager(
                    $app->make('redis')->connection(),
                    'agent:conv:',
                    $config['handoff']['state']['ttl'] ?? 86400
                ),
                default => new ArrayConversationStateManager(),
            };
        });

        // Register SecurityManager
        $this->app->singleton(SecurityManager::class, function ($app) {
            return new SecurityManager($app['config']['agents']);
        });

        // Register MetricsCollector
        $this->app->singleton(MetricsCollector::class, function ($app) {
            $config = $app['config']['agents']['handoff']['metrics'] ?? [];
            return new MetricsCollector(
                processors: $config['processors'] ?? [],
                enabled: $config['enabled'] ?? true
            );
        });

        // Register HandoffOrchestrator
        $this->app->singleton(HandoffOrchestrator::class, function ($app) {
            return new HandoffOrchestrator(
                $app->make(AgentRegistry::class),
                $app->make(ConversationStateManager::class),
                $app->make(SecurityManager::class),
                $app->make(MetricsCollector::class),
                $app['config']['agents']
            );
        });
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/agents.php' => config_path('agents.php'),
        ], 'config');

        // Publicar vistas del dashboard de visualización
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/sapiensly-openai-agents'),
        ], 'agents-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\ChatAgent::class,
                DateQuestionCommand::class,
                ToolTestCommand::class,
                Console\Commands\AgentTinker::class,
                Console\Commands\LifecycleCommand::class,
                Console\Commands\ListFilesCommand::class,
            ]);
        }

        // Register HTTP services in boot method instead
        if (class_exists('Sapiensly\OpenaiAgents\Http\HttpServiceProvider')) {
            $this->app->register('Sapiensly\OpenaiAgents\Http\HttpServiceProvider');
        }
    }
}
