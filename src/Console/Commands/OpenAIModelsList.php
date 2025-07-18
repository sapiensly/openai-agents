<?php

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Providers\ModelProviderManager;

class OpenAIModelsList extends Command
{
    protected $signature = 'agent:openai-models-list {--provider= : Filter models by provider} {--find= : Find a specific model by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available OpenAI models';

    /**
     * Execute the console command.
     */
    public function handle(ModelProviderManager $modelProviderManager): int
    {
        $providerFilter = $this->option('provider');
        $modelToFind = $this->option('find');

        if ($modelToFind) {
            $modelInfo = $modelProviderManager->findModel($modelToFind);
            if ($modelInfo) {
                $this->info("Provider: " . ($modelInfo['provider'] ?? 'N/A'));
                $this->info("Model ID: {$modelToFind}");
                $this->info("Type: " . ($modelInfo['info']['object'] ?? 'N/A'));
                $this->info("Created: " . ( isset($modelInfo['info']['created']) ? date('Y-m-d H:i:s', $modelInfo['info']['created']) : 'N/A'));
                    $this->info("Owner: " . ($modelInfo['info']['owned_by'] ?? 'N/A'));
                return 0;
            } else {
                $this->error("Model '{$modelToFind}' not found.");
                return 1;
            }
        }


        $allModels = $modelProviderManager->getAllAvailableModels();

        if ($providerFilter && !isset($allModels[$providerFilter])) {
            $this->error("Provider '{$providerFilter}' not found. Available providers: " . implode(', ', array_keys($allModels)));
            return 1;
        }

        $providers = $providerFilter ? [$providerFilter => $allModels[$providerFilter]] : $allModels;

        foreach ($providers as $providerName => $models) {
            $this->info("\n" . strtoupper($providerName) . " MODELS:");

            $rows = [];
            foreach ($models as $modelId => $modelInfo) {
                $rows[] = [
                    'ID' => $modelId,
                    'Name' => $modelInfo['name'] ?? $modelId,
                    'Type' => $modelInfo['type'] ?? $modelInfo['object'] ?? 'Unknown',
                    'Created' => $modelInfo['created'] ?? 'N/A',
                    'Owner' => $modelInfo['owned_by'] ?? 'N/A',
                ];
            }

            $this->table(
                ['ID', 'Name', 'Type', 'Created', 'Owner'],
                $rows
            );
        }

        return 0;
    }
}
