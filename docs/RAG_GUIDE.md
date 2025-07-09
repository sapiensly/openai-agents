# RAG (Retrieval-Augmented Generation) Guide

This guide explains how to use RAG functionality in the OpenAI Agents package, which integrates seamlessly as Level 2 tools.

## Overview

RAG (Retrieval-Augmented Generation) allows agents to search through your documents and provide answers based on the retrieved information. This implementation uses OpenAI's official vector stores and retrieval tools.

## Quick Start

### Basic RAG Setup

```php
use Sapiensly\OpenaiAgents\Agent;

// Create agent with RAG
$agent = Agent::create(['model' => 'gpt-4o']);

// Setup RAG with files
$agent->setupRAG('documentation', [
    'manual.pdf',
    'api-docs.txt'
], [
    'k' => 5,        // Number of results
    'r' => 0.8       // Relevance threshold
]);

// Use RAG
$response = $agent->chat('How does Laravel Eloquent work?');
```

### Manual RAG Setup

```php
$agent = Agent::create(['model' => 'gpt-4o']);

// 1. Upload files
$fileResult = $agent->runTool('file_upload', 'upload', [
    'file_path' => 'manual.pdf'
]);
$fileData = json_decode($fileResult, true);
$fileId = $fileData['file_id'];

// 2. Create vector store
$vsResult = $agent->runTool('vector_store', 'create', [
    'name' => 'my_docs'
]);
$vsData = json_decode($vsResult, true);
$vectorStoreId = $vsData['vector_store_id'];

// 3. Add files to vector store
$agent->runTool('vector_store', 'add_files', [
    'vector_store_id' => $vectorStoreId,
    'file_ids' => [$fileId]
]);

// 4. Enable RAG
$agent->enableRAG($vectorStoreId, [
    'k' => 5,
    'r' => 0.8
]);

// 5. Use RAG
$response = $agent->chat('Explain Laravel best practices');
```

## Configuration

### Environment Variables

```env
# Enable RAG functionality
AGENTS_RAG_ENABLED=true

# Default RAG parameters
AGENTS_RAG_DEFAULT_K=5
AGENTS_RAG_DEFAULT_R=0.7

# Auto-setup RAG tools
AGENTS_RAG_AUTO_SETUP=true

# File upload limits
AGENTS_RAG_MAX_FILE_SIZE=536870912  # 512MB
```

### Configuration File

```php
// config/agents.php
'rag' => [
    'enabled' => env('AGENTS_RAG_ENABLED', true),
    'default_k' => env('AGENTS_RAG_DEFAULT_K', 5),
    'default_r' => env('AGENTS_RAG_DEFAULT_R', 0.7),
    'auto_setup' => env('AGENTS_RAG_AUTO_SETUP', true),
    'max_file_size' => env('AGENTS_RAG_MAX_FILE_SIZE', 512 * 1024 * 1024),
    'allowed_file_types' => ['text/plain', 'application/pdf', 'text/markdown'],
    'vector_store_expiry' => [
        'anchor' => 'last_active_at',
        'days' => 7
    ],
],
```

## Tools Available

### RAG Tool

The main RAG tool for searching vector stores:

```php
// Search in vector store
$result = $agent->runTool('rag', 'search', [
    'query' => 'How does Laravel work?',
    'vector_store_id' => 'vs_123',
    'k' => 5,
    'r' => 0.8
]);
```

### Vector Store Tool

Manage OpenAI vector stores:

```php
// Create vector store
$result = $agent->runTool('vector_store', 'create', [
    'name' => 'my_docs'
]);

// List vector stores
$result = $agent->runTool('vector_store', 'list', [
    'limit' => 20
]);

// Get vector store details
$result = $agent->runTool('vector_store', 'get', [
    'vector_store_id' => 'vs_123'
]);

// Delete vector store
$result = $agent->runTool('vector_store', 'delete', [
    'vector_store_id' => 'vs_123'
]);

// Add files to vector store
$result = $agent->runTool('vector_store', 'add_files', [
    'vector_store_id' => 'vs_123',
    'file_ids' => ['file_abc', 'file_def']
]);
```

### File Upload Tool

Upload and manage files:

