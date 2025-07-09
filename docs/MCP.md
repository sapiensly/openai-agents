# Model Context Protocol (MCP)

The Model Context Protocol (MCP) is a standardized protocol that enables AI agents to interact with external tools and data sources in a consistent and type-safe manner. This package provides comprehensive MCP support with multiple transport protocols, resource discovery, and seamless integration with Laravel applications.

## What is MCP?

The Model Context Protocol is designed to standardize communication between AI agents and external tools. It provides:

- **Standardized communication** between agents and tools using JSON-RPC 2.0
- **Multiple transport protocols** (HTTP, STDIO, Server-Sent Events)
- **Resource discovery** and automatic tool registration
- **Type-safe tool definitions** with JSON schemas
- **Extensible architecture** for custom tools and data sources

### Why Use MCP?

MCP offers several advantages over traditional tool integration:

- **Interoperability**: Tools can be shared across different AI platforms
- **Type Safety**: JSON schemas ensure proper parameter validation
- **Discovery**: Automatic resource discovery simplifies tool management
- **Flexibility**: Multiple transport protocols for different use cases
- **Standardization**: Consistent interface across different tools and services

### Architecture Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   AI Agent      │    │   MCP Manager   │    │   MCP Server    │
│                 │    │                 │    │                 │
│ ┌─────────────┐ │    │ ┌─────────────┐ │    │ ┌─────────────┐ │
│ │   Runner    │◄┼────┼►│ MCPManager  │◄┼────┼►│ MCPServer   │ │
│ └─────────────┘ │    │ └─────────────┘ │    │ └─────────────┘ │
│                 │    │                 │    │                 │
│ ┌─────────────┐ │    │ ┌─────────────┐ │    │ ┌─────────────┐ │
│ │   Tools     │◄┼────┼►│ MCPTool     │◄┼────┼►│ MCPResource │ │
│ └─────────────┘ │    │ └─────────────┘ │    │ └─────────────┘ │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Quick Start

### Basic MCP Setup

1. **Enable MCP** in your `.env` file:

```env
MCP_ENABLED=true
MCP_SERVER_URL=https://your-mcp-server.com/mcp
```

2. **Test the connection**:

```bash
php artisan agent:test-mcp-http --tool=add --params='{"a": 10, "b": 20}'
```

3. **Use MCP with an agent**:

```bash
php artisan agent:mcp-example --query="Calculate 15 * 23"
```

### Programmatic MCP Integration

```php
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;
use Sapiensly\OpenaiAgents\MCP\MCPManager;
use Sapiensly\OpenaiAgents\MCP\MCPServer;
use Sapiensly\OpenaiAgents\MCP\MCPResource;
use Sapiensly\OpenaiAgents\MCP\MCPTool;

// Create agent and runner
$manager = app(AgentManager::class);
$agent = $manager->agent(null, 'You can use MCP tools to access external data.');
$runner = new Runner($agent);

// Setup MCP manager
$mcpManager = new MCPManager([
    'enable_logging' => true,
    'auto_discover' => true,
]);

// Add MCP server
$mcpManager->addServer('calculator', 'https://api.example.com/mcp', [
    'transport' => 'http',
    'timeout' => 30,
    'max_retries' => 3,
]);

// Create MCP resource
$resource = new MCPResource(
    'add',
    'Add two numbers',
    '/add',
    [
        'a' => ['type' => 'number', 'description' => 'First number'],
        'b' => ['type' => 'number', 'description' => 'Second number']
    ]
);

// Create MCP tool
$tool = new MCPTool('add', $resource, $mcpManager->getServer('calculator'), 
    function($params) {
        $a = $params['a'] ?? 0;
        $b = $params['b'] ?? 0;
        return $a + $b;
    }
);

// Register tool with runner
$runner->registerMCPTool('calculator', $tool);

// Use the agent with MCP tools
$response = $runner->run('Calculate 15 + 23 using the add tool');
```

