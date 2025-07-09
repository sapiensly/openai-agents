<?php

namespace Sapiensly\OpenaiAgents\Http;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Sapiensly\OpenaiAgents\Http\Controllers\TestController;

class HttpServiceProvider extends BaseServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register test routes if testing is enabled
        if (Config::get('agents.testing.enabled', false)) {
            $this->registerTestRoutes();
        }

        // Register visualization routes if enabled
        if (Config::get('agents.visualization.enabled', false)) {
            $this->registerVisualizationRoutes();
        }

        // Register views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'sapiensly-openai-agents');
    }

    /**
     * Register test routes
     */
    protected function registerTestRoutes(): void
    {
        $middleware = [];
        
        // Add web middleware if enabled
        if (Config::get('agents.testing.middleware.web', true)) {
            $middleware[] = 'web';
        }
        
        // Add auth middleware if enabled
        if (Config::get('agents.testing.middleware.auth', false)) {
            $middleware[] = 'auth';
        }

        Route::middleware($middleware)
            ->group(function () {
                // SSE Test Page
                Route::get(
                    Config::get('agents.testing.routes.sse_test', '/agents/test-sse'),
                    [TestController::class, 'sseTest']
                )->name('agents.test.sse');

                // SSE Chat Stream Endpoint
                Route::post(
                    Config::get('agents.testing.routes.chat_stream', '/agents/chat-stream'),
                    [TestController::class, 'chatStream']
                )->name('agents.test.chat-stream');
            });
    }

    /**
     * Register visualization routes
     */
    protected function registerVisualizationRoutes(): void
    {
        $middleware = Config::get('agents.visualization.middleware', ['web']);
        $route = Config::get('agents.visualization.route', '/agents/visualization');
        \Illuminate\Support\Facades\Route::middleware($middleware)
            ->group(function () use ($route) {
                \Illuminate\Support\Facades\Route::get(
                    $route,
                    [\Sapiensly\OpenaiAgents\Http\Controllers\AgentVisualizationController::class, 'index']
                )->name('agents.visualization');
                \Illuminate\Support\Facades\Route::get(
                    $route . '/metrics',
                    [\Sapiensly\OpenaiAgents\Http\Controllers\AgentVisualizationController::class, 'metrics']
                )->name('agents.visualization.metrics');
                \Illuminate\Support\Facades\Route::get(
                    $route . '/handoff-graph',
                    [\Sapiensly\OpenaiAgents\Http\Controllers\AgentVisualizationController::class, 'handoffGraph']
                )->name('agents.visualization.handoff-graph');
            });
    }
} 