```php
// Upload file
$result = $agent->runTool('file_upload', 'upload', [
    'file_path' => 'document.pdf',
    'purpose' => 'assistants'
]);

// List files
$result = $agent->runTool('file_upload', 'list', [
    'purpose' => 'assistants',
    'limit' => 20
]);

// Get file details
$result = $agent->runTool('file_upload', 'get', [
    'file_id' => 'file_abc'
]);

// Delete file
$result = $agent->runTool('file_upload', 'delete', [
    'file_id' => 'file_abc'
]);

// Download file content
$result = $agent->runTool('file_upload', 'download', [
    'file_id' => 'file_abc'
]);
```

## Command Line Interface

### Test RAG

```bash
# Basic RAG test
php artisan agent:test-rag "How does Laravel work?" --files=manual.pdf

# Advanced RAG test
php artisan agent:test-rag "Explain best practices" \
    --files=manual.pdf,api-docs.txt \
    --vector-store=documentation \
    --k=5 \
    --r=0.8

# Setup only (no query)
php artisan agent:test-rag "test" --setup-only --files=docs.pdf
```

### RAG with Streaming

Test RAG functionality with real-time streaming output:

```bash
# Basic RAG streaming test
php artisan agent:test-rag-streaming "How does Laravel work?" --vector-store=vs_123

# RAG streaming with custom parameters
php artisan agent:test-rag-streaming "Explain best practices" \
    --vector-store=vs_123 \
    --delay=0.01 \
    --max-length=800 \
    --timeout=30

# RAG streaming with different speed settings
php artisan agent:test-rag-streaming "What is Eloquent?" \
    --vector-store=vs_123 \
    --delay=0.05  # Slower for demo purposes

# RAG streaming with custom retrieval parameters
php artisan agent:test-rag-streaming "Search for authentication information" \
    --vector-store=vs_123 \
    --k=10 \
    --r=0.8 \
    --delay=0.02
```

**Streaming Options:**
- `--delay=0.01` - Fast streaming (0.01 = fast, 0.1 = slow)
- `--timeout=60` - Maximum response time
- `--max-length=1000` - Maximum response length
- `--k=5` - Number of results to retrieve
- `--r=0.7` - Relevance threshold

**Benefits of RAG Streaming:**
- Real-time text generation from vector store content
- Better user experience with gradual response display
- Configurable speed and length limits
- Same RAG functionality as non-streaming version

### Manage Vector Stores

```bash
# Create vector store
php artisan agent:vector-store create --name=my_docs

# List vector stores
php artisan agent:vector-store list --limit=10

# Get vector store details
php artisan agent:vector-store get --id=vs_123

# Delete vector store
php artisan agent:vector-store delete --id=vs_123

# Add files to vector store
php artisan agent:vector-store add-files --id=vs_123 --files=file_abc,file_def
```

### List Files from OpenAI Account

```bash
# List all files (default limit: 20)
php artisan agent:list-files

# List with custom limit
php artisan agent:list-files --limit=50

# Filter by purpose
php artisan agent:list-files --purpose=assistants

# Output in different formats
php artisan agent:list-files --format=json
php artisan agent:list-files --format=csv

# Combine options
php artisan agent:list-files --purpose=assistants --limit=10 --format=table
```

## Progressive Enhancement

RAG tools are automatically available in Level 2:

```php
// config/agents.php
'progressive' => [
    'level' => 2,
    'auto_tools' => true,
    'default_tools' => ['calculator', 'date', 'rag', 'vector_store', 'file_upload'],
],
```

## Advanced Usage

### Custom RAG Configuration

```php
$agent = Agent::create(['model' => 'gpt-4o']);

// Custom vector store with expiration
$vsResult = $agent->runTool('vector_store', 'create', [
    'name' => 'temporary_docs',
    'expires_after' => [
        'anchor' => 'last_active_at',
        'days' => 1
    ],
    'metadata' => [
        'purpose' => 'temporary_documentation',
        'owner' => 'user_123'
    ]
]);

// Custom retrieval parameters
$agent->enableRAG($vectorStoreId, [
    'k' => 10,           // More results
    'r' => 0.9,          // Higher relevance threshold
    'filters' => [        // Custom filters
        'source' => 'documentation',
        'language' => 'es'
    ]
]);
```

### Batch File Processing

```php
$agent = Agent::create(['model' => 'gpt-4o']);

// Upload multiple files
$files = ['doc1.pdf', 'doc2.txt', 'doc3.md'];
$fileIds = [];

foreach ($files as $file) {
    $result = $agent->runTool('file_upload', 'upload', [
        'file_path' => $file
    ]);
    $data = json_decode($result, true);
    if ($data['success']) {
        $fileIds[] = $data['file_id'];
    }
}

// Create vector store and add all files
$vsResult = $agent->runTool('vector_store', 'create', [
    'name' => 'batch_docs'
]);
$vsData = json_decode($vsResult, true);

$agent->runTool('vector_store', 'add_files', [
    'vector_store_id' => $vsData['vector_store_id'],
    'file_ids' => $fileIds
]);

// Enable RAG
$agent->enableRAG($vsData['vector_store_id']);
```