## Comandos Disponibles

### 1. `agent:test-mcp-http`

**Propósito**: Pruebas HTTP/JSON-RPC con servidor MCP real.

**Descripción**: Conecta con un servidor MCP real usando el formato JSON-RPC correcto y prueba la ejecución de herramientas.

**Uso básico**:
```bash
php artisan agent:test-mcp-http
```

**Opciones**:
- `--tool=add` - Herramienta a probar (add, multiply, get_time)
- `--params='{"a": 10, "b": 20}'` - Parámetros en formato JSON
- `--method=POST` - Método HTTP (informativo, siempre usa POST para JSON-RPC)

**Examples**:
```bash
# Test addition with default parameters
php artisan agent:test-mcp-http --tool=add

# Test multiplication with custom parameters
php artisan agent:test-mcp-http --tool=multiply --params='{"a": 8, "b": 9}'

# Test get time
php artisan agent:test-mcp-http --tool=get_time

# Test with complex parameters
php artisan agent:test-mcp-http --tool=add --params='{"a": 100, "b": 200}' --method=POST
```

**Características**:
- ✅ Conecta con servidor MCP real
- ✅ Usa formato JSON-RPC correcto
- ✅ Prueba múltiples herramientas automáticamente
- ✅ Muestra estadísticas del manager
- ✅ Validación de capacidades del servidor

**Example output**:
```
🚀 Testing MCP Server with HTTP Transport
📡 MCP Configuration: ...
✅ Connected successfully to MCP server
✅ Tool execution result:
{
    "result": "The result of 15 + 25 = 40"
}
📈 MCP Statistics:
| Total Tools      | 3     |
🎉 MCP HTTP testing completed successfully!
```

---

### 2. `agent:mcp-example`

**Propósito**: Demostración completa de MCP con IA y OpenAI.

**Descripción**: Integra Agent + Runner + MCP para demostrar el uso real de herramientas MCP con inteligencia artificial.

**Uso básico**:
```bash
php artisan agent:mcp-example
```

**Opciones**:
- `--query="What is 15 * 23?"` - Custom query
- `--debug` - Habilitar logging detallado
- `--model=gpt-3.5-turbo` - Modelo de OpenAI a usar

**Ejemplos**:
```bash
# Default query
php artisan agent:mcp-example

# Custom query
php artisan agent:mcp-example --query="What is the weather in Madrid and calculate 15 * 23?"

# Con debug habilitado
php artisan agent:mcp-example --query="Calculate 12 * 8" --debug

# Usando modelo específico
php artisan agent:mcp-example --model=gpt-4 --query="What is 25 * 16?"
```

**Características**:
- ✅ Integración completa con OpenAI
- ✅ Herramientas simuladas (weather, calculator, database)
- ✅ Respuestas inteligentes usando IA
- ✅ Estadísticas de ejecución
- ✅ Tiempo de respuesta

**Salida de ejemplo**:
```
🚀 MCP Example: Demonstrating Model Context Protocol functionality
🤖 Running query: What is 12 * 8?
📝 Response: The result of 12 * 8 is 96.
⏱️ Execution time: 2.2645s
✅ MCP Example completed successfully
```

---

### 3. `agent:mcp-sse-example`

**Purpose**: Demonstration of Server-Sent Events (SSE) with realistic streaming.

**Description**: Simulates different types of real-time data streaming.

**Uso básico**:
```bash
php artisan agent:mcp-sse-example
```

**Opciones**:
- `--type=stock-data` - Streaming type (stock-data, log-analysis, sensor-data)
- `--duration=10` - Duración en segundos

**Ejemplos**:
```bash
# Stock data streaming (default)
php artisan agent:mcp-sse-example --type=stock-data --duration=5

# Análisis de logs
php artisan agent:mcp-sse-example --type=log-analysis --duration=8

# Datos de sensores
php artisan agent:mcp-sse-example --type=sensor-data --duration=6

# Short streaming for testing
php artisan agent:mcp-sse-example --duration=3
```

