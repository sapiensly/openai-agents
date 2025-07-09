<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    /*
    |--------------------------------------------------------------------------
    | Default Agent Settings
    |--------------------------------------------------------------------------
    |
    | These options allow you to configure your default agent. They correspond
    | to options available in the OpenAI Agents Python SDK.
    |
    */

    'default' => [
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'temperature' => env('OPENAI_TEMPERATURE', 0.7),
        'top_p' => env('OPENAI_TOP_P', 1.0),
    ],

   /*
    |--------------------------------------------------------------------------
    | Testing Tools Configuration
    |--------------------------------------------------------------------------
    |
    | Configure testing tools and routes for development and debugging.
    | These tools help you test SSE streaming, agent responses, and other
    | functionality during development.
    |
    */
    'testing' => [
        'enabled' => env('AGENTS_TESTING_ENABLED', true),
        'routes' => [
            'sse_test' => env('AGENTS_TEST_SSE_ROUTE', '/agents/test-sse'),
            'chat_stream' => env('AGENTS_TEST_CHAT_STREAM_ROUTE', '/agents/chat-stream'),
        ],
        'middleware' => [
            'web' => env('AGENTS_TEST_WEB_MIDDLEWARE', true),
            'auth' => env('AGENTS_TEST_AUTH_MIDDLEWARE', true),
        ],
        'commands' => [
            'enabled' => env('AGENTS_TEST_COMMANDS_ENABLED', true),
        ],
        'views' => [
            'enabled' => env('AGENTS_TEST_VIEWS_ENABLED', true),
            'layout' => env('AGENTS_TEST_LAYOUT', 'app'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP (Model Context Protocol) Settings
    |--------------------------------------------------------------------------
    |
    | Configure MCP servers and transport types (HTTP and STDIO).
    |
    */
    'mcp' => [
        'enabled' => env('MCP_ENABLED', false),
        
        // HTTP Transport Configuration
        'server_url' => env('MCP_SERVER_URL', 'https://mcp-test.edgar-escudero.workers.dev/mcp'),
        'sse_url' => env('MCP_SSE_URL', 'https://mcp-test.edgar-escudero.workers.dev/sse'),
        'timeout' => env('MCP_TIMEOUT', 30),
        'max_retries' => env('MCP_MAX_RETRIES', 3),
        'enable_logging' => env('MCP_ENABLE_LOGGING', true),
        'headers' => json_decode(env('MCP_HEADERS', '{"Content-Type": "application/json"}'), true) ?: [],
        
        // STDIO Transport Configuration
        'stdio' => [
            'enabled' => env('MCP_STDIO_ENABLED', false),
            'servers' => [
                // Example STDIO server configurations
                'git-tools' => [
                    'command' => env('MCP_GIT_COMMAND', 'git'),
                    'arguments' => ['--help'],
                    'working_directory' => env('MCP_GIT_WORKING_DIR', ''),
                    'environment' => json_decode(env('MCP_GIT_ENV', '{}'), true) ?: [],
                    'timeout' => env('MCP_GIT_TIMEOUT', 30),
                    'enabled' => env('MCP_GIT_ENABLED', false),
                ],
                'docker-tools' => [
                    'command' => env('MCP_DOCKER_COMMAND', 'docker'),
                    'arguments' => ['--help'],
                    'working_directory' => env('MCP_DOCKER_WORKING_DIR', ''),
                    'environment' => json_decode(env('MCP_DOCKER_ENV', '{}'), true) ?: [],
                    'timeout' => env('MCP_DOCKER_TIMEOUT', 30),
                    'enabled' => env('MCP_DOCKER_ENABLED', false),
                ],
                'custom-script' => [
                    'command' => env('MCP_CUSTOM_COMMAND', 'php'),
                    'arguments' => [env('MCP_CUSTOM_SCRIPT', 'script.php')],
                    'working_directory' => env('MCP_CUSTOM_WORKING_DIR', ''),
                    'environment' => json_decode(env('MCP_CUSTOM_ENV', '{}'), true) ?: [],
                    'timeout' => env('MCP_CUSTOM_TIMEOUT', 30),
                    'enabled' => env('MCP_CUSTOM_ENABLED', false),
                ],
            ],
        ],
        
        // Server configurations
        'servers' => [
            'http-server' => [
                'url' => env('MCP_SERVER_URL', 'https://mcp-test.edgar-escudero.workers.dev/mcp'),
                'transport' => 'http',
                'enabled' => env('MCP_HTTP_ENABLED', true),
                'timeout' => env('MCP_TIMEOUT', 30),
                'max_retries' => env('MCP_MAX_RETRIES', 3),
                'headers' => json_decode(env('MCP_HEADERS', '{"Content-Type": "application/json"}'), true) ?: [],
                'capabilities' => ['http', 'json-rpc'],
            ],
        ],
    ],

    'tracing' => [
        'enabled' => env('AGENTS_TRACING', false),
        'processors' => [
            // callable list of trace processors
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Handoff Settings
    |--------------------------------------------------------------------------
    |
    | Configure the handoff behavior between agents. You can choose between
    | basic and advanced implementations, and configure various aspects
| of the advanced implementation.
    |
    */
    'handoff' => [
        'advanced' => env('AGENTS_ADVANCED_HANDOFF', true),
        'max_handoffs_per_conversation' => env('AGENTS_MAX_HANDOFFS', 10),
        'timeout_seconds' => env('AGENTS_HANDOFF_TIMEOUT', 30),
        'retry_attempts' => env('AGENTS_HANDOFF_RETRIES', 3),
        'permissions' => [
            'customer_service' => ['sales', 'technical_support', 'billing'],
            'sales' => ['technical_support', 'customer_service'],
            'technical_support' => ['engineering', 'customer_service'],
            'admin' => ['*'], // Access to all agents
        ],
        'capabilities' => [
            'customer_service' => ['handle_complaints', 'process_refunds', 'answer_faqs'],
            'sales' => ['product_information', 'pricing', 'discounts'],
            'technical_support' => ['troubleshooting', 'installation_help', 'bug_reporting']
        ],
        'fallback_strategies' => [
            'default' => 'customer_service',
            'technical_support' => 'engineering',
            'sales' => 'customer_service'
        ],
        'state' => [
            'provider' => env('AGENTS_STATE_PROVIDER', 'redis'),
            'ttl' => env('AGENTS_STATE_TTL', 86400), // 24 hours
        ],
        'metrics' => [
            'enabled' => env('AGENTS_METRICS_ENABLED', true),
        ],
        'async' => [
            'enabled' => env('AGENTS_ASYNC_HANDOFF', false),
            'queue' => env('AGENTS_ASYNC_QUEUE', 'default'),
            'timeout' => env('AGENTS_ASYNC_TIMEOUT', 300),
            'retries' => env('AGENTS_ASYNC_RETRIES', 3),
        ]
    ],

    // Visualización avanzada de agentes y handoffs
    'visualization' => [
        'enabled' => env('AGENTS_VISUALIZATION_ENABLED', false),
        'route' => env('AGENTS_VISUALIZATION_ROUTE', '/agents/visualization'),
        'middleware' => ['web'],
        'views_publishable' => true,
        'metrics_refresh_interval' => 2, // segundos para polling de métricas
        'features' => [
            'handoff_graph' => true,
            'live_metrics' => true,
            'agent_activity' => true,
            'tracing' => true,
            'conversation_export' => true,
            'parallel_handoff_viz' => true,
            'error_analysis' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle Management Settings
    |--------------------------------------------------------------------------
    |
    | Configure agent lifecycle management including pooling, health checks,
    | resource management, and cleanup policies.
    |
    */
    'lifecycle' => [
        'enabled' => env('AGENTS_LIFECYCLE_ENABLED', true),
        'max_agents' => env('AGENTS_LIFECYCLE_MAX_AGENTS', 100),
        'max_memory_per_agent' => env('AGENTS_LIFECYCLE_MAX_MEMORY', 50 * 1024 * 1024), // 50MB
        'max_conversations_per_agent' => env('AGENTS_LIFECYCLE_MAX_CONVERSATIONS', 1000),
        'agent_ttl' => env('AGENTS_LIFECYCLE_TTL', 3600), // 1 hour
        'health_check_interval' => env('AGENTS_LIFECYCLE_HEALTH_INTERVAL', 300), // 5 minutes
        'cleanup_interval' => env('AGENTS_LIFECYCLE_CLEANUP_INTERVAL', 600), // 10 minutes
        'enable_pooling' => env('AGENTS_LIFECYCLE_POOLING', true),
        'enable_health_checks' => env('AGENTS_LIFECYCLE_HEALTH_CHECKS', true),
        'enable_resource_tracking' => env('AGENTS_LIFECYCLE_RESOURCE_TRACKING', true),
        
        'pool' => [
            'max_size' => env('AGENTS_LIFECYCLE_POOL_MAX_SIZE', 50),
            'min_size' => env('AGENTS_LIFECYCLE_POOL_MIN_SIZE', 5),
            'max_idle_time' => env('AGENTS_LIFECYCLE_POOL_MAX_IDLE', 1800), // 30 minutes
            'cleanup_interval' => env('AGENTS_LIFECYCLE_POOL_CLEANUP', 300), // 5 minutes
            'enable_stats' => env('AGENTS_LIFECYCLE_POOL_STATS', true),
        ],
        
        'health' => [
            'memory_threshold' => env('AGENTS_LIFECYCLE_HEALTH_MEMORY', 50 * 1024 * 1024), // 50MB
            'conversation_threshold' => env('AGENTS_LIFECYCLE_HEALTH_CONVERSATIONS', 1000),
            'response_timeout' => env('AGENTS_LIFECYCLE_HEALTH_TIMEOUT', 5), // seconds
            'enable_caching' => env('AGENTS_LIFECYCLE_HEALTH_CACHE', true),
            'cache_ttl' => env('AGENTS_LIFECYCLE_HEALTH_CACHE_TTL', 300), // 5 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Progressive Enhancement Settings
    |--------------------------------------------------------------------------
    |
    | Configure the progressive enhancement levels for the package.
    | This allows developers to start simple and scale up as needed.
    |
    */
    'progressive' => [
        'level' => env('AGENTS_PROGRESSIVE_LEVEL', 0),
        'auto_configure' => env('AGENTS_AUTO_CONFIGURE', true),
        'auto_tools' => env('AGENTS_AUTO_TOOLS', false),
        'auto_handoff' => env('AGENTS_AUTO_HANDOFF', false),
        'declarative_agents' => env('AGENTS_DECLARATIVE_AGENTS', false),
        'enterprise_features' => env('AGENTS_ENTERPRISE_FEATURES', false),
        'default_tools' => ['echo', 'date', 'calculator', 'rag', 'vector_store', 'file_upload'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pre-configured Agents (Level 2+)
    |--------------------------------------------------------------------------
    |
    | Define pre-configured agents for declarative usage.
    | These agents are available via Agent::use('agent_name').
    |
    */
    'agents' => [
        'assistant' => [
            'system_prompt' => 'You are a helpful assistant',
            'tools' => ['echo', 'date', 'calculator'],
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
        ],
        'coder' => [
            'system_prompt' => 'You are a coding expert. Write clean, documented code.',
            'tools' => ['git', 'docker', 'file_operations'],
            'model' => 'gpt-4',
            'temperature' => 0.3,
        ],
        'analyst' => [
            'system_prompt' => 'You are a data analyst. Provide insights and analysis.',
            'tools' => ['calculator', 'statistics', 'chart_generator'],
            'model' => 'gpt-4',
            'temperature' => 0.5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools Configuration (Level 1+)
    |--------------------------------------------------------------------------
    |
    | Configure tool settings for progressive enhancement.
    |
    */
    'tools' => [
        'cache' => [
            'enabled' => env('AGENTS_TOOLS_CACHE_ENABLED', true),
            'ttl' => env('AGENTS_TOOLS_CACHE_TTL', 300),
        ],
        'auto_discovery' => env('AGENTS_TOOLS_AUTO_DISCOVERY', true),
        'validation' => env('AGENTS_TOOLS_VALIDATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG Configuration (Level 2+)
    |--------------------------------------------------------------------------
    |
    | Configure RAG (Retrieval-Augmented Generation) functionality.
    |
    */
    'rag' => [
        'enabled' => env('AGENTS_RAG_ENABLED', true),
        'default_k' => env('AGENTS_RAG_DEFAULT_K', 5),
        'default_r' => env('AGENTS_RAG_DEFAULT_R', 0.7),
        'auto_setup' => env('AGENTS_RAG_AUTO_SETUP', true),
        'max_file_size' => env('AGENTS_RAG_MAX_FILE_SIZE', 512 * 1024 * 1024), // 512MB
        'allowed_file_types' => ['text/plain', 'application/pdf', 'text/markdown'],
        'vector_store_expiry' => [
            'anchor' => 'last_active_at',
            'days' => 7
        ],
    ],
];
