# Model Provider Abstraction

The Laravel OpenAI Agents package includes a comprehensive **Model Provider Abstraction** system that allows you to easily switch between different AI model providers (OpenAI, Anthropic, Google, etc.) without changing your application code.

## Overview

The Model Provider Abstraction provides:

- **Unified Interface**: All providers implement the same interface
- **Easy Switching**: Change providers with a single configuration change
- **Extensible**: Add new providers without modifying core code
- **Provider Management**: Centralized management of multiple providers
- **Statistics**: Track usage across all providers
- **Connection Testing**: Test provider connectivity

## Architecture

```
ModelProviderManager
├── OpenAIProvider (implements ModelProviderInterface)
├── AnthropicProvider (implements ModelProviderInterface)
├── GoogleProvider (implements ModelProviderInterface)
└── CustomProvider (implements ModelProviderInterface)
```

## Available Providers

### OpenAI Provider

The default provider that uses OpenAI's API.

**Configuration:**
```php
// config/agents.php
'provider' => 'openai',
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
],
```

**Features:**
- GPT-4o, GPT-4o Mini, GPT-3.5 Turbo
- Whisper for audio transcription
- TTS for speech generation
- Streaming support

### Adding New Providers

To add a new provider (e.g., Anthropic):

1. **Create the Provider Class:**
```php
<?php

namespace Sapiensly\OpenaiAgents\Providers;

use Sapiensly\OpenaiAgents\Interfaces\ModelProviderInterface;

class AnthropicProvider implements ModelProviderInterface
{
    public function getName(): string
    {
        return 'Anthropic';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAvailableModels(): array
    {
        return [
            'claude-3-opus' => [
                'name' => 'Claude 3 Opus',
                'type' => 'chat',
                'max_tokens' => 200000,
                'supports_streaming' => true,
            ],
            'claude-3-sonnet' => [
                'name' => 'Claude 3 Sonnet',
                'type' => 'chat',
                'max_tokens' => 200000,
                'supports_streaming' => true,
            ],
        ];
    }

    public function createChatCompletion(array $messages, array $options = []): array
    {
        // Implement Anthropic API call
        $response = $this->client->messages()->create([
            'model' => $options['model'] ?? 'claude-3-sonnet',
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 1000,
        ]);

        return [
            'content' => $response->content[0]->text,
            'finish_reason' => $response->stop_reason,
            'usage' => [
                'input_tokens' => $response->usage->inputTokens,
                'output_tokens' => $response->usage->outputTokens,
            ],
        ];
    }

    // Implement other required methods...
}
```

2. **Register the Provider:**
```php
// In your service provider or application bootstrap
$manager = app(ModelProviderManager::class);
$manager->registerProvider('anthropic', new AnthropicProvider([
    'api_key' => env('ANTHROPIC_API_KEY'),
]));
```

3. **Update Configuration:**
```php
// config/agents.php
'provider' => 'anthropic', // or 'openai' or any registered provider
```

## Usage Examples

### Basic Usage

```php
use Sapiensly\OpenaiAgents\Providers\ModelProviderManager;

$manager = app(ModelProviderManager::class);

// Use current provider
$response = $manager->createChatCompletion([
    ['role' => 'user', 'content' => 'Hello!']
]);

// Switch providers
$manager->setCurrentProvider('anthropic');
$response = $manager->createChatCompletion([
    ['role' => 'user', 'content' => 'Hello!']
]);
```

### Provider Information

```php
$manager = app(ModelProviderManager::class);

// Get current provider info
$provider = $manager->getCurrentProvider();
echo "Current provider: " . $provider->getName() . " v" . $provider->getVersion();

// Get all available models
$models = $manager->getAllAvailableModels();
foreach ($models as $providerName => $providerModels) {
    echo "Provider {$providerName} has " . count($providerModels) . " models\n";
}

// Find a specific model
$modelInfo = $manager->findModel('gpt-4o');
if ($modelInfo) {
    echo "Model found in provider: " . $modelInfo['provider'] . "\n";
}
```