**Available streaming types**:

#### Stock Data (`--type=stock-data`)
- Simula precios de acciones en tiempo real
- Símbolos: AAPL, GOOGL, MSFT
- Datos: precio, cambio, volumen

#### Log Analysis (`--type=log-analysis`)
- Simula análisis de logs en tiempo real
- Niveles: DEBUG, INFO, WARN, ERROR, FATAL
- Filtros por nivel y patrón

#### Sensor Data (`--type=sensor-data`)
- Simula datos de sensores IoT
- Sensores: temperatura, humedad, presión
- Lecturas en tiempo real

**Características**:
- ✅ Simulated real-time streaming
- ✅ Múltiples tipos de datos
- ✅ Configuración de duración
- ✅ Formato JSON estructurado

**Salida de ejemplo**:
```
🚀 MCP SSE Example - Realistic Streaming Demo
📈 Stock Data Streaming Demo
📊 Streaming stock data...
📈 Update 1: {"timestamp":"2025-07-06 03:31:43","symbols":[{"symbol":"AAPL","price":394.58,"change":0.39,"volume":2296311}]}
✅ Streamed 3 stock updates
🎉 MCP SSE example completed successfully!
```

---

### 4. `agent:test-mcp-stdio`

**Propósito**: Pruebas STDIO (stdin/stdout) con herramientas locales y comandos CLI.

**Descripción**: Conecta con comandos locales usando STDIO transport para probar herramientas MCP con procesos locales.

**Uso básico**:
```bash
php artisan agent:test-mcp-stdio
```

**Opciones**:
- `--command=echo` - Comando a probar
- `--args='["Hello World"]'` - Argumentos del comando (JSON array)
- `--working-dir=` - Directorio de trabajo
- `--timeout=30` - Timeout de la petición
- `--list-commands` - Listar comandos de prueba disponibles

**Ejemplos**:
```bash
# Probar comando echo
php artisan agent:test-mcp-stdio --command=echo --args='["Hello World"]'

# Probar git version
php artisan agent:test-mcp-stdio --command=git --args='["--version"]'

# Probar docker version
php artisan agent:test-mcp-stdio --command=docker --args='["--version"]'

# Probar PHP version
php artisan agent:test-mcp-stdio --command=php --args='["--version"]'

# Listar comandos disponibles
php artisan agent:test-mcp-stdio --list-commands

# Probar con directorio específico
php artisan agent:test-mcp-stdio --command=ls --args='["-la"]' --working-dir=/tmp
```

**Comandos de prueba disponibles**:

#### echo
- **Descripción**: Comando echo simple
- **Argumentos**: `["Hello World"]`
- **Uso**: `php artisan agent:test-mcp-stdio --command=echo --args='["Hello World"]'`

#### git
- **Descripción**: Comando git version
- **Argumentos**: `["--version"]`
- **Uso**: `php artisan agent:test-mcp-stdio --command=git --args='["--version"]'`

#### docker
- **Descripción**: Comando docker version
- **Argumentos**: `["--version"]`
- **Uso**: `php artisan agent:test-mcp-stdio --command=docker --args='["--version"]'`

#### php
- **Descripción**: Comando PHP version
- **Argumentos**: `["--version"]`
- **Uso**: `php artisan agent:test-mcp-stdio --command=php --args='["--version"]'`

#### ls
- **Descripción**: Listar contenido del directorio
- **Argumentos**: `["-la"]`
- **Uso**: `php artisan agent:test-mcp-stdio --command=ls --args='["-la"]'`

#### pwd
- **Descripción**: Mostrar directorio actual
- **Argumentos**: `[]`
- **Uso**: `php artisan agent:test-mcp-stdio --command=pwd --args='[]'`

