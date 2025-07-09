<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tools;

use OpenAI\Client;
use Illuminate\Support\Facades\Log;

/**
 * Class FileUploadTool
 *
 * Tool for uploading files to OpenAI.
 * Integrates as a Level 2 tool.
 */
class FileUploadTool
{
    /**
     * The OpenAI client instance.
     */
    private Client $client;

    /**
     * Create a new FileUploadTool instance.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Upload a file to OpenAI.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function upload(array $args): string
    {
        $filePath = $args['file_path'] ?? '';
        $purpose = $args['purpose'] ?? 'assistants';

        if (empty($filePath)) {
            return json_encode([
                'success' => false,
                'error' => 'File path is required'
            ]);
        }

        if (!file_exists($filePath)) {
            return json_encode([
                'success' => false,
                'error' => "File not found: {$filePath}"
            ]);
        }

        try {
            $response = $this->client->files()->upload([
                'file' => fopen($filePath, 'r'),
                'purpose' => $purpose
            ]);

            Log::info("[FileUploadTool] Uploaded file: {$response['id']} from {$filePath}");

            return json_encode([
                'success' => true,
                'file_id' => $response['id'],
                'filename' => $response['filename'],
                'purpose' => $response['purpose'],
                'bytes' => $response['bytes'],
                'message' => "File uploaded successfully with ID: {$response['id']}"
            ]);

        } catch (\Exception $e) {
            Log::error("[FileUploadTool] Error uploading file: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to upload file: " . $e->getMessage()
            ]);
        }
    }

    /**
     * List uploaded files.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function list(array $args = []): string
    {
        try {
            $params = [
                'purpose' => $args['purpose'] ?? null,
                'limit' => $args['limit'] ?? 20
            ];

            $response = $this->client->files()->list($params);
            $files = $response['data'] ?? [];

            $result = [];
            foreach ($files as $file) {
                $result[] = [
                    'id' => $file['id'],
                    'filename' => $file['filename'],
                    'purpose' => $file['purpose'],
                    'bytes' => $file['bytes'],
                    'created_at' => $file['created_at']
                ];
            }

            return json_encode([
                'success' => true,
                'files' => $result,
                'count' => count($result)
            ]);

        } catch (\Exception $e) {
            Log::error("[FileUploadTool] Error listing files: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to list files: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Get file information.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function get(array $args): string
    {
        $fileId = $args['file_id'] ?? null;

        if (empty($fileId)) {
            return json_encode([
                'success' => false,
                'error' => 'File ID is required'
            ]);
        }

        try {
            $response = $this->client->files()->retrieve($fileId);

            return json_encode([
                'success' => true,
                'file' => [
                    'id' => $response['id'],
                    'filename' => $response['filename'],
                    'purpose' => $response['purpose'],
                    'bytes' => $response['bytes'],
                    'created_at' => $response['created_at'],
                    'status' => $response['status'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("[FileUploadTool] Error getting file: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to get file: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete a file.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function delete(array $args): string
    {
        $fileId = $args['file_id'] ?? null;

        if (empty($fileId)) {
            return json_encode([
                'success' => false,
                'error' => 'File ID is required'
            ]);
        }

        try {
            $response = $this->client->files()->delete($fileId);
            
            Log::info("[FileUploadTool] Deleted file: {$fileId}");
            
            return json_encode([
                'success' => true,
                'message' => "File {$fileId} deleted successfully"
            ]);

        } catch (\Exception $e) {
            Log::error("[FileUploadTool] Error deleting file: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to delete file: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Download file content.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function download(array $args): string
    {
        $fileId = $args['file_id'] ?? null;

        if (empty($fileId)) {
            return json_encode([
                'success' => false,
                'error' => 'File ID is required'
            ]);
        }

        try {
            $content = $this->client->files()->download($fileId);

            return json_encode([
                'success' => true,
                'content' => $content,
                'message' => "File content downloaded successfully"
            ]);

        } catch (\Exception $e) {
            Log::error("[FileUploadTool] Error downloading file: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to download file: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Get the tool schema for registration.
     *
     * @return array The tool schema
     */
    public static function getSchema(): array
    {
        return [
            'name' => 'file_upload',
            'description' => 'Upload and manage files for OpenAI services',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'description' => 'Action to perform: upload, list, get, delete, download',
                        'enum' => ['upload', 'list', 'get', 'delete', 'download']
                    ],
                    'file_path' => [
                        'type' => 'string',
                        'description' => 'Path to the file to upload (for upload action)'
                    ],
                    'file_id' => [
                        'type' => 'string',
                        'description' => 'File ID (for get, delete, download actions)'
                    ],
                    'purpose' => [
                        'type' => 'string',
                        'description' => 'Purpose of the file: assistants, fine-tune, etc.',
                        'default' => 'assistants'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Limit for listing files',
                        'default' => 20
                    ]
                ],
                'required' => ['action']
            ]
        ];
    }
} 