### Testing and Statistics

```php
$manager = app(ModelProviderManager::class);

// Test all connections
$connections = $manager->testAllConnections();
foreach ($connections as $provider => $info) {
    $status = $info['connection'] ? '✅' : '❌';
    echo "{$status} {$provider}: {$info['name']} v{$info['version']}\n";
}

// Get statistics
$stats = $manager->getAllStats();
foreach ($stats as $provider => $providerStats) {
    echo "{$provider}: {$providerStats['requests']} requests, {$providerStats['errors']} errors\n";
}
```

### Integration with Agents

The ModelProviderManager is automatically integrated with the Agent system:

```php
use Sapiensly\OpenaiAgents\AgentManager;

$manager = app(AgentManager::class);

// Create agent with specific provider
$agent = $manager->agent([
    'provider' => 'anthropic', // Use Anthropic instead of OpenAI
    'model' => 'claude-3-sonnet',
]);

// The agent will automatically use the specified provider
$response = $agent->chat('Hello!');
```

## Configuration

### Environment Variables

```env
# Default provider
AGENTS_PROVIDER=openai

# OpenAI configuration
OPENAI_API_KEY=your-openai-key
OPENAI_ORGANIZATION=your-org-id

# Anthropic configuration (if using Anthropic provider)
ANTHROPIC_API_KEY=your-anthropic-key

# Google configuration (if using Google provider)
GOOGLE_API_KEY=your-google-key
```

### Configuration File

```php
// config/agents.php
return [
    'provider' => env('AGENTS_PROVIDER', 'openai'),
    
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
        ],
        'google' => [
            'api_key' => env('GOOGLE_API_KEY'),
        ],
    ],
];
```

## Advanced Features

### Custom Provider Implementation

For advanced use cases, you can implement custom providers:

```php
class CustomProvider implements ModelProviderInterface
{
    public function createChatCompletion(array $messages, array $options = []): array
    {
        // Custom implementation
        $response = $this->makeCustomAPICall($messages, $options);
        
        return [
            'content' => $response['text'],
            'finish_reason' => $response['reason'],
            'usage' => $response['usage'],
        ];
    }
    
    // Implement other methods...
}
```

### Provider-Specific Features

Some providers may support features that others don't:

```php
$manager = app(ModelProviderManager::class);
$provider = $manager->getCurrentProvider();

// Check if provider supports specific features
if (method_exists($provider, 'createImage')) {
    $image = $provider->createImage('A beautiful sunset');
}

// Use provider-specific methods
if ($provider instanceof OpenAIProvider) {
    $embeddings = $provider->createEmbeddings(['Hello world']);
}
```

## Best Practices

1. **Provider Selection**: Choose providers based on your needs (cost, performance, features)
2. **Fallback Strategy**: Implement fallback to other providers if one fails
3. **Monitoring**: Track usage and costs across different providers
4. **Testing**: Test all providers before deploying to production
5. **Documentation**: Document provider-specific features and limitations

## Troubleshooting

### Common Issues

**Provider not found:**
```bash
# Check registered providers
php artisan tinker
>>> app(ModelProviderManager::class)->getProviderNames()
```

**Connection failed:**
```bash
# Test connections
php artisan tinker
>>> app(ModelProviderManager::class)->testAllConnections()
```

**Model not available:**
```bash
# Check available models
php artisan tinker
>>> app(ModelProviderManager::class)->getAllAvailableModels()
```

## Contributing

To add a new provider:

1. Create the provider class implementing `ModelProviderInterface`
2. Add comprehensive tests
3. Update documentation
4. Submit a pull request

## Future Enhancements

- **Provider Auto-Discovery**: Automatically detect available providers
- **Load Balancing**: Distribute requests across multiple providers
- **Cost Optimization**: Automatically choose the most cost-effective provider
- **Provider-Specific Features**: Support for provider-specific capabilities
- **Caching**: Cache responses across providers 