**Características**:
- ✅ Conecta con comandos locales via STDIO
- ✅ Soporte para argumentos personalizados
- ✅ Información del proceso (PID, estado)
- ✅ Descubrimiento de recursos
- ✅ Integración con MCP Manager
- ✅ Estadísticas del servidor

**Salida de ejemplo**:
```
🚀 Testing MCP Server with STDIO Transport
📡 STDIO Configuration:
  Command: git
  Arguments: ["--version"]
  Working Directory: /Users/edstudio/code/rag
  Timeout: 30s

🔗 Testing connection...
✅ Connection successful

📋 Getting server information...
📚 Listing available resources...
🔍 Process Information:
  Process ID: 12345
  Is Running: Yes

🔧 Testing with MCP Manager...
✅ Server connection successful

📊 Server Information:
  Name: stdio-test-server
  Transport: stdio
  Enabled: Yes
  Resources: 0

📈 Server Statistics:
| Metric          | Value                    |
| Name            | stdio-test-server        |
| Transport       | stdio                    |
| Enabled         | Yes                      |
| Resources Count | 0                        |
| Process ID      | 12345                    |
| Is Running      | Yes                      |

🎉 MCP STDIO testing completed successfully!
```

**Tips**:
- Usa `--working-dir` para establecer un directorio de trabajo específico
- Usa `--timeout` para ajustar el timeout de la petición
- Los comandos que generan JSON son más adecuados para pruebas MCP
- Algunos comandos pueden no responder a peticiones JSON-RPC

---

## Ejemplos de Código

### HTTP Transport Example

```php
use Sapiensly\OpenaiAgents\MCP\MCPManager;
use Sapiensly\OpenaiAgents\MCP\MCPServer;
use Sapiensly\OpenaiAgents\MCP\MCPResource;
use Sapiensly\OpenaiAgents\MCP\MCPTool;

// Create MCP manager
$mcpManager = new MCPManager([
    'enable_logging' => true,
    'auto_discover' => true,
]);

// Add HTTP server
$mcpManager->addServer('api-server', 'https://api.example.com/mcp', [
    'transport' => 'http',
    'timeout' => 30,
    'max_retries' => 3,
    'headers' => [
        'Authorization' => 'Bearer your-token',
        'Content-Type' => 'application/json',
    ],
]);

// Create resource for weather API
$weatherResource = new MCPResource(
    'get_weather',
    'Get current weather for a location',
    '/weather',
    [
        'location' => [
            'type' => 'string',
            'description' => 'City name',
            'required' => true
        ],
        'units' => [
            'type' => 'string',
            'enum' => ['celsius', 'fahrenheit'],
            'default' => 'celsius'
        ]
    ]
);

// Create tool with HTTP client
$weatherTool = new MCPTool('get_weather', $weatherResource, $mcpManager->getServer('api-server'), 
    function($params) {
        $location = $params['location'] ?? 'London';
        $units = $params['units'] ?? 'celsius';
        
        // Make HTTP request to weather API
        $response = Http::get("https://api.weatherapi.com/v1/current.json", [
            'key' => env('WEATHER_API_KEY'),
            'q' => $location,
            'aqi' => 'no'
        ]);
        
        return $response->json();
    }
);

// Register tool
$mcpManager->addTool($weatherTool);
```

### STDIO Transport Example

