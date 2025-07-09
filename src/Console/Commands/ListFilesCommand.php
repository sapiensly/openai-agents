<?php

/**
 * ListFilesCommand - OpenAI File Management
 * 
 * Purpose: Lists files from OpenAI account with filtering and formatting options.
 * This command provides comprehensive file management capabilities for OpenAI
 * file operations including listing, filtering, and format conversion.
 * 
 * File Management Concept: OpenAI allows uploading files for various purposes
 * including assistants, fine-tuning, and other AI operations. This command
 * helps manage and inspect these files.
 * 
 * Features Tested:
 * - File listing and retrieval
 * - Purpose-based filtering
 * - Multiple output formats (table, JSON, CSV)
 * - File information display
 * - Size and metadata formatting
 * - Pagination and limits
 * 
 * Usage:
 * - List all files: php artisan agent:list-files
 * - Filter by purpose: php artisan agent:list-files --purpose=assistants
 * - Custom limit: php artisan agent:list-files --limit=50
 * - JSON output: php artisan agent:list-files --format=json
 * - CSV output: php artisan agent:list-files --format=csv
 * 
 * Test Scenarios:
 * 1. File listing and retrieval
 * 2. Purpose-based filtering
 * 3. Multiple output format testing
 * 4. File information display
 * 5. Size and metadata formatting
 * 6. Pagination and limit handling
 * 
 * File Purposes:
 * - assistants: Files for assistant creation
 * - fine-tune: Files for model fine-tuning
 * - batch: Files for batch processing
 * - vision: Files for vision models
 * 
 * Output Formats:
 * - table: Tabular output (default)
 * - json: JSON format
 * - csv: CSV format
 * 
 * File Information:
 * - File ID and name
 * - Purpose and type
 * - Size in bytes
 * - Creation date
 * - Status and metadata
 */

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\Tools\FileUploadTool;

/**
 * Class ListFilesCommand
 *
 * List files from OpenAI account.
 */
class ListFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:list-files
                            {--purpose= : Filter by purpose (assistants, fine-tune, etc.)}
                            {--limit=20 : Limit for listing files}
                            {--format=table : Output format (table, json, csv)}';

    /**
     * The console command description.
     */
    protected $description = 'List files from OpenAI account';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $agent = Agent::create(['model' => 'gpt-4o']);
            $client = $agent->getClient();
            $fileUploadTool = new FileUploadTool($client);

            $purpose = $this->option('purpose');
            $limit = (int) $this->option('limit');
            $format = $this->option('format');

            $this->info("Listing files from OpenAI account...");
            
            if ($purpose) {
                $this->info("Filtering by purpose: {$purpose}");
            }
            
            $this->info("Limit: {$limit}");

            $result = $fileUploadTool->list([
                'purpose' => $purpose,
                'limit' => $limit
            ]);
            
            $data = json_decode($result, true);
            
            if ($data['success']) {
                $this->info("✅ Found {$data['count']} files:");
                
                if (empty($data['files'])) {
                    $this->info("No files found.");
                    return 0;
                }
                
                switch ($format) {
                    case 'json':
                        $this->output->write(json_encode($data['files'], JSON_PRETTY_PRINT));
                        break;
                        
                    case 'csv':
                        $this->outputCsv($data['files']);
                        break;
                        
                    default:
                        $this->outputTable($data['files']);
                        break;
                }
                
                return 0;
            } else {
                $this->error("❌ Failed to list files: " . ($data['error'] ?? 'Unknown error'));
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ File listing operation failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Output files as table.
     */
    private function outputTable(array $files): void
    {
        $rows = [];
        foreach ($files as $file) {
            $rows[] = [
                $file['id'],
                $file['filename'],
                $file['purpose'],
                $this->formatBytes($file['bytes']),
                $file['created_at']
            ];
        }
        
        $this->table(['ID', 'Filename', 'Purpose', 'Size', 'Created'], $rows);
    }

    /**
     * Output files as CSV.
     */
    private function outputCsv(array $files): void
    {
        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, ['ID', 'Filename', 'Purpose', 'Size (bytes)', 'Created']);
        
        // Data
        foreach ($files as $file) {
            fputcsv($output, [
                $file['id'],
                $file['filename'],
                $file['purpose'],
                $file['bytes'],
                $file['created_at']
            ]);
        }
        
        fclose($output);
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
} 