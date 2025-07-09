# Streaming in OpenAI Agents for Laravel

**Streaming mode** allows receiving responses from AI agents in real-time, showing text as it is generated. This improves user experience, reduces perceived latency, and allows building much more fluid conversational interfaces.

---

## How does streaming work?

- El agente utiliza la API de OpenAI con la opción `stream=true`.
- El método `runStreamed()` del `Runner` retorna un iterable de chunks de texto.
- Cada chunk es una parte de la respuesta generada por el modelo, que puede mostrarse inmediatamente en consola, web o cualquier interfaz.
- El historial de conversación y las herramientas funcionan igual que en modo normal, pero la respuesta se va construyendo en tiempo real.

---

## Ventajas sobre el modo normal

- **Interactividad:** El usuario ve la respuesta mientras se genera, sin esperar a que termine todo el procesamiento.
- **Percepción de velocidad:** Aunque el tiempo total pueda ser similar, la experiencia es mucho más ágil.
- **Mejor UX:** Ideal para chatbots, asistentes, aplicaciones de soporte y cualquier interfaz conversacional.
- **Immediate feedback:** Allows showing animations, loaders or even canceling the response if the user desires.

---

## Console usage example

### Comando básico
```bash
php artisan agent:test-streaming --mode=streaming --turns=2 --delay=0.05
```

### Comando con control de duración
```bash
# Test rápido (1 turno, 30s timeout, 200 chars max)
php artisan agent:test-streaming --quick

# Test controlado (1 turno, 10s timeout, 100 chars max)
php artisan agent:test-streaming --turns=1 --timeout=10 --max-length=100

# Test streaming only with limits
php artisan agent:test-streaming --mode=streaming --turns=1 --timeout=15
```

### Opciones disponibles

| Option | Description | Default Value |
|--------|-------------|-------------------|
| `--quick` | Quick mode (1 turn, 30s timeout, 200 chars) | false |
| `--turns` | Number of conversation turns | 3 |
| `--timeout` | Maximum time per turn (seconds) | 60 |
| `--max-length` | Maximum response length (characters) | 500 |
| `--delay` | Delay between chunks (seconds) | 0.1 |
| `--mode` | Test mode (normal, streaming, both) | both |

## Programmatic usage example

### Basic streaming
```php
$runner = new Runner($agent);
foreach ($runner->runStreamed('Tell me an epic story') as $chunk) {
    echo $chunk; // You can send each chunk to the frontend via SSE, WebSocket, etc.
}
```

### Streaming with timeout and length control
```php
$agent = Agent::create(['model' => 'gpt-3.5-turbo']);

$chunks = [];
$startTime = microtime(true);
$maxTime = 30; // 30 seconds
$maxLength = 500; // 500 characters

foreach ($agent->chatStreamed('Tell me a story') as $chunk) {
    $chunks[] = $chunk;
    echo $chunk;
    
    // Check timeout
    if (microtime(true) - $startTime > $maxTime) {
        echo "\n[Timeout reached]";
        break;
    }
    
    // Check length limit
    if (strlen(implode('', $chunks)) > $maxLength) {
        echo "\n[Length limit reached]";
        break;
    }
}
```

### Streaming with tools
```php
$runner = new Runner($agent);

// Register tools for streaming
$runner->registerTool('calculator', function($args) {
    $expression = $args['expression'] ?? '0';
    return eval("return {$expression};");
});

// Streaming with tools
foreach ($runner->runStreamed('Calculate 15 * 23 and explain the result') as $chunk) {
    echo $chunk;
}
```

### Streaming with RAG (Retrieval-Augmented Generation)

Streaming with RAG allows getting real-time responses based on documents stored in vector stores:

```php
// Configurar agente con RAG
$agent = Agent::create(['model' => 'gpt-4o']);
$agent->enableRAG('vs_123', ['k' => 5, 'r' => 0.7]);

// Streaming with RAG
foreach ($agent->chatStreamed('How does Laravel Eloquent work?') as $chunk) {
    echo $chunk; // Gradual response based on documents from the vector store
}
```