```php
// Create STDIO server for local Git tools
$mcpManager->addServer('git-tools', 'git', [
    'transport' => 'stdio',
    'command' => 'git',
    'arguments' => ['--version'],
    'working_directory' => '/path/to/repo',
    'environment' => [
        'GIT_AUTHOR_NAME' => 'AI Agent',
        'GIT_AUTHOR_EMAIL' => 'agent@example.com',
    ],
    'timeout' => 30,
]);

// Create resource for git status
$gitStatusResource = new MCPResource(
    'git_status',
    'Get git repository status',
    '/status',
    [
        'porcelain' => [
            'type' => 'boolean',
            'description' => 'Use porcelain format',
            'default' => false
        ]
    ]
);

// Create tool for git status
$gitStatusTool = new MCPTool('git_status', $gitStatusResource, $mcpManager->getServer('git-tools'), 
    function($params) {
        $porcelain = $params['porcelain'] ?? false;
        $args = $porcelain ? ['status', '--porcelain'] : ['status'];
        
        // Execute git command via STDIO
        $process = new Process(['git'] + $args);
        $process->run();
        
        return [
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
        ];
    }
);

// Register tool
$mcpManager->addTool($gitStatusTool);
```

### SSE Streaming Example

```php
// Create SSE streaming resource
$streamingResource = new MCPResource(
    'stream_data',
    'Stream real-time data',
    '/stream',
    [
        'type' => [
            'type' => 'string',
            'enum' => ['stock', 'logs', 'sensors'],
            'description' => 'Type of data to stream'
        ],
        'duration' => [
            'type' => 'integer',
            'description' => 'Stream duration in seconds',
            'default' => 30
        ]
    ]
);

// Create streaming tool
$streamingTool = new MCPTool('stream_data', $streamingResource, $mcpManager->getServer('sse-server'), 
    function($params) {
        $type = $params['type'] ?? 'stock';
        $duration = $params['duration'] ?? 30;
        
        // Return generator for streaming
        return function() use ($type, $duration) {
            $start = time();
            while (time() - $start < $duration) {
                yield [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'type' => $type,
                    'data' => $this->generateData($type),
                ];
                sleep(1);
            }
        };
    }
);

// Register streaming tool
$mcpManager->addTool($streamingTool);
```

### Integration with Agent and Runner

```php
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;

// Create agent and runner
$manager = app(AgentManager::class);
$agent = $manager->agent(null, 'You can use MCP tools to access external data and perform calculations.');
$runner = new Runner($agent);

// Set MCP manager
$runner->setMCPManager($mcpManager);

// Register MCP tools with runner
foreach ($mcpManager->getEnabledTools() as $tool) {
    $runner->registerMCPTool($tool->getServer()->getName(), $tool);
}

// Use agent with MCP tools
$response = $runner->run('Get the weather in Madrid and calculate the square root of 144');

// Stream MCP resource
foreach ($runner->streamMCPResource('sse-server', 'stream_data', ['type' => 'stock', 'duration' => 10]) as $chunk) {
    echo json_encode($chunk) . "\n";
}
```

## Configuración Avanzada

### Environment Variables

Los comandos MCP requieren las siguientes variables en tu archivo `.env`:

```env
# Habilitar MCP
MCP_ENABLED=true

# Configuración HTTP del servidor MCP
MCP_SERVER_URL=https://mcp-test.edgar-escudero.workers.dev/mcp
MCP_SSE_URL=https://mcp-test.edgar-escudero.workers.dev/sse

# Configuración de conexión
MCP_TIMEOUT=30
MCP_MAX_RETRIES=3
MCP_ENABLE_LOGGING=true

# Headers personalizados (opcional)
MCP_HEADERS={"Content-Type": "application/json"}

# Authentication token (if required)
MCP_AUTH_TOKEN=your-auth-token-if-needed

# Configuración STDIO
MCP_STDIO_ENABLED=false

# Servidores STDIO de ejemplo
MCP_GIT_COMMAND=git
MCP_GIT_WORKING_DIR=
MCP_GIT_ENV={}
MCP_GIT_TIMEOUT=30
MCP_GIT_ENABLED=false

MCP_DOCKER_COMMAND=docker
MCP_DOCKER_WORKING_DIR=
MCP_DOCKER_ENV={}
MCP_DOCKER_TIMEOUT=30
MCP_DOCKER_ENABLED=false

MCP_CUSTOM_COMMAND=php
MCP_CUSTOM_SCRIPT=script.php
MCP_CUSTOM_WORKING_DIR=
MCP_CUSTOM_ENV={}
MCP_CUSTOM_TIMEOUT=30
MCP_CUSTOM_ENABLED=false
```

