<?php

/**
 * TestRAGCommand - Retrieval-Augmented Generation Testing
 * 
 * Purpose: Tests RAG (Retrieval-Augmented Generation) functionality with OpenAI
 * vector stores. This command validates document ingestion, vector search,
 * and context-aware responses using uploaded files and vector databases.
 * 
 * RAG Concept: Combines retrieval of relevant documents with generation of
 * responses, allowing agents to access and reference specific knowledge bases
 * and documents for more accurate and contextual responses.
 * 
 * Features Tested:
 * - Vector store creation and management
 * - File upload and document ingestion
 * - Vector search with relevance scoring
 * - Context-aware response generation
 * - Streaming responses with RAG
 * - Debug mode for raw API inspection
 * - Performance measurement and timing
 * 
 * Usage:
 * - Basic test: php artisan agent:test-rag "What is Laravel?"
 * - With files: php artisan agent:test-rag "Explain the code" --files=file1.txt,file2.pdf
 * - Custom vector store: php artisan agent:test-rag "Query" --vector-store=my_store
 * - Setup only: php artisan agent:test-rag "Query" --setup-only
 * - Debug mode: php artisan agent:test-rag "Query" --debug
 * - Custom parameters: php artisan agent:test-rag "Query" --k=10 --r=0.8
 * 
 * Test Scenarios:
 * 1. Vector store creation and configuration
 * 2. File upload and document processing
 * 3. Vector search with relevance scoring
 * 4. Context-aware response generation
 * 5. Streaming responses with RAG
 * 6. Debug mode for API inspection
 * 
 * RAG Parameters:
 * - k: Number of results to return (default: 5)
 * - r: Relevance threshold (default: 0.7)
 * - vector-store: Vector store name or ID
 * - files: Files to upload and process
 * - setup-only: Only setup RAG without querying
 * - debug: Show raw API responses
 */

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;

/**
 * Class TestRAGCommand
 *
 * Test RAG functionality with vector stores and file uploads.
 */
class TestRAGCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:test-rag 
                            {query : The query to test RAG with}
                            {--files=* : Files to upload and use for RAG}
                            {--vector-store= : Vector store name (default: test_rag)}
                            {--k=5 : Number of results to return}
                            {--r=0.7 : Relevance threshold}
                            {--setup-only : Only setup RAG without querying}
                            {--debug : Show raw API response for debugging}';

    /**
     * The console command description.
     */
    protected $description = 'Test RAG functionality with OpenAI vector stores';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧠 Testing RAG (Retrieval-Augmented Generation) functionality...');

        try {
            // Create agent
            $agent = Agent::create(['model' => 'gpt-4o']);

            $vectorStoreName = $this->option('vector-store') ?? 'test_rag';
            $files = $this->option('files') ?? [];
            $k = (int) $this->option('k');
            $r = (float) $this->option('r');
            $debug = $this->option('debug');

            $this->info("📁 Vector store name: {$vectorStoreName}");
            $this->info("📄 Files to upload: " . (empty($files) ? 'none' : implode(', ', $files)));
            $this->info("🔍 Query: {$this->argument('query')}");

            // Check if vector store parameter is an ID or name
            $isVectorStoreId = str_starts_with($vectorStoreName, 'vs_');
            
            if ($isVectorStoreId) {
                // Use vector store ID directly
                $this->info("🔗 Using vector store ID: {$vectorStoreName}");
                $agent->enableRAG($vectorStoreName, [
                    'k' => $k,
                    'r' => $r
                ]);
                $this->info("✅ Vector store enabled with ID: {$vectorStoreName}");
            } else {
                // Try to find vector store by name
                $vectorStoreId = null;
                $usedExisting = false;
                try {
                    $vectorStoreId = $agent->useRAG($vectorStoreName, [
                        'k' => $k,
                        'r' => $r
                    ]);
                    $usedExisting = true;
                } catch (\Exception $e) {
                    $usedExisting = false;
                }

                if ($usedExisting) {
                    $this->info("✅ Used existing vector store '{$vectorStoreName}' (useRAG)");
                } else {
                    $this->info("ℹ️  No vector store found with that name, creating a new one (setupRAG)...");
                    $agent->setupRAG($vectorStoreName, $files, [
                        'k' => $k,
                        'r' => $r
                    ]);
                    $this->info("✅ Vector store created and configured (setupRAG)");
                }
            }

            if ($this->option('setup-only')) {
                $this->info("🎯 Setup-only mode: RAG configured but query was not executed.");
                return 0;
            }

            // Test query
            $this->info("\n🔍 Executing query...");
            $startTime = microtime(true);

            $response = '';
            $rawResponse = null;
            try {
                // Force traditional fallback if debug is active to see the raw
                // Run manual search and show the JSON
                if ($debug) {
                    $vectorStoreId = $agent->ragConfig['vector_store_id'] ?? $vectorStoreName;
                    $tool = new \Sapiensly\OpenaiAgents\Tools\VectorStoreTool($agent->getClient());
                    $searchResult = $tool->search([
                        'vector_store_id' => $vectorStoreId,
                        'query' => $this->argument('query'),
                        'k' => $k,
                        'r' => $r
                    ]);
                    $rawResponse = json_decode($searchResult, true);
                    $this->info("\n[DEBUG] Raw API response:");
                    $this->line(json_encode($rawResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    if (isset($rawResponse['results'][0]['text'])) {
                        $text = $rawResponse['results'][0]['text'];
                                    // If it's an array of objects with 'text', concatenate the texts
            if (is_array($text)) {
                // If it's an array of objects with 'text'
                if (isset($text[0]['text'])) {
                    $response = implode("\n\n", array_map(fn($t) => $t['text'] ?? '', $text));
                } else {
                    $response = implode("\n", $text);
                }
            } else {
                $response = $text;
            }
                        $this->output->write("\n[Extracted response]\n");
                        $this->output->write($response);
                    }
                } else {
                    foreach ($agent->chatStreamed($this->argument('query')) as $chunk) {
                        $response .= $chunk;
                        $this->output->write($chunk);
                    }
                }
            } catch (\Exception $e) {
                $this->error("❌ Error executing the query: " . $e->getMessage());
                if ($debug && isset($rawResponse)) {
                    $this->info("\n[DEBUG] Raw API response:");
                    $this->line(json_encode($rawResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
            
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 3);

            $this->info("\n⏱️  Query executed in {$executionTime}s");

            $this->info("\n✅ Test RAG completed!");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ RAG test failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
} 