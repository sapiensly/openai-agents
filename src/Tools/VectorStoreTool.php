<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tools;

use OpenAI\Client;
use Illuminate\Support\Facades\Log;

/**
 * Class VectorStoreTool
 *
 * Tool for managing OpenAI vector stores.
 * Integrates as a Level 2 tool.
 */
class VectorStoreTool
{
    /**
     * The OpenAI client instance.
     */
    private Client $client;

    /**
     * Create a new VectorStoreTool instance.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Create a vector store.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function create(array $args): string
    {
        $name = $args['name'] ?? 'default';
        $expiresAfter = $args['expires_after'] ?? null;
        $metadata = $args['metadata'] ?? null;

        try {
            $params = ['name' => $name];
            
            if ($expiresAfter) {
                $params['expires_after'] = $expiresAfter;
            }
            
            if ($metadata) {
                $params['metadata'] = $metadata;
            }

            $response = $this->client->vectorStores()->create($params);
            
            Log::info("[VectorStoreTool] Created vector store: {$response['id']}");
            
            return json_encode([
                'success' => true,
                'vector_store_id' => $response['id'],
                'name' => $response['name'],
                'status' => $response['status'],
                'message' => "Vector store '{$name}' created successfully with ID: {$response['id']}"
            ]);

        } catch (\Exception $e) {
            Log::error("[VectorStoreTool] Error creating vector store: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to create vector store: " . $e->getMessage()
            ]);
        }
    }

    /**
     * List vector stores.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function list(array $args = []): string
    {
        try {
            $params = [
                'limit' => $args['limit'] ?? 20,
                'order' => $args['order'] ?? 'desc'
            ];

            $response = $this->client->vectorStores()->list($params);
            $vectorStores = $response['data'] ?? [];

            $result = [];
            foreach ($vectorStores as $vs) {
                $result[] = [
                    'id' => $vs['id'],
                    'name' => $vs['name'],
                    'status' => $vs['status'],
                    'created_at' => $vs['created_at']
                ];
            }

            return json_encode([
                'success' => true,
                'vector_stores' => $result,
                'count' => count($result)
            ]);

        } catch (\Exception $e) {
            Log::error("[VectorStoreTool] Error listing vector stores: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to list vector stores: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Get a vector store.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function get(array $args): string
    {
        $vectorStoreId = $args['vector_store_id'] ?? null;

        if (empty($vectorStoreId)) {
            return json_encode([
                'success' => false,
                'error' => 'Vector store ID is required'
            ]);
        }

        try {
            $response = $this->client->vectorStores()->retrieve($vectorStoreId);

            return json_encode([
                'success' => true,
                'vector_store' => [
                    'id' => $response['id'],
                    'name' => $response['name'],
                    'status' => $response['status'],
                    'created_at' => $response['created_at'],
                    'expires_after' => $response['expires_after'] ?? null,
                    'metadata' => $response['metadata'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("[VectorStoreTool] Error getting vector store: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to get vector store: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete a vector store.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function delete(array $args): string
    {
        $vectorStoreId = $args['vector_store_id'] ?? null;

        if (empty($vectorStoreId)) {
            return json_encode([
                'success' => false,
                'error' => 'Vector store ID is required'
            ]);
        }

        try {
            $response = $this->client->vectorStores()->delete($vectorStoreId);
            
            Log::info("[VectorStoreTool] Deleted vector store: {$vectorStoreId}");
            
            return json_encode([
                'success' => true,
                'message' => "Vector store {$vectorStoreId} deleted successfully"
            ]);

        } catch (\Exception $e) {
            Log::error("[VectorStoreTool] Error deleting vector store: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to delete vector store: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Add files to a vector store.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function addFiles(array $args): string
    {
        $vectorStoreId = $args['vector_store_id'] ?? null;
        $fileIds = $args['file_ids'] ?? [];

        if (empty($vectorStoreId)) {
            return json_encode([
                'success' => false,
                'error' => 'Vector store ID is required'
            ]);
        }

        if (empty($fileIds)) {
            return json_encode([
                'success' => false,
                'error' => 'File IDs are required'
            ]);
        }

        try {
            $addedFiles = [];
            foreach ($fileIds as $fileId) {
                $response = $this->client->vectorStores()->files()->create($vectorStoreId, [
                    'file_id' => $fileId
                ]);
                $addedFiles[] = $response['id'];
            }

            Log::info("[VectorStoreTool] Added " . count($addedFiles) . " files to vector store: {$vectorStoreId}");

            return json_encode([
                'success' => true,
                'message' => "Added " . count($addedFiles) . " files to vector store",
                'added_files' => $addedFiles
            ]);

        } catch (\Exception $e) {
            Log::error("[VectorStoreTool] Error adding files to vector store: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to add files to vector store: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Search for relevant documents in a vector store.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function search(array $args): string
    {
        $query = $args['query'] ?? '';
        $vectorStoreId = $args['vector_store_id'] ?? null;
        $k = $args['k'] ?? 5;
        $r = $args['r'] ?? 0.7;

        if (empty($query)) {
            return json_encode([
                'success' => false,
                'error' => 'Query is required'
            ]);
        }

        if (empty($vectorStoreId)) {
            return json_encode([
                'success' => false,
                'error' => 'Vector store ID is required'
            ]);
        }

        try {
            // Use OpenAI's retrieval API with correct structure
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $query
                    ]
                ],
                'tools' => [
                    [
                        'type' => 'retrieval',
                        'retrieval' => [
                            'vector_store_id' => $vectorStoreId,
                            'k' => $k,
                            'r' => $r
                        ]
                    ]
                ],
                'tool_choice' => 'auto'
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '';
            
            Log::info("[VectorStoreTool] Search completed for query: '{$query}' in vector store: {$vectorStoreId}");

            return json_encode([
                'success' => true,
                'results' => [
                    [
                        'text' => $content
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            // If the retrieval tool is not available, use the direct search endpoint
            if (strpos($e->getMessage(), "tools[0].function") !== false || strpos($e->getMessage(), "Missing required parameter") !== false) {
                Log::info("[VectorStoreTool] Retrieval tool not available, trying direct search API for query: '{$query}' in vector store: {$vectorStoreId}");
                
                try {
                    // Correct call according to official documentation: only 'query'
                    $searchResponse = $this->client->vectorStores()->search($vectorStoreId, [
                        'query' => $query
                    ]);
                    
                    $results = [];
                    if (isset($searchResponse['data'])) {
                        foreach ($searchResponse['data'] as $result) {
                            $results[] = [
                                'text' => $result['text'] ?? $result['content'] ?? ''
                            ];
                        }
                    }
                    
                    return json_encode([
                        'success' => true,
                        'results' => $results
                    ]);
                    
                } catch (\Exception $searchError) {
                    Log::error("[VectorStoreTool] Direct search also failed: " . $searchError->getMessage());
                    return json_encode([
                        'success' => false,
                        'error' => "Both retrieval tool and direct search failed: " . $searchError->getMessage()
                    ]);
                }
            }
            
            Log::error("[VectorStoreTool] Error searching vector store: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to search vector store: " . $e->getMessage()
            ]);
        }
    }

    /**
     * List files in a vector store.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function list_files(array $args): string
    {
        $vectorStoreId = $args['vector_store_id'] ?? null;
        $limit = $args['limit'] ?? 20;

        if (empty($vectorStoreId)) {
            return json_encode([
                'success' => false,
                'error' => 'Vector store ID is required'
            ]);
        }

        try {
            $params = [
                'limit' => $limit
            ];

            $response = $this->client->vectorStores()->files()->list($vectorStoreId, $params);
            $files = $response['data'] ?? [];

            $result = [];
            foreach ($files as $file) {
                $result[] = [
                    'id' => $file['id'],
                    'name' => $file['filename'] ?? $file['name'] ?? 'unknown',
                    'size' => $file['bytes'] ?? $file['size'] ?? 0,
                    'created_at' => $file['created_at']
                ];
            }

            Log::info("[VectorStoreTool] Listed " . count($result) . " files from vector store: {$vectorStoreId}");

            return json_encode([
                'success' => true,
                'files' => $result,
                'count' => count($result)
            ]);

        } catch (\Exception $e) {
            Log::error("[VectorStoreTool] Error listing files from vector store: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => "Failed to list files from vector store: " . $e->getMessage()
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
            'name' => 'vector_store',
            'description' => 'Manage OpenAI vector stores for RAG functionality',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'description' => 'Action to perform: create, list, get, delete, add_files, list_files',
                        'enum' => ['create', 'list', 'get', 'delete', 'add_files', 'list_files']
                    ],
                    'name' => [
                        'type' => 'string',
                        'description' => 'Name for the vector store (for create action)'
                    ],
                    'vector_store_id' => [
                        'type' => 'string',
                        'description' => 'Vector store ID (for get, delete, add_files, list_files actions)'
                    ],
                    'file_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Array of file IDs to add to vector store (for add_files action)'
                    ],
                    'expires_after' => [
                        'type' => 'object',
                        'description' => 'Expiration configuration (for create action)'
                    ],
                    'metadata' => [
                        'type' => 'object',
                        'description' => 'Metadata for the vector store (for create action)'
                    ]
                ],
                'required' => ['action']
            ]
        ];
    }
} 