### Variable Description

#### HTTP Transport
- `MCP_ENABLED`: Enable/disable MCP functionality (default: false)
- `MCP_SERVER_URL`: MCP server URL for HTTP requests
- `MCP_SSE_URL`: MCP server URL for Server-Sent Events streaming
- `MCP_TIMEOUT`: Request timeout in seconds (default: 30)
- `MCP_MAX_RETRIES`: Maximum retry attempts (default: 3)
- `MCP_ENABLE_LOGGING`: Enable logging for MCP operations (default: true)
- `MCP_AUTH_TOKEN`: Authentication token if required by the server (default: empty)

#### STDIO Transport
- `MCP_STDIO_ENABLED`: Enable/disable STDIO transport (default: false)
- `MCP_GIT_COMMAND`: Git command for versioning tools
- `MCP_GIT_WORKING_DIR`: Working directory for git
- `MCP_GIT_ENV`: Environment variables for git (JSON)
- `MCP_GIT_TIMEOUT`: Timeout for git commands
- `MCP_GIT_ENABLED`: Enable git STDIO server

- `MCP_DOCKER_COMMAND`: Docker command for container tools
- `MCP_DOCKER_WORKING_DIR`: Working directory for docker
- `MCP_DOCKER_ENV`: Environment variables for docker (JSON)
- `MCP_DOCKER_TIMEOUT`: Timeout for docker commands
- `MCP_DOCKER_ENABLED`: Enable docker STDIO server

- `MCP_CUSTOM_COMMAND`: Custom command
- `MCP_CUSTOM_SCRIPT`: Custom script to execute
- `MCP_CUSTOM_WORKING_DIR`: Working directory for custom script
- `MCP_CUSTOM_ENV`: Environment variables for custom script (JSON)
- `MCP_CUSTOM_TIMEOUT`: Timeout for custom script
- `MCP_CUSTOM_ENABLED`: Enable custom STDIO server

### Available Tools

Based on your MCP server at https://mcp-test.edgar-escudero.workers.dev/:

- `add` - Add two numbers
- `multiply` - Multiply two numbers  
- `get_time` - Get current time

### Configuration in `config/agents.php`

```php
'mcp' => [
    'enabled' => env('MCP_ENABLED', false),
    'server_url' => env('MCP_SERVER_URL', 'http://localhost:3000/mcp'),
    'sse_url' => env('MCP_SSE_URL', 'http://localhost:3000/sse'),
    'timeout' => env('MCP_TIMEOUT', 30),
    'max_retries' => env('MCP_MAX_RETRIES', 3),
    'enable_logging' => env('MCP_ENABLE_LOGGING', true),
    'headers' => json_decode(env('MCP_HEADERS', '{"Content-Type": "application/json"}'), true),
],
```

## Best Practices

### 1. Resource Design

**✅ Good Resource Design:**
```php
// Clear, descriptive resource
$resource = new MCPResource(
    'calculate_mortgage',
    'Calculate monthly mortgage payment',
    '/mortgage/calculate',
    [
        'principal' => [
            'type' => 'number',
            'description' => 'Loan amount in dollars',
            'minimum' => 0
        ],
        'rate' => [
            'type' => 'number',
            'description' => 'Annual interest rate (e.g., 3.5 for 3.5%)',
            'minimum' => 0,
            'maximum' => 100
        ],
        'term_years' => [
            'type' => 'integer',
            'description' => 'Loan term in years',
            'minimum' => 1,
            'maximum' => 50
        ]
    ]
);
```

