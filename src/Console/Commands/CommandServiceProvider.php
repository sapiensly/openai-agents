<?php

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DateQuestionCommand::class,
                HandoffTestCommand::class,
                ToolTestCommand::class,
                WeatherAndTimeQuestionCommand::class,
                MCPExampleCommand::class,
                MCPSSEExample::class,
                TestMCPHTTP::class,
                TestMCPSTDIO::class,
                TestVoicePipeline::class,
                TestStreaming::class,
                TestLevel1Command::class,
                TestLevel2Command::class,
                TestLevel3Command::class,
                TestLevel4Command::class,
                TestAllLevelsCommand::class,
                TestRAGCommand::class,
                TestRAGStreaming::class,
                VectorStoreCommand::class,
                \Sapiensly\OpenaiAgents\Console\Commands\CompareSpeedCommand::class,
            ]);
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