### Error Handling

```php
try {
    $agent->setupRAG('docs', ['manual.pdf']);
    $response = $agent->chat('How does Laravel work?');
} catch (\Exception $e) {
    // Handle RAG setup errors
    Log::error('RAG setup failed: ' . $e->getMessage());
    
    // Fallback to regular chat
    $response = $agent->chat('How does Laravel work? (without RAG)');
}
```

## Best Practices

### File Management

1. **Use appropriate file types**: PDF, TXT, MD files work best
2. **Keep files under 512MB**: Larger files may cause timeouts
3. **Organize by purpose**: Use descriptive vector store names
4. **Clean up old files**: Delete unused files to save costs

### Vector Store Management

1. **Set appropriate expiration**: Use shorter expiration for temporary docs
2. **Monitor usage**: Check vector store status regularly
3. **Use metadata**: Add meaningful metadata for organization
4. **Backup important data**: Export content before deletion

### Query Optimization

1. **Use specific queries**: "How does Laravel Eloquent work?" vs "Tell me about Laravel"
2. **Adjust relevance threshold**: Higher `r` values for more precise results
3. **Experiment with `k`**: More results for broader context, fewer for focused answers
4. **Use filters**: Filter by source, language, or other metadata

### Performance Tips

1. **Cache results**: RAG results are cached automatically
2. **Batch operations**: Upload multiple files at once
3. **Monitor costs**: Vector stores have usage-based pricing
4. **Use appropriate models**: GPT-4o works best with RAG

## Estrategias para hacer RAG más rápido

### 1. Optimizar parámetros de recuperación

Los parámetros `k` y `r` tienen un impacto directo en la velocidad:

```bash
# Ultra-fast RAG with optimized parameters
php artisan agent:test-rag-streaming "query" \
    --vector-store=vs_123 \
    --k=3 \
    --r=0.8 \
    --delay=0.01 \
    --timeout=20 \
    --max-length=500
```

**Parámetros de velocidad:**
- `--k=3` (menos resultados = más rápido)
- `--r=0.8` (umbral más alto = resultados más precisos)
- `--timeout=20` (límite de tiempo estricto)
- `--max-length=500` (respuestas más cortas)
- `--delay=0.01` (faster streaming)

### 2. Usar modelos más rápidos

For queries that don't require maximum precision, use faster models:

```php
// Usar gpt-3.5-turbo para mayor velocidad
$agent = Agent::create(['model' => 'gpt-3.5-turbo']);
$agent->enableRAG($vectorStoreId, ['k' => 3, 'r' => 0.8]);
```

### 3. Leverage the caching system

The system has **3 cache levels** implemented:

```env
# Enable tool cache
AGENTS_TOOLS_CACHE_ENABLED=true
AGENTS_TOOLS_CACHE_TTL=300

# Optimizar RAG
AGENTS_RAG_DEFAULT_K=3
AGENTS_RAG_DEFAULT_R=0.8
```

**Cache types:**
1. **ToolCacheManager** - Cache for tools (5 min TTL)
2. **ResponseCacheManager** - Cache for complete responses (5 min TTL)  
3. **IntelligentCacheManager** - Cache for handoffs and contexts (1-2 hours TTL)

### 4. Query optimizations

**Specific vs general queries:**
```bash
# ❌ Slow: Very general query
php artisan agent:test-rag "Explain everything about trading"

# ✅ Fast: Specific query
php artisan agent:test-rag "What are buy and sell orders?"
```

### 5. Optimized streaming configuration

```bash
# Ultra-fast streaming
php artisan agent:test-rag-streaming "query" \
    --vector-store=vs_123 \
    --k=2 \
    --r=0.9 \
    --delay=0.005 \
    --timeout=15 \
    --max-length=300
```

### 6. Optimization commands

```bash
# RAG with ultra-fast streaming
php artisan agent:test-rag-streaming "query" \
    --vector-store=vs_6854700b62d88191883f2e1a34415481 \
    --k=2 \
    --r=0.9 \
    --delay=0.005 \
    --timeout=15 \
    --max-length=300

# RAG with cache enabled
php artisan agent:test-rag "query" \
    --vector-store=vs_6854700b62d88191883f2e1a34415481 \
    --k=3 \
    --r=0.8
```

