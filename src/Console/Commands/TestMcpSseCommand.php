<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sapiensly\OpenaiAgents\Facades\Agent;
use Sapiensly\OpenaiAgents\AgentManager;

class TestMcpSseCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:test-mcp-sse
                            {--url= : Base URL del servidor MCP (sin /sse)}
                            {--sse-url= : URL específica para SSE}
                            {--timeout=10 : Timeout para las conexiones en segundos}
                            {--debug : Habilitar logging detallado}
                            {--json-rpc : Probar comunicación JSON-RPC}
                            {--discover : Intentar descubrir recursos}
                            {--full : Ejecutar todos los tests}';

    /**
     * The console command description.
     */
    protected $description = 'Prueba conexiones MCP con Server-Sent Events (SSE) y JSON-RPC específicamente';

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $this->info('🚀 Iniciando diagnóstico completo MCP SSE con JSON-RPC...');

        // Obtener parámetros
        $baseUrl = $this->option('url') ?: 'https://mcp.higress.ai/mcp-time/cmdv51y3x003f9901loxkocyc';
        $sseUrl = $this->option('sse-url') ?: 'https://mcp.higress.ai/mcp-time/cmdv51y3x003f9901loxkocyc/sse';
        $timeout = (int) $this->option('timeout');
        $debug = $this->option('debug');
        $testJsonRpc = $this->option('json-rpc') || $this->option('full');
        $discoverResources = $this->option('discover') || $this->option('full');
        $fullTest = $this->option('full');

        if ($debug) {
            $this->comment("📋 Configuración detallada:");
            $this->line("   Base URL: {$baseUrl}");
            $this->line("   SSE URL: {$sseUrl}");
            $this->line("   Timeout: {$timeout}s");
            $this->line("   Debug: " . ($debug ? 'YES' : 'NO'));
            $this->line("   JSON-RPC Test: " . ($testJsonRpc ? 'YES' : 'NO'));
            $this->line("   Resource Discovery: " . ($discoverResources ? 'YES' : 'NO'));
        }

        $this->newLine();

        // Test 1: Conexiones básicas
        $this->testBasicConnections($baseUrl, $sseUrl, $timeout);

        // Test 2: Configuración MCP con SSE específico
        $this->testMcpSseConfiguration($manager, $baseUrl, $sseUrl, $timeout, $debug);

        // Test 3: JSON-RPC detallado (siempre ejecutar)
        $this->testJsonRpcDetailed($baseUrl, $sseUrl, $timeout, $debug);

        // Test 4: Verificar que sse_url se use correctamente
        $this->testSseUrlUsage($baseUrl, $sseUrl, $timeout, $debug);

        // Test 5: Descubrir recursos si se solicita
        if ($discoverResources) {
            $this->testResourceDiscovery($manager, $baseUrl, $sseUrl, $timeout);
        }

        // Test 6: Streaming SSE avanzado
        if ($fullTest) {
            $this->testAdvancedSseStreaming($sseUrl, $timeout);
        }

        // Test 7: Integración completa MCP + SSE + JSON-RPC
        $this->testFullIntegration($manager, $baseUrl, $sseUrl, $timeout, $debug);

        $this->newLine();
        $this->info('✅ Diagnóstico completo terminado');

        return Command::SUCCESS;
    }

    /**
     * Test conexiones básicas HTTP
     */
    private function testBasicConnections(string $baseUrl, string $sseUrl, int $timeout): void
    {
        $this->comment('📡 Test 1: Conexiones básicas HTTP');

        $endpoints = [
            'Base URL' => $baseUrl,
            'SSE URL' => $sseUrl,
            'Health Base' => $baseUrl . '/health',
            'Resources Base' => $baseUrl . '/resources',
            'SSE Health' => $sseUrl . '/health',
            'SSE Resources' => $sseUrl . '/resources',
        ];

        foreach ($endpoints as $name => $url) {
            try {
                $response = Http::timeout($timeout)->get($url);
                $status = $response->status();

                if ($status === 200) {
                    $this->line("   ✅ {$name}: {$status} OK");
                    if ($response->header('Content-Type')) {
                        $this->line("      📄 Content-Type: " . $response->header('Content-Type'));
                    }
                } elseif ($status === 405) {
                    $this->line("   ⚠️  {$name}: {$status} (Method Not Allowed - normal)");
                } else {
                    $this->line("   ❌ {$name}: {$status}");
                }
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'timeout')) {
                    $this->line("   ⏱️  {$name}: TIMEOUT (normal para SSE)");
                } else {
                    $this->line("   ❌ {$name}: " . substr($e->getMessage(), 0, 50) . "...");
                }
            }
        }
        $this->newLine();
    }

    /**
     * Test configuración MCP específica para SSE
     */
    private function testMcpSseConfiguration(AgentManager $manager, string $baseUrl, string $sseUrl, int $timeout, bool $debug): void
    {
        $this->comment('🔧 Test 2: Configuración MCP SSE específica');

        try {
            // Configuración CORRECTA con sse_url
            $agent = Agent::agent()->useMCPServer([
                'name' => 'mcp-time-sse',
                'url' => $baseUrl,  // URL base
                'config' => [
                    'sse_url' => $sseUrl,  // ✅ URL SSE específica
                    'transport' => 'sse',  // ✅ Transport SSE
                    'timeout' => $timeout,
                    'headers' => [
                        'Accept' => 'text/event-stream',
                        'Cache-Control' => 'no-cache',
                        'Connection' => 'keep-alive'
                    ],
                    'enable_logging' => $debug
                ]
            ]);

            $this->line('   ✅ Agente MCP configurado con SSE URL específica');

            $mcpManager = $agent->getMCPManager();
            $server = $mcpManager->getServer('mcp-time-sse');

            if ($server) {
                $this->line("   ✅ Servidor encontrado: " . $server->getName());
                $this->line("   📍 URL Base: " . $server->getUrl());
                $this->line("   🚀 Transport: " . $server->getTransport());

                // Verificar SSE support
                if ($server->supportsSSE()) {
                    $this->line("   ✅ SSE Support: SÍ");
                } else {
                    $this->line("   ❌ SSE Support: NO");
                }

                $client = $server->getClient();
                if ($client) {
                    $this->line("   🌐 Client URL: " . $client->getServerUrl());

                    if ($debug) {
                        $this->line("   🔍 Headers configurados: " . json_encode($client->getHeaders()));
                    }

                    // Test conexión específica
                    $canConnect = $client->testConnection();
                    if ($canConnect) {
                        $this->line("   ✅ Conexión client: OK");
                    } else {
                        $this->line("   ❌ Conexión client: FAIL");
                    }

                    // Verificar si el cliente usa sse_url internamente
                    $clientUrl = $client->getServerUrl();
                    if (str_contains($clientUrl, '/sse')) {
                        $this->line("   ✅ Cliente usando URL SSE: SÍ");
                    } else {
                        $this->line("   ⚠️  Cliente usando URL SSE: NO (usando: {$clientUrl})");
                    }
                } else {
                    $this->line('   ❌ Cliente no disponible');
                }
            } else {
                $this->line('   ❌ Servidor no encontrado');
            }

            // Test todas las conexiones
            $connections = $mcpManager->testAllConnections();
            foreach ($connections as $serverName => $result) {
                $enabled = $result['enabled'] ? '✅' : '❌';
                $connected = $result['connected'] ? '✅' : '❌';
                $this->line("   📊 {$serverName}: Enabled {$enabled}, Connected {$connected}");
            }

        } catch (Exception $e) {
            $this->line('   ❌ Error configurando MCP: ' . $e->getMessage());
        }
        $this->newLine();
    }

    /**
     * Test comunicación JSON-RPC detallada
     */
    private function testJsonRpcDetailed(string $baseUrl, string $sseUrl, int $timeout, bool $debug): void
    {
        $this->comment('💬 Test 3: Comunicación JSON-RPC detallada');

        // Diferentes payloads JSON-RPC para probar
        $jsonRpcTests = [
            'initialize' => [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '0.1.0',
                    'clientInfo' => [
                        'name' => 'Laravel MCP Client',
                        'version' => '1.0.0'
                    ]
                ]
            ],
            'resources/list' => [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'resources/list',
                'params' => []
            ],
            'tools/list' => [
                'jsonrpc' => '2.0',
                'id' => 3,
                'method' => 'tools/list',
                'params' => []
            ]
        ];

        foreach ($jsonRpcTests as $testName => $payload) {
            $this->line("   🧪 Testing {$testName}:");

            // Test JSON-RPC sobre URL base
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->post($baseUrl, $payload);

                $status = $response->status();
                $this->line("      📡 JSON-RPC Base URL: Status {$status}");

                if ($response->successful()) {
                    $body = $response->json();
                    if ($debug) {
                        $this->line("      ✅ Response: " . json_encode($body, JSON_PRETTY_PRINT));
                    } else {
                        $this->line("      ✅ Response received (" . strlen($response->body()) . " bytes)");
                    }
                } else {
                    $this->line("      ❌ Error response: " . substr($response->body(), 0, 100));
                }
            } catch (Exception $e) {
                $this->line('      ❌ JSON-RPC Base URL Error: ' . $e->getMessage());
            }

            // Test JSON-RPC sobre SSE URL (¡CRÍTICO!)
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'text/event-stream',
                        'Cache-Control' => 'no-cache'
                    ])
                    ->post($sseUrl, $payload);

                $status = $response->status();
                $this->line("      📡 JSON-RPC SSE URL: Status {$status}");

                if ($response->successful() || $status === 200) {
                    $body = $response->body();
                    $contentType = $response->header('Content-Type') ?: 'unknown';
                    $this->line("      📄 Content-Type: {$contentType}");

                    if ($debug) {
                        $this->line("      ✅ SSE Response: " . substr($body, 0, 300) . "...");
                    } else {
                        $this->line("      ✅ SSE Response received (" . strlen($body) . " bytes)");
                    }

                    // Verificar si es SSE format
                    if (str_starts_with($body, 'data:') || str_contains($body, 'event:')) {
                        $this->line("      🌊 Format: Server-Sent Events detected");
                    } else {
                        $this->line("      📄 Format: Standard HTTP response");
                    }
                } else {
                    $this->line("      ❌ SSE Error response: " . substr($response->body(), 0, 100));
                }
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'timeout')) {
                    $this->line('      ⏱️  JSON-RPC SSE: TIMEOUT (normal para streaming)');
                } else {
                    $this->line('      ❌ JSON-RPC SSE Error: ' . $e->getMessage());
                }
            }

            $this->line(''); // Espacio entre tests
        }
        $this->newLine();
    }

    /**
     * Test específico para verificar uso de sse_url
     */
    private function testSseUrlUsage(string $baseUrl, string $sseUrl, int $timeout, bool $debug): void
    {
        $this->comment('🔍 Test 4: Verificación uso correcto de sse_url');

        try {
            // Crear configuración con sse_url específica
            $agent = Agent::agent()->useMCPServer([
                'name' => 'test-sse-url',
                'url' => $baseUrl,
                'config' => [
                    'sse_url' => $sseUrl,  // ✅ Específico
                    'transport' => 'sse',
                    'timeout' => $timeout,
                    'enable_logging' => $debug
                ]
            ]);

            $mcpManager = $agent->getMCPManager();
            $server = $mcpManager->getServer('test-sse-url');

            if ($server && $server->getClient()) {
                $client = $server->getClient();
                $clientUrl = $client->getServerUrl();

                $this->line("   🌐 URL configurada en servidor: " . $server->getUrl());
                $this->line("   🌐 URL usada por cliente: " . $clientUrl);

                // VERIFICACIÓN CRÍTICA: ¿El cliente usa sse_url o url base?
                if ($clientUrl === $sseUrl) {
                    $this->line("   ✅ Cliente usa SSE URL correctamente");
                } elseif ($clientUrl === $baseUrl) {
                    $this->line("   ⚠️  Cliente usa URL base (debería usar SSE URL)");
                } else {
                    $this->line("   ❓ Cliente usa URL diferente: {$clientUrl}");
                }

                // Test directo de recursos usando cliente MCP
                $this->line("   🔧 Probando descubrimiento con cliente...");
                try {
                    $resources = $client->discoverResources();
                    $this->line("   📦 Recursos encontrados vía cliente: " . count($resources));

                    if ($debug && !empty($resources)) {
                        foreach ($resources as $index => $resource) {
                            $name = is_array($resource) ? ($resource['name'] ?? "Resource {$index}") : "Resource {$index}";
                            $this->line("      - {$name}");
                        }
                    }
                } catch (Exception $e) {
                    $this->line("   ❌ Error descubrimiento: " . $e->getMessage());
                }

                // Test directo de call resource
                $this->line("   🎯 Probando llamada directa con cliente...");
                try {
                    $result = $client->callResource('get_time', []);
                    if (isset($result['error'])) {
                        $this->line("   ⚠️  Call result error: " . $result['error']);
                    } else {
                        $this->line("   ✅ Call result: " . json_encode($result));
                    }
                } catch (Exception $e) {
                    $this->line("   ❌ Error en call: " . $e->getMessage());
                }

            } else {
                $this->line("   ❌ No se pudo obtener servidor o cliente");
            }

        } catch (Exception $e) {
            $this->line('   ❌ Error en verificación: ' . $e->getMessage());
        }
        $this->newLine();
    }

    /**
     * Test descubrimiento de recursos
     */
    private function testResourceDiscovery(AgentManager $manager, string $baseUrl, string $sseUrl, int $timeout): void
    {
        $this->comment('🔍 Test 5: Descubrimiento de recursos');

        try {
            $agent = Agent::agent()->useMCPServer([
                'name' => 'mcp-discovery',
                'url' => $baseUrl,
                'config' => [
                    'sse_url' => $sseUrl,
                    'transport' => 'sse',
                    'timeout' => $timeout,
                ]
            ]);

            $mcpManager = $agent->getMCPManager();
            $server = $mcpManager->getServer('mcp-discovery');

            if ($server && $server->getClient()) {
                $client = $server->getClient();

                $this->line("   📡 Descubriendo recursos...");
                $resources = $client->discoverResources();

                $this->line("   📦 Recursos encontrados: " . count($resources));

                if (!empty($resources)) {
                    foreach ($resources as $index => $resource) {
                        $resourceName = is_array($resource) ? ($resource['name'] ?? "Resource {$index}") : "Resource {$index}";
                        $this->line("     - {$resourceName}");
                    }
                } else {
                    $this->line("   ℹ️  No se encontraron recursos (puede ser normal si el servidor no los expone via /resources)");
                }

                // Test server info
                $this->line("   ℹ️  Obteniendo server info...");
                $serverInfo = $client->getServerInfo();
                if (!empty($serverInfo)) {
                    $this->line("   📄 Server Info: " . json_encode($serverInfo));
                } else {
                    $this->line("   ℹ️  No server info disponible");
                }

                // Test tools disponibles
                $tools = $mcpManager->getTools();
                $this->line("   🔧 Tools disponibles: " . count($tools));
                foreach ($tools as $toolName => $tool) {
                    $enabled = $tool->isEnabled() ? '✅' : '❌';
                    $this->line("     - {$toolName}: {$enabled}");
                }
            }
        } catch (Exception $e) {
            $this->line('   ❌ Error en descubrimiento: ' . $e->getMessage());
        }
        $this->newLine();
    }

    /**
     * Test streaming SSE avanzado
     */
    private function testAdvancedSseStreaming(string $sseUrl, int $timeout): void
    {
        $this->comment('🌊 Test 6: Streaming SSE avanzado');

        try {
            $client = new Client([
                'timeout' => $timeout,
                'stream' => true
            ]);

            $this->line("   📡 Conectando a stream SSE: {$sseUrl}");

            $response = $client->get($sseUrl, [
                'headers' => [
                    'Accept' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive'
                ],
                'stream' => true
            ]);

            $status = $response->getStatusCode();
            $this->line("   📡 SSE Stream Status: {$status}");

            $headers = $response->getHeaders();
            $contentType = $headers['Content-Type'][0] ?? 'unknown';
            $this->line("   📋 Content-Type: {$contentType}");

            if ($status === 200) {
                $body = $response->getBody();

                // Leer múltiples chunks
                $chunks = [];
                $totalData = '';
                for ($i = 0; $i < 3; $i++) {
                    $chunk = $body->read(100);
                    if (!empty($chunk)) {
                        $chunks[] = $chunk;
                        $totalData .= $chunk;
                    } else {
                        break;
                    }
                }

                if (!empty($totalData)) {
                    $this->line("   ✅ Stream Data recibida (" . strlen($totalData) . " bytes)");
                    $this->line("   📄 Primeros datos: " . substr($totalData, 0, 200));

                    // Analizar formato SSE
                    if (str_contains($totalData, 'data:') || str_contains($totalData, 'event:')) {
                        $this->line("   🌊 Formato SSE detectado");
                    } else {
                        $this->line("   📄 Formato no-SSE");
                    }
                } else {
                    $this->line("   ⚠️  Stream conectado pero sin datos inmediatos");
                }
            }

        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'timeout')) {
                $this->line('   ⏱️  SSE Stream: TIMEOUT (conexión establecida)');
            } else {
                $this->line('   ❌ SSE Stream Error: ' . $e->getMessage());
            }
        }
        $this->newLine();
    }

    /**
     * Test integración completa MCP + SSE + JSON-RPC
     */
    private function testFullIntegration(AgentManager $manager, string $baseUrl, string $sseUrl, int $timeout, bool $debug): void
    {
        $this->comment('🚀 Test 7: Integración completa MCP + SSE + JSON-RPC');

        try {
            // Configurar agente con SSE completo
            $agent = Agent::agent()->useMCPServer([
                'name' => 'mcp-full-test',
                'url' => $baseUrl,
                'config' => [
                    'sse_url' => $sseUrl,
                    'transport' => 'sse',
                    'timeout' => $timeout,
                    'headers' => [
                        'Accept' => 'text/event-stream',
                        'Cache-Control' => 'no-cache',
                        'Connection' => 'keep-alive',
                        'Content-Type' => 'application/json'
                    ],
                    'enable_logging' => $debug
                ]
            ]);

            // PASO 1: Aplicar exposeMCP
            $this->line("   📝 Aplicando exposeMCP...");
            try {
                $result = $agent->exposeMCP('mcp-full-test')
                    ->sources(['tools', 'resources'])
                    ->mode('auto')
                    ->apply();

                $this->line("   ✅ exposeMCP aplicado");
            } catch (Exception $e) {
                $this->line("   ❌ Error en exposeMCP: " . $e->getMessage());
            }

            // PASO 2: Listar herramientas MCP
            $this->line("   🔧 Listando herramientas MCP...");
            $tools = $agent->listMCPTools();
            $this->line("   📊 Herramientas encontradas: " . count($tools));

            if (!empty($tools)) {
                foreach ($tools as $toolName) {
                    $this->line("     ✅ {$toolName}");
                }
            } else {
                $this->line("   ⚠️  No se encontraron herramientas MCP");
            }

            // PASO 3: Probar herramienta específica si existe
            if (!empty($tools)) {
                $firstTool = array_keys($tools)[0];
                $this->line("   🎯 Probando herramienta: {$firstTool}");

                try {
                    // Esto debería usar SSE internamente
                    $toolResult = $agent->callMCPTool($firstTool, []);
                    $this->line("   ✅ Tool result: " . json_encode($toolResult));
                } catch (Exception $e) {
                    $this->line("   ❌ Error ejecutando tool: " . $e->getMessage());
                }
            }

            // PASO 4: Test chat completo con MCP
            $this->line("   💬 Test chat completo con MCP...");
            try {
                $response = $agent->chat("What's the current time?");
                $this->line("   ✅ Chat response: " . substr($response, 0, 100) . "...");
            } catch (Exception $e) {
                $this->line("   ❌ Error en chat: " . $e->getMessage());
            }

        } catch (Exception $e) {
            $this->line('   ❌ Error en integración completa: ' . $e->getMessage());
        }
        $this->newLine();
    }
}
