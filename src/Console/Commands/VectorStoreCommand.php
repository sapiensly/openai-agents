<?php

/**
 * VectorStoreCommand - OpenAI Vector Store Management
 * 
 * Purpose: Manages OpenAI vector stores for RAG (Retrieval-Augmented Generation)
 * functionality. This command provides comprehensive vector store operations
 * including creation, listing, deletion, and file management.
 * 
 * Vector Store Concept: OpenAI vector stores enable efficient storage and
 * retrieval of document embeddings for RAG applications, allowing agents to
 * access and reference specific knowledge bases.
 * 
 * Features Tested:
 * - Vector store creation and configuration
 * - Vector store listing and information retrieval
 * - Vector store deletion and cleanup
 * - File addition to vector stores
 * - File listing and management
 * - Detailed information display
 * - Multiple output formats (table, JSON, CSV)
 * 
 * Usage:
 * - Create store: php artisan agent:vector-store create --name=my_store
 * - List stores: php artisan agent:vector-store list
 * - Get details: php artisan agent:vector-store get --id=vs_123456
 * - Delete store: php artisan agent:vector-store delete --id=vs_123456
 * - Add files: php artisan agent:vector-store add-files --id=vs_123456 --files=file1,file2
 * - List files: php artisan agent:vector-store list-files --id=vs_123456
 * - With details: php artisan agent:vector-store list --details
 * - Custom limit: php artisan agent:vector-store list --limit=50
 * 
 * Test Scenarios:
 * 1. Vector store creation and configuration
 * 2. Vector store listing and information retrieval
 * 3. Vector store deletion and cleanup
 * 4. File addition and management
 * 5. Detailed information display
 * 6. Multiple output format testing
 * 
 * Vector Store Operations:
 * - create: Create a new vector store
 * - list: List all vector stores
 * - get: Get detailed information about a vector store
 * - delete: Delete a vector store
 * - add-files: Add files to a vector store
 * - list-files: List files in a vector store
 * 
 * Output Formats:
 * - table: Tabular output (default)
 * - json: JSON format
 * - csv: CSV format
 */

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\Tools\VectorStoreTool;

/**
 * Class VectorStoreCommand
 *
 * Manage OpenAI vector stores for RAG functionality.
 */
class VectorStoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:vector-store 
                            {action : Action to perform (create, list, get, delete, add-files, list-files)}
                            {--name= : Vector store name (for create action)}
                            {--id= : Vector store ID (for get, delete, add-files, list-files actions)}
                            {--files=* : File IDs to add (for add-files action)}
                            {--limit=20 : Limit for listing vector stores}
                            {--details|-d : Show real file name and size (slower, more informative)}';

    /**
     * The console command description.
     */
    protected $description = 'Manage OpenAI vector stores for RAG functionality';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        
        try {
            $agent = Agent::create(['model' => 'gpt-4o']);
            $client = $agent->getClient();
            $vectorStoreTool = new VectorStoreTool($client);

            switch ($action) {
                case 'create':
                    return $this->handleCreate($vectorStoreTool);
                    
                case 'list':
                    return $this->handleList($vectorStoreTool);
                    
                case 'get':
                    return $this->handleGet($vectorStoreTool);
                    
                case 'delete':
                    return $this->handleDelete($vectorStoreTool);
                    
                case 'add-files':
                    return $this->handleAddFiles($vectorStoreTool);
                    
                case 'list-files':
                    return $this->handleListFiles($vectorStoreTool);
                    
                default:
                    $this->error("Unknown action: {$action}");
                    $this->info("Available actions: create, list, get, delete, add-files, list-files");
                    return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Vector store operation failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Handle create action.
     */
    private function handleCreate(VectorStoreTool $tool): int
    {
        $name = $this->option('name');
        
        if (empty($name)) {
            $this->error("Vector store name is required. Use --name option.");
            return 1;
        }

        $this->info("Creating vector store: {$name}");
        
        $result = $tool->create(['name' => $name]);
        $data = json_decode($result, true);
        
        if ($data['success']) {
            $this->info("✅ Vector store created successfully!");
            $this->table(['ID', 'Name', 'Status'], [
                [$data['vector_store_id'], $data['name'], $data['status']]
            ]);
            return 0;
        } else {
            $this->error("❌ Failed to create vector store: " . ($data['error'] ?? 'Unknown error'));
            return 1;
        }
    }

    /**
     * Handle list action.
     */
    private function handleList(VectorStoreTool $tool): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Listing vector stores (limit: {$limit})...");
        
        $result = $tool->list(['limit' => $limit]);
        $data = json_decode($result, true);
        
        if ($data['success']) {
            $this->info("✅ Found {$data['count']} vector stores:");
            
            if (empty($data['vector_stores'])) {
                $this->info("No vector stores found.");
                return 0;
            }
            
            $rows = [];
            foreach ($data['vector_stores'] as $vs) {
                $rows[] = [
                    $vs['id'],
                    $vs['name'],
                    $vs['status'],
                    $vs['created_at']
                ];
            }
            
            $this->table(['ID', 'Name', 'Status', 'Created'], $rows);
            return 0;
        } else {
            $this->error("❌ Failed to list vector stores: " . ($data['error'] ?? 'Unknown error'));
            return 1;
        }
    }

    /**
     * Handle get action.
     */
    private function handleGet(VectorStoreTool $tool): int
    {
        $id = $this->option('id');
        
        if (empty($id)) {
            $this->error("Vector store ID is required. Use --id option.");
            return 1;
        }

        $this->info("Getting vector store: {$id}");
        
        $result = $tool->get(['vector_store_id' => $id]);
        $data = json_decode($result, true);
        
        if ($data['success']) {
            $this->info("✅ Vector store details:");
            $vs = $data['vector_store'];
            
            $this->table(['Property', 'Value'], [
                ['ID', $vs['id']],
                ['Name', $vs['name']],
                ['Status', $vs['status']],
                ['Created', $vs['created_at']],
                ['Expires After', $vs['expires_after'] ?? 'Never'],
            ]);
            return 0;
        } else {
            $this->error("❌ Failed to get vector store: " . ($data['error'] ?? 'Unknown error'));
            return 1;
        }
    }

    /**
     * Handle delete action.
     */
    private function handleDelete(VectorStoreTool $tool): int
    {
        $id = $this->option('id');
        
        if (empty($id)) {
            $this->error("Vector store ID is required. Use --id option.");
            return 1;
        }

        if (!$this->confirm("Are you sure you want to delete vector store {$id}?")) {
            $this->info("Operation cancelled.");
            return 0;
        }

        $this->info("Deleting vector store: {$id}");
        
        $result = $tool->delete(['vector_store_id' => $id]);
        $data = json_decode($result, true);
        
        if ($data['success']) {
            $this->info("✅ Vector store deleted successfully!");
            return 0;
        } else {
            $this->error("❌ Failed to delete vector store: " . ($data['error'] ?? 'Unknown error'));
            return 1;
        }
    }

    /**
     * Handle add-files action.
     */
    private function handleAddFiles(VectorStoreTool $tool): int
    {
        $id = $this->option('id');
        $files = $this->option('files');
        
        if (empty($id)) {
            $this->error("Vector store ID is required. Use --id option.");
            return 1;
        }
        
        if (empty($files)) {
            $this->error("File IDs are required. Use --files option.");
            return 1;
        }

        $this->info("Adding files to vector store: {$id}");
        $this->info("Files: " . implode(', ', $files));
        
        $result = $tool->addFiles([
            'vector_store_id' => $id,
            'file_ids' => $files
        ]);
        $data = json_decode($result, true);
        
        if ($data['success']) {
            $this->info("✅ Files added successfully!");
            $this->info("Added files: " . implode(', ', $data['added_files']));
            return 0;
        } else {
            $this->error("❌ Failed to add files: " . ($data['error'] ?? 'Unknown error'));
            return 1;
        }
    }

    /**
     * Handle list-files action.
     */
    private function handleListFiles(VectorStoreTool $tool): int
    {
        $id = $this->option('id');
        $details = $this->option('details');
        $limit = (int) $this->option('limit') ?: 20;
        if (empty($id)) {
            $this->error("Vector store ID is required. Use --id option.");
            return 1;
        }
        $this->info("Listing files in vector store: {$id}");
        $result = $tool->list_files(['vector_store_id' => $id, 'limit' => $limit]);
        $data = json_decode($result, true);
        if ($data['success']) {
            if (empty($data['files'])) {
                $this->info("No files found in vector store.");
                return 0;
            }
            $rows = [];
            if ($details) {
                $this->info('Fetching real file names and sizes (may take a while)...');
                $agent = Agent::create(['model' => 'gpt-4o']);
                $client = $agent->getClient();
                $fileTool = new \Sapiensly\OpenaiAgents\Tools\FileUploadTool($client);
                foreach ($data['files'] as $file) {
                    $fileInfo = json_decode($fileTool->get(['file_id' => $file['id']]), true);
                    $rows[] = [
                        $file['id'],
                        $fileInfo['file']['filename'] ?? $file['name'],
                        $fileInfo['file']['bytes'] ?? $file['size'],
                        $file['created_at']
                    ];
                }
            } else {
                foreach ($data['files'] as $file) {
                    $rows[] = [
                        $file['id'],
                        $file['name'],
                        $file['size'],
                        $file['created_at']
                    ];
                }
            }
            $this->table(['ID', 'Name', 'Size', 'Created'], $rows);
            return 0;
        } else {
            $this->error("❌ Failed to list files: " . ($data['error'] ?? 'Unknown error'));
            return 1;
        }
    }
} 