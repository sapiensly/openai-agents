<?php

declare(strict_types=1);

return [
    // Global enable switch (still requires withConversation() per agent)
    'enabled' => env('AGENT_PERSISTENCE_ENABLED', true),

    // Default store. Keep 'null' by default to avoid any dependency or behavior change
    'default' => env('AGENT_PERSISTENCE_STORE', 'null'), // database|cache|null

    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'mysql'),
            'cache' => [
                'enabled' => true,
                'ttl' => 3600,
                'tags' => true,
            ],
        ],
        'cache' => [
            'driver' => 'cache',
            'store' => env('CACHE_DRIVER', 'redis'),
            'ttl' => 86400,
            'prefix' => 'agent_conv:',
        ],
        'null' => [
            'driver' => 'null',
        ],
    ],

    'context' => [
        'strategy' => \Sapiensly\OpenaiAgents\Persistence\Strategies\RecentMessagesStrategy::class,
        'max_messages' => 20,
        'max_tokens' => 3000,
        'include_summary' => true,
    ],

    'summarization' => [
        'enabled' => true,
        'after_messages' => 20,
        'model' => env('AGENT_SUMMARY_MODEL', 'gpt-3.5-turbo'),
        'max_length' => 500,
        'temperature' => 0.3,
    ],

    'cleanup' => [
        'enabled' => false,
        'older_than_days' => 90,
        'keep_summaries' => true,
    ],
];
