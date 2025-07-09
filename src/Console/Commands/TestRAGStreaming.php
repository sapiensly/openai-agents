<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;

class TestRAGStreaming extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:test-rag-streaming 
                            {query : The query to test RAG with}
                            {--vector-store= : Vector store ID to use}
                            {--name= : Vector store name to use}
                            {--files=* : Files to upload to vector store}
                            {--k=5 : Number of results to retrieve}
                            {--r=0.7 : Relevance threshold}
                            {--delay=0.05 : Delay between chunks (seconds)}
                            {--timeout=60 : Maximum time in seconds}
                            {--max-length=1000 : Maximum response length}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test RAG (Retrieval-Augmented Generation) with streaming output';

    public function handle(): int
    {
        $query = $this->argument('query');
        $vectorStoreId = $this->option('vector-store');
        $vectorStoreName = $this->option('name');
        $files = $this->option('files');
        $k = (int) $this->option('k');
        $r = (float) $this->option('r');
        $delay = (float) $this->option('delay');
        $timeout = (int) $this->option('timeout');
        $maxLength = (int) $this->option('max-length');

        $this->info('🧠 Testing RAG with Streaming (Retrieval-Augmented Generation)');
        $this->info('=' . str_repeat('=', 60));
        
        if ($vectorStoreId) {
            $this->info("📁 Vector store ID: {$vectorStoreId}");
        } elseif ($vectorStoreName) {
            $this->info("📁 Vector store name: {$vectorStoreName}");
        } else {
            $this->info("📁 Vector store: Will be created or found by name");
        }
        
        if (!empty($files)) {
            $this->info("📄 Files to upload: " . implode(', ', $files));
        } else {
            $this->info("📄 Files to upload: none");
        }
        
        $this->info("🔍 Query: {$query}");
        $this->info("⚙️  Config: k={$k}, r={$r}, delay={$delay}s, timeout={$timeout}s, max-length={$maxLength}");

        // Create agent
        $manager = app(AgentManager::class);
        $agent = $manager->agent();

        // Setup RAG
        if ($vectorStoreId) {
            $this->info("🔗 Using vector store ID: {$vectorStoreId}");
            $agent->enableRAG($vectorStoreId, ['k' => $k, 'r' => $r]);
            $this->info("✅ Vector store enabled with ID: {$vectorStoreId}");
        } elseif ($vectorStoreName) {
            $this->info("🔗 Using vector store name: {$vectorStoreName}");
            $agent->useRAG($vectorStoreName, ['k' => $k, 'r' => $r]);
            $this->info("✅ Vector store enabled with name: {$vectorStoreName}");
        } else {
            $this->error("❌ You must specify --vector-store or --name");
            return self::FAILURE;
        }

        $this->info("🔍 Executing query with streaming...");
        $this->newLine();

        $startTime = microtime(true);
        $chunks = 0;
        $response = '';

        try {
            foreach ($agent->chatStreamed($query) as $chunk) {
                echo $chunk;
                $response .= $chunk;
                $chunks++;
                
                // Check timeout
                if (microtime(true) - $startTime > $timeout) {
                    $this->warn("\n⚠️  Timeout reached ({$timeout}s)");
                    break;
                }
                
                // Check length limit
                if (strlen($response) > $maxLength) {
                    echo "\n... [truncated]";
                    break;
                }
                
                // Add delay to make streaming visible
                if ($delay > 0) {
                    usleep((int) ($delay * 1000000));
                }
            }

            $totalTime = microtime(true) - $startTime;
            $this->newLine(2);
            $this->info("⏱️  Query executed in " . number_format($totalTime, 3) . "s");
            $this->info("📦 Chunks received: {$chunks}");
            $this->info("📏 Total length: " . strlen($response) . " characters");

        } catch (\Exception $e) {
            $this->error("❌ Error executing the query: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("✅ Test RAG with streaming completed!");
        $this->info("💡 Use --delay=0.02 for faster streaming");
        $this->info("💡 Use --delay=0.1 for slower streaming");
        $this->info("💡 Use --timeout=30 to limit the time");
        $this->info("💡 Use --max-length=500 for shorter responses");

        return self::SUCCESS;
    }
} 