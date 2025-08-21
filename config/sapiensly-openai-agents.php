<?php

use Sapiensly\OpenaiAgents\Persistence\Strategies\RecentMessagesStrategy;

return [
    /*
    |--------------------------------------------------------------------------
    | Sapiensly OpenAI Agents Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains all settings for the OpenAI Agents
    | package including agent definitions, conversation persistence,
    | progressive enhancement levels, and general agent behavior.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION', null),

    /*
    |--------------------------------------------------------------------------
    | Default Agent Options
    |--------------------------------------------------------------------------
    |
    | These are the default options for all agents unless overridden.
    |
    */
    'default_options' => [
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'temperature' => (float) env('OPENAI_TEMPERATURE', 0.7),
        'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 4096),
        'max_turns' => (int) env('OPENAI_MAX_TURNS', 10),
        'max_input_tokens' => (int) env('OPENAI_MAX_INPUT_TOKENS', 4096),
        'max_conversation_tokens' => (int) env('OPENAI_MAX_CONVERSATION_TOKENS', 10000),
        'system_prompt' => env('OPENAI_SYSTEM_PROMPT', null),
        'top_p' => (float) env('OPENAI_TOP_P', 1.0),
        'frequency_penalty' => (float) env('OPENAI_FREQUENCY_PENALTY', 0.0),
        'presence_penalty' => (float) env('OPENAI_PRESENCE_PENALTY', 0.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Progressive Enhancement Configuration
    |--------------------------------------------------------------------------
    |
    | Control the progressive enhancement levels and features.
    |
    */
    'progressive' => [
        'level' => (int) env('AGENTS_PROGRESSIVE_LEVEL', 0),
        'auto_configure' => env('AGENTS_AUTO_CONFIGURE', true),
        'auto_tools' => env('AGENTS_AUTO_TOOLS', false),
        'multi_agents' => env('AGENTS_MULTI_AGENTS', false),
        'enterprise_features' => env('AGENTS_ENTERPRISE_FEATURES', false),
        'autonomy_enabled' => env('AGENTS_AUTONOMY_ENABLED', false),

        'default_tools' => ['calculator', 'date', 'echo'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Definition Persistence
    |--------------------------------------------------------------------------
    |
    | Controls whether agent definitions are stored and retrievable.
    | This is independent of conversation persistence.
    |
    */
    'definitions' => [
        'enabled' => env('AGENT_DEFINITIONS_ENABLED', true),
        'store' => env('AGENT_DEFINITIONS_STORE', 'database'),

        'stores' => [
            'database' => [
                'driver' => 'database',
                'connection' => env('DB_CONNECTION', 'sqlite'),
                'table' => 'agent_definitions',
                'cache' => [
                    'enabled' => env('AGENT_DEFINITIONS_CACHE_ENABLED', true),
                    'ttl' => (int) env('AGENT_DEFINITIONS_CACHE_TTL', 86400),
                    'tags' => env('AGENT_DEFINITIONS_CACHE_TAGS', true),
                ],
            ],

            'cache' => [
                'driver' => 'cache',
                'store' => env('CACHE_DRIVER', 'redis'),
                'prefix' => env('AGENT_DEFINITIONS_CACHE_PREFIX', 'agent_def:'),
                'ttl' => (int) env('AGENT_DEFINITIONS_CACHE_TTL', 86400),
            ],

            'null' => [
                'driver' => 'null',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Persistence
    |--------------------------------------------------------------------------
    |
    | Stores and restores chat histories across requests.
    | Requires both global configuration and per-agent opt-in via withConversation().
    |
    */
    'persistence' => [
        'enabled' => env('AGENT_PERSISTENCE_ENABLED', true),
        'default' => env('AGENT_PERSISTENCE_STORE', 'database'),

        'stores' => [
            'database' => [
                'driver' => 'database',
                'connection' => env('DB_CONNECTION'),
                'cache' => [
                    'enabled' => env('AGENT_PERSISTENCE_CACHE_ENABLED', true),
                    'ttl' => (int) env('AGENT_PERSISTENCE_CACHE_TTL', 3600),
                    'tags' => env('AGENT_PERSISTENCE_CACHE_TAGS', true),
                ],
            ],

            'cache' => [
                'driver' => 'cache',
                'store' => env('CACHE_DRIVER', 'redis'),
                'ttl' => (int) env('AGENT_PERSISTENCE_CACHE_TTL', 86400),
                'prefix' => env('AGENT_PERSISTENCE_CACHE_PREFIX', 'agent_conv:'),
            ],

            'null' => [
                'driver' => 'null',
            ],
        ],

        'context' => [
            'strategy' => RecentMessagesStrategy::class,
            'max_messages' => (int) env('AGENT_CONTEXT_MAX_MESSAGES', 20),
            'max_tokens' => (int) env('AGENT_CONTEXT_MAX_TOKENS', 3000),
            'include_summary' => env('AGENT_CONTEXT_INCLUDE_SUMMARY', true),
        ],

        'summarization' => [
            'enabled' => env('AGENT_SUMMARIZATION_ENABLED', true),
            'after_messages' => (int) env('AGENT_SUMMARIZATION_AFTER_MESSAGES', 20),
            'model' => env('AGENT_SUMMARY_MODEL', 'gpt-3.5-turbo'),
            'max_length' => (int) env('AGENT_SUMMARY_MAX_LENGTH', 500),
            'temperature' => (float) env('AGENT_SUMMARY_TEMPERATURE', 0.3),
        ],

        'cleanup' => [
            'enabled' => env('AGENT_CLEANUP_ENABLED', false),
            'older_than_days' => (int) env('AGENT_CLEANUP_OLDER_THAN_DAYS', 90),
            'keep_summaries' => env('AGENT_CLEANUP_KEEP_SUMMARIES', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Handoff Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for multi-agent handoffs and runners.
    |
    */
    'handoff' => [
        'advanced' => env('AGENTS_HANDOFF_ADVANCED', false),
        'parallel' => env('AGENTS_HANDOFF_PARALLEL', false),
        'reversible' => env('AGENTS_HANDOFF_REVERSIBLE', true),
        'security_controls' => env('AGENTS_HANDOFF_SECURITY', true),
        'default_runner_name' => env('AGENTS_DEFAULT_RUNNER_NAME', 'default_runner'),
        'default_runner_instructions' => env('AGENTS_DEFAULT_RUNNER_INSTRUCTIONS', 'You are a helpful AI assistant that can coordinate with other specialized agents when needed.'),
        'metrics' => [
            'enabled' => env('AGENTS_METRICS_ENABLED', true),
            'processors' => [
                // callable list of metric processors
            ],
        ],
        'state' => [
            'provider' => env('AGENTS_STATE_PROVIDER', 'array'),
            'ttl' => env('AGENTS_STATE_TTL', 86400), // 24 hours
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools & Capabilities
    |--------------------------------------------------------------------------
    |
    | Configuration for various agent tools and capabilities.
    |
    */
    'tools' => [
        'rag' => [
            'enabled' => env('AGENTS_RAG_ENABLED', true),
            'default_k' => (int) env('AGENTS_RAG_DEFAULT_K', 5),
            'default_r' => (float) env('AGENTS_RAG_DEFAULT_R', 0.7),
            'auto_setup' => env('AGENTS_RAG_AUTO_SETUP', true),
        ],

        'web_search' => [
            'enabled' => env('AGENTS_WEB_SEARCH_ENABLED', true),
            'default_context_size' => env('AGENTS_WEB_SEARCH_CONTEXT_SIZE', 'medium'),
        ],

        'mcp' => [
            'enabled' => env('AGENTS_MCP_ENABLED', true),
            'timeout' => (int) env('AGENTS_MCP_TIMEOUT', 30),
            'retry_attempts' => (int) env('AGENTS_MCP_RETRY_ATTEMPTS', 3),
        ],

        'code_interpreter' => [
            'enabled' => env('AGENTS_CODE_INTERPRETER_ENABLED', true),
        ],

        'file_search' => [
            'enabled' => env('AGENTS_FILE_SEARCH_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Features
    |--------------------------------------------------------------------------
    |
    | Configuration for advanced agent capabilities.
    |
    */
    'tracing' => [
        'enabled' => env('AGENTS_TRACING_ENABLED', false),
        'detailed' => env('AGENTS_TRACING_DETAILED', false),
        'store_results' => env('AGENTS_TRACING_STORE_RESULTS', false),
        'processors' => [
            // callable list of trace processors
        ],
    ],

    'voice' => [
        'enabled' => env('AGENTS_VOICE_ENABLED', false),
        'transcription_model' => env('AGENTS_VOICE_TRANSCRIPTION_MODEL', 'whisper-1'),
        'tts_model' => env('AGENTS_VOICE_TTS_MODEL', 'tts-1'),
        'tts_voice' => env('AGENTS_VOICE_TTS_VOICE', 'alloy'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance & Limits
    |--------------------------------------------------------------------------
    |
    | Configuration for performance optimization and rate limiting.
    |
    */
    'performance' => [
        'async_enabled' => env('AGENTS_ASYNC_ENABLED', true),
        'cache_tool_results' => env('AGENTS_CACHE_TOOL_RESULTS', true),
        'parallel_processing' => env('AGENTS_PARALLEL_PROCESSING', false),

        'rate_limits' => [
            'requests_per_minute' => (int) env('AGENTS_RATE_LIMIT_RPM', 60),
            'tokens_per_minute' => (int) env('AGENTS_RATE_LIMIT_TPM', 50000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaming Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for streaming responses.
    |
    */
    'streaming' => [
        'enabled' => env('AGENTS_STREAMING_ENABLED', true),
        'chunk_size' => (int) env('AGENTS_STREAMING_CHUNK_SIZE', 1024),
        'timeout' => (int) env('AGENTS_STREAMING_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing & Development
    |--------------------------------------------------------------------------
    |
    | Settings for testing and development environments.
    |
    */
    'testing' => [
        'mock_enabled' => env('AGENTS_TESTING_MOCK_ENABLED', false),
        'log_requests' => env('AGENTS_TESTING_LOG_REQUESTS', false),
        'fake_responses' => env('AGENTS_TESTING_FAKE_RESPONSES', false),
    ],
];