### CLI Command for RAG with Streaming

```bash
# Basic RAG streaming
php artisan agent:test-rag-streaming "How does Laravel work?" --vector-store=vs_123

# RAG streaming with custom parameters
php artisan agent:test-rag-streaming "Explain best practices" \
    --vector-store=vs_123 \
    --delay=0.01 \
    --max-length=800 \
    --timeout=30

# RAG streaming with different speeds
php artisan agent:test-rag-streaming "What is Eloquent?" \
    --vector-store=vs_123 \
    --delay=0.05  # Slower for demos

# RAG streaming with custom retrieval parameters
php artisan agent:test-rag-streaming "Search for authentication information" \
    --vector-store=vs_123 \
    --k=10 \
    --r=0.8 \
    --delay=0.02
```

**RAG Streaming Options:**
- `--delay=0.01` - Fast streaming (0.01 = fast, 0.1 = slow)
- `--timeout=60` - Maximum response time
- `--max-length=1000` - Maximum response length
- `--k=5` - Number of results to retrieve
- `--r=0.7` - Relevance threshold

**Benefits of RAG Streaming:**
- Real-time text generation based on vector store content
- Better user experience with gradual response
- Configurable speed and length limits
- Same RAG functionality as non-streaming version

## Integration in real projects

- **Web (Vue/React):** Use Server-Sent Events (SSE) or WebSockets to send each chunk to the browser and display it in a live chat.
- **REST API:** Expose an endpoint that returns chunks using SSE (`text/event-stream`).
- **CLI:** Display chunks in console for an interactive terminal experience.

## Best practices

- Use streaming for long prompts, story generation, conversational support, and assistants.
- If you need the complete response for validation, you can accumulate chunks and process at the end.
- Handle errors and disconnections: if the stream is interrupted, show a friendly message.
- Adjust delay only for demos; in production, show chunks as soon as they arrive.
- **Implement timeouts** to avoid infinite responses.
- **Set length limits** to control response size.
- **Use `--quick` mode** for fast tests during development.

---

## Comparison: Streaming vs Normal Mode

| Feature                 | Streaming         | Normal Mode       |
|------------------------|-------------------|-------------------|
| Real-time response      | ✅ Yes            | ❌ No              |
| User experience         | ⭐⭐⭐⭐⭐            | ⭐⭐                |
| Early cancellation      | ✅ Possible       | ❌ No              |
| Chatbot integration     | ✅ Ideal          | ❌ Limited         |
| Total time              | Similar           | Similar            |
| Timeout control         | ✅ Configurable    | ❌ Limited         |
| Length limit            | ✅ Configurable    | ❌ Not available   |

---

## Testing Tools for Streaming

The package includes complete tools for testing and developing SSE streaming functionality.

### Configuration

Enable testing tools in your `.env`:

```env
AGENTS_TESTING_ENABLED=true
AGENTS_TEST_SSE_ROUTE=/agents/test-sse
AGENTS_TEST_CHAT_STREAM_ROUTE=/agents/chat-stream
AGENTS_TEST_WEB_MIDDLEWARE=true
AGENTS_TEST_AUTH_MIDDLEWARE=false
```

O configura directamente en `config/agents.php`:

```php
'testing' => [
    'enabled' => env('AGENTS_TESTING_ENABLED', false),
    'routes' => [
        'sse_test' => env('AGENTS_TEST_SSE_ROUTE', '/agents/test-sse'),
        'chat_stream' => env('AGENTS_TEST_CHAT_STREAM_ROUTE', '/agents/chat-stream'),
    ],
    'middleware' => [
        'web' => env('AGENTS_TEST_WEB_MIDDLEWARE', true),
        'auth' => env('AGENTS_TEST_AUTH_MIDDLEWARE', false),
    ],
],
```

### Interfaz Web de Testing

Visita `/agents/test-sse` para acceder a la interfaz interactiva que incluye:

- **Modo Debug:** Muestra detalles técnicos, timestamps y datos SSE raw
- **Modo Normal:** Muestra conversaciones en formato chat
- **Métricas en tiempo real:** Estadísticas durante el streaming
- **Testing de conexión:** Prueba la conectividad del endpoint
- **Mensajes personalizados:** Envía mensajes y prompts del sistema