**❌ Poor Resource Design:**
```php
// Vague, unclear resource
$resource = new MCPResource(
    'calc',
    'Do math',
    '/calc',
    [
        'a' => ['type' => 'number'],
        'b' => ['type' => 'number']
    ]
);
```

### 2. Error Handling

**✅ Good Error Handling:**
```php
$tool = new MCPTool('api_call', $resource, $server, 
    function($params) {
        try {
            $response = Http::timeout(30)->get($params['url']);
            
            if (!$response->successful()) {
                throw new \Exception("API request failed: " . $response->status());
            }
            
            return $response->json();
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'status' => 'failed'
            ];
        }
    }
);
```

### 3. Performance Optimization

**✅ Efficient Resource Usage:**
```php
// Use caching for expensive operations
$mcpManager = new MCPManager([
    'enable_logging' => true,
    'auto_discover' => true,
    'cache_enabled' => true,
    'cache_ttl' => 300, // 5 minutes
]);
```

### 4. Security Considerations

**✅ Secure Configuration:**
```php
// Use environment variables for sensitive data
$mcpManager->addServer('secure-api', 'https://api.example.com', [
    'headers' => [
        'Authorization' => 'Bearer ' . env('API_TOKEN'),
        'X-API-Key' => env('API_KEY'),
    ],
    'timeout' => 30,
    'max_retries' => 3,
]);
```

### 5. Testing Strategies

**✅ Comprehensive Testing:**
```bash
# Test all transport types
php artisan agent:test-mcp-http --tool=add --params='{"a": 10, "b": 20}'
php artisan agent:test-mcp-stdio --command=git --args='["--version"]'
php artisan agent:mcp-sse-example --type=stock-data --duration=5

# Test with real agent
php artisan agent:mcp-example --query="Calculate 15 * 23" --debug
```

## Use Cases

### Development and Testing
```bash
# Test connection with MCP server
php artisan agent:test-mcp-http --tool=add

# Verify available tools
php artisan agent:test-mcp-http --tool=multiply --params='{"a": 5, "b": 6}'
```

### AI Demonstration
```bash
# Math query
php artisan agent:mcp-example --query="What is 25 * 16?"

# Complex query
php artisan agent:mcp-example --query="Get the weather in Madrid and calculate the square root of 144"
```

### Data Streaming
```bash
# Stock monitoring
php artisan agent:mcp-sse-example --type=stock-data --duration=10

# Log analysis
php artisan agent:mcp-sse-example --type=log-analysis --duration=5
```

### Integration with Local Tools
```bash
# Git operations
php artisan agent:test-mcp-stdio --command=git --args='["status"]'

# Docker operations
php artisan agent:test-mcp-stdio --command=docker --args='["ps"]'
```

## Troubleshooting

### Error: "MCP is not enabled"
```bash
# Solución: Agregar a .env
MCP_ENABLED=true
```

### Error: "Failed to connect to MCP server"
```bash
# Verificar URL del servidor en .env
MCP_SERVER_URL=https://mcp-test.edgar-escudero.workers.dev/mcp
```

### Error: "Tool execution failed"
```bash
# Verificar formato JSON de parámetros
php artisan agent:test-mcp-http --tool=add --params='{"a": 10, "b": 20}'
```

### Error: "Server does not support SSE"
```bash
# El comando SSE usa streaming simulado, no requiere servidor SSE real
php artisan agent:mcp-sse-example --type=stock-data --duration=3
```

### Error: "STDIO process failed"
```bash
# Verificar que el comando existe y es ejecutable
php artisan agent:test-mcp-stdio --command=git --args='["--version"]'
```

### Performance Issues

**High latency with HTTP transport:**
```php
// Increase timeout and retries
$mcpManager->addServer('api', 'https://api.example.com', [
    'timeout' => 60,
    'max_retries' => 5,
]);
```

**Memory issues with large responses:**
```php
// Use streaming for large data
foreach ($runner->streamMCPResource('server', 'large_dataset', []) as $chunk) {
    // Process chunk by chunk
    processChunk($chunk);
}
```