### 7. Performance monitoring

```bash
# View cache statistics
php artisan agent:test-tools --cache-stats

# Compare speeds
php artisan agent:compare-speed "query" --model=gpt-3.5-turbo
```

### 8. Best practices for speed

1. **Specific queries**: "What are buy orders?" vs "Explain everything about trading"
2. **Fewer results**: `k=2-3` instead of `k=5-10`
3. **High threshold**: `r=0.8-0.9` for more precise results
4. **Fast streaming**: `delay=0.01` for immediate response
5. **Strict limits**: `timeout=15-20s` and `max-length=300-500`

### 9. Advanced optimizations for production

**Distributed cache configuration:**
```php
// Use Redis for distributed cache
'cache' => [
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],
    ],
],
```

**Document pre-processing:**
```php
// Cache frequent documents
if (!cache()->has('frequent_docs')) {
    $agent->setupRAG('frequent_docs', $frequentFiles);
    cache()->put('frequent_docs', true, 3600);
}
```

### 10. Resultados esperados

With these optimizations, RAG can be **3-5x faster** than the default configuration:

- **First query**: 2-3 seconds (with tool cache)
- **Repeated queries**: 0.5-1 second (with response cache)
- **Optimized streaming**: Immediate response with 0.01s chunks
- **Cost reduction**: Fewer API calls thanks to caching

### 11. Speed troubleshooting

**"RAG very slow"**
- Reduce `k` to 2-3
- Increase `r` to 0.8-0.9
- Use `gpt-3.5-turbo` instead of `gpt-4o`
- Verify that cache is enabled

**"Streaming not smooth"**
- Reduce `delay` to 0.01 or less
- Increase `timeout` if necessary
- Check internet connection

**"Cache doesn't work"**
- Verify `AGENTS_TOOLS_CACHE_ENABLED=true`
- Check cache logs
- Clear cache if necessary

## Troubleshooting

### Common Issues

**"File not found"**
- Check file path is correct
- Ensure file exists and is readable
- Verify file size is under limit

**"Vector store not found"**
- Check vector store ID is correct
- Verify vector store hasn't expired
- Ensure vector store is in active status

**"No results found"**
- Lower the relevance threshold (`r`)
- Increase number of results (`k`)
- Check if files were properly uploaded
- Verify query is specific enough

**"API rate limit exceeded"**
- Wait before retrying
- Reduce batch sizes
- Use caching for repeated queries

### Debug Commands

```bash
# Check vector store status
php artisan agent:vector-store get --id=vs_123

# List all files from OpenAI account
php artisan agent:list-files

# List files with specific purpose
php artisan agent:list-files --purpose=assistants

# Export files list to CSV
php artisan agent:list-files --format=csv > files.csv

# Test RAG with verbose output
php artisan agent:test-rag "test query" --verbose
```

## Integration Examples

### Laravel Application

```php
// In a controller
public function askQuestion(Request $request)
{
    $agent = Agent::create(['model' => 'gpt-4o']);
    
    // Setup RAG if not already done
    if (!cache()->has('rag_setup')) {
        $agent->setupRAG('app_docs', [
            storage_path('docs/manual.pdf'),
            storage_path('docs/api.txt')
        ]);
        cache()->put('rag_setup', true, 3600);
    }
    
    $response = $agent->chat($request->input('question'));
    
    return response()->json(['answer' => $response]);
}
```

### Command Line Tool

```php
// In a console command
public function handle()
{
    $agent = Agent::create(['model' => 'gpt-4o']);
    
    $agent->setupRAG('cli_docs', [
        'docs/readme.md',
        'docs/changelog.txt'
    ]);
    
    $question = $this->ask('What would you like to know?');
    $response = $agent->chat($question);
    
    $this->info($response);
}
```

This RAG implementation provides a powerful, yet simple way to enhance your agents with document-based knowledge retrieval, seamlessly integrated into the existing Level 2 tool system. 

## Automatic fallback to traditional RAG

If the OpenAI API doesn't support the official `retrieval` tool, the agent automatically performs traditional RAG: it searches for relevant documents in the vector store and adds them as context to the message before calling the model. This happens transparently for the user, both in normal mode and streaming.

This ensures that RAG functionality is always available, regardless of API support for the retrieval tool. 