### Características de la Interfaz

- **UI Profesional:** Diseño limpio y minimalista con paleta grayscale
- **Dual Display:** Modos debug y conversación normal
- **Estadísticas en vivo:** Métricas en tiempo real durante streaming
- **Manejo de errores:** Reportes de error comprehensivos
- **Diseño responsivo:** Funciona en desktop y móviles
- **Protección CSRF:** Manejo automático de tokens CSRF

### Endpoint SSE de Testing

El package proporciona un endpoint de prueba en `/agents/chat-stream` que:

- Acepta requests POST con parámetros `message` y `system`
- Retorna Server-Sent Events (SSE) stream
- Incluye eventos de conexión, chunk y completado
- Simula streaming en tiempo real
- Soporta validación de tokens CSRF

### Usage Example

```bash
# Habilitar testing
echo "AGENTS_TESTING_ENABLED=true" >> .env

# Limpiar cachés
php artisan config:clear
php artisan route:clear

# Acceder a la interfaz web
# http://localhost:8000/agents/test-sse

# O probar via curl
curl -X POST http://localhost:8000/agents/chat-stream \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "X-CSRF-TOKEN: your-csrf-token" \
  -d "message=Tell me a story&system=You are a storyteller"
```

### Eventos SSE Disponibles

```json
{
  "type": "connected",
  "message": "SSE connection established",
  "timestamp": "2024-01-01T12:00:00.000Z"
}

{
  "type": "chunk", 
  "chunk": "Once upon a time...",
  "timestamp": "2024-01-01T12:00:01.000Z"
}

{
  "type": "done",
  "message": "Stream completed",
  "stats": {
    "chunks": 15,
    "total_chars": 245,
    "duration_ms": 1500
  },
  "timestamp": "2024-01-01T12:00:02.000Z"
}
```

### Seguridad

- **Protección CSRF:** Todos los endpoints requieren tokens CSRF válidos
- **Middleware Configurable:** Puede habilitar/deshabilitar auth middleware
- **Control por Ambiente:** Las herramientas solo disponibles cuando están explícitamente habilitadas
- **Aislamiento de Rutas:** Las rutas de test están separadas de las rutas de producción

---

## Performance y Optimización

### Comparación de APIs

El package soporta tanto Responses API como Chat Completions API para streaming:

```bash
# Comparar performance de streaming entre APIs
php artisan agent:compare-speed "What is the capital of France?" --model=gpt-3.5-turbo
```

### Optimización de Streaming

```php
// Configuración optimizada para streaming
$agent = Agent::create([
    'model' => 'gpt-3.5-turbo', // Modelo más rápido para streaming
    'temperature' => 0.7, // Balance entre creatividad y velocidad
]);

// Streaming con métricas
$startTime = microtime(true);
$chunks = [];
$totalChars = 0;

foreach ($agent->chatStreamed('Tell me a story') as $chunk) {
    $chunks[] = $chunk;
    $totalChars += strlen($chunk);
    echo $chunk;
}

$duration = microtime(true) - $startTime;
echo "\nStats: " . count($chunks) . " chunks, {$totalChars} chars, " . number_format($duration, 3) . "s";
```

### Recommended Configuration

```php
// config/agents.php
'streaming' => [
    'default_timeout' => 30,
    'default_max_length' => 1000,
    'chunk_delay' => 0.05, // Para demos
    'enable_metrics' => true,
],
```

---

## Referencias
- [Command example: `agent:test-streaming`](src/Console/Commands/TestStreaming.php)
- [Runner::runStreamed()](src/Runner.php)
- [TestController](src/Http/Controllers/TestController.php)
- [HttpServiceProvider](src/Http/HttpServiceProvider.php)
- [OpenAI API Docs - Streaming](https://platform.openai.com/docs/guides/text-generation/streaming)

---

¿Dudas o sugerencias? ¡Abre un issue o contribuye al proyecto! 