## Arquitectura

### Componentes MCP

1. **MCPManager**: Orquesta servidores, recursos y herramientas
2. **MCPServer**: Representa un servidor MCP
3. **MCPResource**: Define recursos disponibles
4. **MCPTool**: Herramientas ejecutables
5. **MCPClient**: Cliente HTTP para comunicación
6. **MCPSTDIOClient**: Cliente STDIO para procesos locales

### Flujo de Ejecución

1. **Configuración**: Carga configuración desde `.env`
2. **Conexión**: Establece conexión con servidor MCP
3. **Descubrimiento**: Descubre recursos y herramientas
4. **Ejecución**: Ejecuta herramientas con parámetros
5. **Resultado**: Procesa y muestra resultados

### Diagrama de Clases

```
MCPManager
├── MCPServer[]
│   ├── MCPClient (HTTP)
│   ├── MCPSTDIOClient (STDIO)
│   └── MCPResource[]
│       └── MCPTool
└── Statistics & Logging
```

### Transport Protocols

#### HTTP/JSON-RPC
- **Use case**: Remote APIs, web services
- **Pros**: Standard, widely supported
- **Cons**: Network latency, requires server

#### STDIO
- **Use case**: Local tools, CLI applications
- **Pros**: Fast, secure, no network
- **Cons**: Local only, process management

#### Server-Sent Events (SSE)
- **Use case**: Real-time streaming data
- **Pros**: Real-time, efficient
- **Cons**: One-way communication, browser support

## API Reference

### MCPManager

**Constructor:**
```php
$manager = new MCPManager([
    'enable_logging' => true,
    'auto_discover' => true,
    'connection_timeout' => 30,
    'max_retries' => 3,
]);
```

**Methods:**
- `addServer(string $name, string $url, array $config): self`
- `getServer(string $name): ?MCPServer`
- `addTool(MCPTool $tool): self`
- `executeTool(string $toolName, array $parameters): mixed`
- `testAllConnections(): array`
- `getStats(): array`

### MCPServer

**Constructor:**
```php
$server = new MCPServer($name, $url, [
    'transport' => 'http|stdio',
    'timeout' => 30,
    'max_retries' => 3,
]);
```

**Methods:**
- `testConnection(): bool`
- `discoverResources(): array`
- `callResource(string $resourceName, array $parameters): array`
- `getServerInfo(): array`

### MCPResource

**Constructor:**
```php
$resource = new MCPResource(
    $name,
    $description,
    $uri,
    $parameters,
    $schema
);
```

**Methods:**
- `validateParameters(array $parameters): array`
- `toArray(): array`
- `fromArray(array $data): self`

### MCPTool

**Constructor:**
```php
$tool = new MCPTool(
    $toolName,
    $resource,
    $server,
    $callback
);
```

**Methods:**
- `execute(array $parameters): mixed`
- `getSchema(): array`
- `getToolDefinition(): array`

## Contribución

Para agregar nuevos comandos MCP:

1. Crear nuevo comando en `src/Console/Commands/`
2. Registrar en `CommandServiceProvider.php`
3. Documentar en este archivo
4. Probar con diferentes escenarios

### Guidelines for MCP Tools

1. **Clear naming**: Use descriptive names for tools and resources
2. **Proper validation**: Always validate input parameters
3. **Error handling**: Provide meaningful error messages
4. **Documentation**: Document all parameters and return values
5. **Testing**: Include comprehensive tests for all tools

## Referencias

- [Model Context Protocol](https://modelcontextprotocol.io/)
- [JSON-RPC 2.0 Specification](https://www.jsonrpc.org/specification)
- [Server-Sent Events](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events)
- [OpenAI Agents Python SDK](https://github.com/openai/openai-agents-python)
- [Laravel HTTP Client](https://laravel.com/docs/http-client)
