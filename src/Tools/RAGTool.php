<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tools;

use OpenAI\Client;
use Illuminate\Support\Facades\Log;

/**
 * Class RAGTool
 *
 * Herramienta RAG que se integra orgánicamente como herramienta del Nivel 2.
 * Permite realizar búsquedas en vector stores de OpenAI.
 */
class RAGTool
{
    /**
     * The OpenAI client instance.
     */
    private Client $client;

    /**
     * Create a new RAGTool instance.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Invoke the RAG tool.
     *
     * @param array $args The tool arguments
     * @return string The tool result
     */
    public function __invoke(array $args): string
    {
        $query = $args['query'] ?? '';
        $vectorStoreId = $args['vector_store_id'] ?? null;
        $k = $args['k'] ?? 5;
        $r = $args['r'] ?? 0.7;

        if (empty($query)) {
            return "Error: Query is required for RAG search";
        }

        if (empty($vectorStoreId)) {
            return "Error: Vector store ID is required for RAG search";
        }

        try {
            return $this->performRetrieval($query, $vectorStoreId, $k, $r);
        } catch (\Exception $e) {
            Log::error("[RAGTool] Error performing retrieval: " . $e->getMessage());
            return "Error performing RAG search: " . $e->getMessage();
        }
    }

    /**
     * Perform retrieval using OpenAI's retrieval tool.
     *
     * @param string $query The search query
     * @param string $vectorStoreId The vector store ID
     * @param int $k Number of results
     * @param float $r Relevance threshold
     * @return string The retrieval result
     */
    private function performRetrieval(string $query, string $vectorStoreId, int $k, float $r): string
    {
        try {
            // Use Chat Completions API for retrieval
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

            return $response->choices[0]->message->content ?? 'No response received';

        } catch (\Exception $e) {
            Log::error("[RAGTool] Retrieval error: " . $e->getMessage());
            return "Error performing retrieval: " . $e->getMessage();
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
            'name' => 'rag',
            'description' => 'Search for information in vector stores using RAG (Retrieval-Augmented Generation)',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'The search query to find relevant information'
                    ],
                    'vector_store_id' => [
                        'type' => 'string',
                        'description' => 'The ID of the vector store to search in'
                    ],
                    'k' => [
                        'type' => 'integer',
                        'description' => 'Number of results to return (default: 5)',
                        'default' => 5
                    ],
                    'r' => [
                        'type' => 'number',
                        'description' => 'Relevance threshold (0.0 to 1.0, default: 0.7)',
                        'default' => 0.7
                    ]
                ],
                'required' => ['query', 'vector_store_id']
            ]
        ];
    }
} 