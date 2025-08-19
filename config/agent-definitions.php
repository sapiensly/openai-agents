<?php

return [
    // Master switch for definition persistence (does not affect conversation persistence)
    'enabled' => env('AGENT_DEFINITIONS_ENABLED', true),

    // Store driver: 'null' (default, no-op) | 'database' (optional, if implementation exists)
    'store' => env('AGENT_DEFINITIONS_STORE', 'null'),
];
