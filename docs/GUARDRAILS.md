# Guardrails - Safety and Validation System for OpenAI Agents

The Guardrails system provides a comprehensive safety and validation framework for AI agent conversations. It enables you to control, filter, transform, and validate both input and output content during agent execution.

## What are Guardrails?

Guardrails are validation mechanisms that act as safety barriers around your AI agent interactions. They operate at two critical points:

1. **Input Guardrails**: Process and validate user input before it reaches the AI agent
2. **Output Guardrails**: Process and validate AI responses before they're returned to the user

Think of guardrails as security checkpoints that ensure content meets your application's requirements, safety standards, and business rules.

## Core Functionality

Guardrails provide:

1. **Content Transformation**: Modify input or output content (e.g., formatting, sanitization)
2. **Safety Validation**: Block inappropriate or harmful content
3. **Business Rule Enforcement**: Ensure responses comply with organizational policies
4. **Data Sanitization**: Clean and normalize content
5. **Error Handling**: Gracefully handle validation failures

## Basic Implementation

### Input Guardrails

Input guardrails extend the `InputGuardrail` abstract class and must implement the `validate()` method:

```php
use Sapiensly\OpenaiAgents\Guardrails\InputGuardrail;

$inputGuardrail = new class extends InputGuardrail {
    public function validate(string $content): string
    {
        // Transform or validate input content
        return strtoupper($content);
    }
};

$runner->addInputGuardrail($inputGuardrail);
```

### Output Guardrails

Output guardrails extend the `OutputGuardrail` abstract class:

```php
use Sapiensly\OpenaiAgents\Guardrails\OutputGuardrail;
use Sapiensly\OpenaiAgents\Guardrails\OutputGuardrailException;

$outputGuardrail = new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        // Transform content or throw exception to block
        if (str_contains($content, 'forbidden')) {
            throw new OutputGuardrailException('Content blocked');
        }
        return $content;
    }
};

$runner->addOutputGuardrail($outputGuardrail);
```

## Practical Examples - From Simple to Complex

### 1. Basic Content Filtering

```php
// Remove profanity from input
$profanityFilter = new class extends InputGuardrail {
    public function validate(string $content): string
    {
        $badWords = ['spam', 'hack', 'virus'];
        return str_replace($badWords, '***', $content);
    }
};

// Replace negative words in output
$positivityFilter = new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        $replacements = [
            'terrible' => 'challenging',
            'awful' => 'difficult',
            'horrible' => 'unfortunate'
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
};

$runner->addInputGuardrail($profanityFilter);
$runner->addOutputGuardrail($positivityFilter);
```

### 2. Data Validation and Formatting

```php
// Validate and format email addresses in input
$emailValidator = new class extends InputGuardrail {
    public function validate(string $content): string
    {
        // Extract and validate email addresses
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $content, $matches)) {
            $email = $matches[1];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InputGuardrailException('Invalid email format detected');
            }
        }
        return $content;
    }
};

// Ensure output contains proper formatting
$formattingGuardrail = new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        // Add proper punctuation
        if (!str_ends_with(trim($content), '.')) {
            $content = trim($content) . '.';
        }

        // Capitalize first letter
        return ucfirst($content);
    }
};
```

### 3. Business Rule Enforcement

```php
// Prevent discussions of competitors
$competitorFilter = new class extends OutputGuardrail {
    private array $competitors = ['CompetitorA', 'CompetitorB', 'CompetitorC'];

    public function validate(string $content): string
    {
        foreach ($this->competitors as $competitor) {
            if (stripos($content, $competitor) !== false) {
                throw new OutputGuardrailException(
                    'Response contains reference to competitor: ' . $competitor
                );
            }
        }
        return $content;
    }
};

// Ensure brand guidelines compliance
$brandGuardrail = new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        // Replace generic terms with branded ones
        $brandReplacements = [
            'AI assistant' => 'YourBrand AI Assistant',
            'artificial intelligence' => 'YourBrand Intelligence',
        ];

        return str_ireplace(array_keys($brandReplacements), array_values($brandReplacements), $content);
    }
};
```

### 4. Security and Privacy Protection

```php
// Remove potential PII from input
$piiProtection = new class extends InputGuardrail {
    public function validate(string $content): string
    {
        // Remove social security numbers
        $content = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/', '[SSN REDACTED]', $content);

        // Remove credit card numbers
        $content = preg_replace('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', '[CARD REDACTED]', $content);

        // Remove phone numbers
        $content = preg_replace('/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', '[PHONE REDACTED]', $content);

        return $content;
    }
};

// Prevent disclosure of sensitive information in output
$informationLeakage = new class extends OutputGuardrail {
    private array $sensitivePatterns = [
        '/password/i',
        '/api[_\s]?key/i',
        '/secret/i',
        '/token/i',
        '/database/i'
    ];

    public function validate(string $content): string
    {
        foreach ($this->sensitivePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new OutputGuardrailException('Response contains potentially sensitive information');
            }
        }
        return $content;
    }
};
```

### 5. Advanced Content Analysis

```php
// Sentiment analysis guardrail
$sentimentGuardrail = new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        $negativeWords = ['hate', 'terrible', 'awful', 'disgusting', 'horrible'];
        $negativeCount = 0;

        foreach ($negativeWords as $word) {
            $negativeCount += substr_count(strtolower($content), $word);
        }

        // If response is too negative, ask for a more balanced response
        if ($negativeCount > 2) {
            throw new OutputGuardrailException('Response tone is too negative. Please provide a more balanced perspective.');
        }

        return $content;
    }
};

// Length and structure validation
$structureGuardrail = new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        // Ensure minimum length for quality responses
        if (strlen(trim($content)) < 50) {
            throw new OutputGuardrailException('Response is too short. Please provide more detailed information.');
        }

        // Ensure maximum length to prevent overwhelming responses
        if (strlen($content) > 2000) {
            // Truncate and add ellipsis
            $content = substr($content, 0, 1997) . '...';
        }

        return $content;
    }
};
```

### 6. Multi-Language Support

```php
// Language detection and translation
$languageGuardrail = new class extends InputGuardrail {
    public function validate(string $content): string
    {
        // Simple language detection (in real implementation, use a proper library)
        $spanishWords = ['hello', 'thank you', 'please', 'good morning'];
        $spanishCount = 0;

        foreach ($spanishWords as $word) {
            if (stripos($content, $word) !== false) {
                $spanishCount++;
            }
        }

        // If Spanish detected, add language context
        if ($spanishCount > 0) {
            return "[SPANISH] " . $content;
        }

        return $content;
    }
};

$responseLanguageGuardrail = new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        // Ensure consistent language in response
        // This is a simplified example - real implementation would use translation APIs
        if (str_starts_with($content, '[SPANISH]')) {
            // Ensure response is in Spanish or translate it
            return "Respuesta: " . str_replace('[SPANISH]', '', $content);
        }

        return $content;
    }
};
```

### 7. Industry-Specific Compliance

```php
// Healthcare HIPAA compliance
$hipaaGuardrail = new class extends OutputGuardrail {
    private array $phiPatterns = [
        '/patient\s+(\w+)/i',
        '/diagnosis\s*:\s*([^.]+)/i',
        '/medical\s+record\s+#?\s*(\w+)/i',
    ];

    public function validate(string $content): string
    {
        foreach ($this->phiPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new OutputGuardrailException('Response contains potential PHI (Protected Health Information)');
            }
        }

        // Add disclaimer for medical content
        if (stripos($content, 'medical') !== false || stripos($content, 'health') !== false) {
            $content .= "\n\n**Disclaimer**: This information is for educational purposes only and should not replace professional medical advice.";
        }

        return $content;
    }
};

// Financial compliance
$financialGuardrail = new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        // Check for investment advice
        $investmentTerms = ['buy', 'sell', 'invest', 'stock', 'portfolio'];
        $hasInvestmentTerms = false;

        foreach ($investmentTerms as $term) {
            if (stripos($content, $term) !== false) {
                $hasInvestmentTerms = true;
                break;
            }
        }

        if ($hasInvestmentTerms) {
            $content .= "\n\n**Investment Disclaimer**: This is not financial advice. Please consult with a qualified financial advisor before making investment decisions.";
        }

        return $content;
    }
};
```

### 8. Dynamic and Context-Aware Guardrails

```php
// Context-aware guardrail that adapts based on user role
class RoleBasedGuardrail extends OutputGuardrail
{
    private string $userRole;
    private array $rolePermissions;

    public function __construct(string $userRole)
    {
        $this->userRole = $userRole;
        $this->rolePermissions = [
            'admin' => ['technical', 'financial', 'confidential'],
            'user' => ['general'],
            'guest' => ['public']
        ];
    }

    public function validate(string $content): string
    {
        $permissions = $this->rolePermissions[$this->userRole] ?? ['public'];

        // Check if content contains restricted information
        if (!in_array('technical', $permissions) && stripos($content, 'database') !== false) {
            throw new OutputGuardrailException('Technical information restricted for this user role');
        }

        if (!in_array('financial', $permissions) && stripos($content, 'revenue') !== false) {
            throw new OutputGuardrailException('Financial information restricted for this user role');
        }

        if (!in_array('confidential', $permissions) && stripos($content, 'internal') !== false) {
            throw new OutputGuardrailException('Confidential information restricted for this user role');
        }

        return $content;
    }
}

// Usage
$userRole = 'user'; // This would come from your authentication system
$roleGuardrail = new RoleBasedGuardrail($userRole);
$runner->addOutputGuardrail($roleGuardrail);
```

### 9. Learning and Adaptive Guardrails

```php
// Guardrail that learns from flagged content
class AdaptiveGuardrail extends OutputGuardrail
{
    private array $flaggedPatterns = [];

    public function __construct()
    {
        // Load previously flagged patterns from database/cache
        $this->flaggedPatterns = Cache::get('flagged_patterns', []);
    }

    public function validate(string $content): string
    {
        // Check against learned patterns
        foreach ($this->flaggedPatterns as $pattern) {
            if (preg_match('/' . preg_quote($pattern, '/') . '/i', $content)) {
                throw new OutputGuardrailException("Content matches previously flagged pattern: {$pattern}");
            }
        }

        // Log for manual review if content contains certain triggers
        $reviewTriggers = ['controversial', 'sensitive', 'complaint'];
        foreach ($reviewTriggers as $trigger) {
            if (stripos($content, $trigger) !== false) {
                Log::info('Content flagged for review', [
                    'content' => $content,
                    'trigger' => $trigger,
                    'timestamp' => now()
                ]);
            }
        }

        return $content;
    }

    public function addFlaggedPattern(string $pattern): void
    {
        $this->flaggedPatterns[] = $pattern;
        Cache::put('flagged_patterns', $this->flaggedPatterns, now()->addDays(30));
    }
}
```

### 10. Real-Time External Validation

```php
// Guardrail that validates content against external APIs
class ExternalValidationGuardrail extends OutputGuardrail
{
    public function validate(string $content): string
    {
        // Check content against moderation API
        $response = Http::post('https://api.openai.com/v1/moderations', [
            'input' => $content
        ], [
            'Authorization' => 'Bearer ' . config('openai.api_key'),
            'Content-Type' => 'application/json'
        ]);

        if ($response->successful()) {
            $moderation = $response->json();
            if ($moderation['results'][0]['flagged'] ?? false) {
                $categories = $moderation['results'][0]['categories'] ?? [];
                $flaggedCategories = array_keys(array_filter($categories));

                throw new OutputGuardrailException(
                    'Content flagged by moderation API: ' . implode(', ', $flaggedCategories)
                );
            }
        }

        // Check against custom fact-checking API
        $factCheck = Http::post('https://your-factcheck-api.com/verify', [
            'text' => $content
        ]);

        if ($factCheck->successful()) {
            $result = $factCheck->json();
            if (($result['confidence'] ?? 0) < 0.7) {
                $content .= "\n\n**Note**: The accuracy of this information could not be fully verified.";
            }
        }

        return $content;
    }
}
```

## Complete Usage Example

Here's a comprehensive example showing how to combine multiple guardrails:

```php
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;

// Create agent and runner
$manager = app(AgentManager::class);
$agent = $manager->agent([], 'You are a helpful customer service assistant.');
$runner = new Runner($agent, maxTurns: 5);

// Input guardrails (applied in order)
$runner->addInputGuardrail(new class extends InputGuardrail {
    public function validate(string $content): string
    {
        // Remove excessive whitespace and normalize
        return trim(preg_replace('/\s+/', ' ', $content));
    }
});

$runner->addInputGuardrail(new class extends InputGuardrail {
    public function validate(string $content): string
    {
        // Block obvious spam
        if (preg_match('/\b(viagra|casino|lottery|winner)\b/i', $content)) {
            throw new InputGuardrailException('Spam content detected');
        }
        return $content;
    }
});

// Output guardrails (applied in order)
$runner->addOutputGuardrail(new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        // Ensure professional tone
        $unprofessional = ['stupid', 'dumb', 'idiotic'];
        foreach ($unprofessional as $word) {
            if (stripos($content, $word) !== false) {
                throw new OutputGuardrailException('Unprofessional language detected');
            }
        }
        return $content;
    }
});

$runner->addOutputGuardrail(new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        // Add company signature
        return $content . "\n\nBest regards,\nYour Customer Service Team";
    }
});

// Test the complete system
try {
    $response = $runner->run('Hello, I need help with my order');
    echo $response;
} catch (GuardrailException $e) {
    echo "Content blocked: " . $e->getMessage();
}
```

## Error Handling and Exception Types

Guardrails can throw exceptions to stop processing:

```php
use Sapiensly\OpenaiAgents\Guardrails\InputGuardrailException;
use Sapiensly\OpenaiAgents\Guardrails\OutputGuardrailException;
use Sapiensly\OpenaiAgents\Guardrails\GuardrailException;

// Custom exception handling
try {
    $response = $runner->run($userInput);
} catch (InputGuardrailException $e) {
    // Handle input validation failure
    return response()->json(['error' => 'Invalid input: ' . $e->getMessage()], 400);
} catch (OutputGuardrailException $e) {
    // Handle output validation failure
    Log::warning('Output blocked by guardrail', ['message' => $e->getMessage()]);
    return response()->json(['error' => 'Response blocked for safety'], 200);
} catch (GuardrailException $e) {
    // Handle any other guardrail exception
    return response()->json(['error' => 'Content validation failed'], 500);
}
```

## Best Practices

### 1. Layer Multiple Guardrails
```php
// Layer guardrails from specific to general
$runner->addInputGuardrail($sqlInjectionGuard);     // Most specific
$runner->addInputGuardrail($profanityFilter);       // Medium specificity
$runner->addInputGuardrail($lengthValidator);       // General
```

### 2. Performance Considerations
```php
// Cache expensive operations
class CachedModerationGuardrail extends OutputGuardrail
{
    public function validate(string $content): string
    {
        $hash = md5($content);
        $cached = Cache::get("moderation_{$hash}");

        if ($cached !== null) {
            if ($cached === 'blocked') {
                throw new OutputGuardrailException('Content previously flagged');
            }
            return $content;
        }

        // Perform expensive validation...
        $isBlocked = $this->performModerationCheck($content);

        Cache::put("moderation_{$hash}", $isBlocked ? 'blocked' : 'allowed', now()->addHours(24));

        if ($isBlocked) {
            throw new OutputGuardrailException('Content flagged by moderation');
        }

        return $content;
    }
}
```

### 3. Logging and Monitoring
```php
class MonitoringGuardrail extends OutputGuardrail
{
    public function validate(string $content): string
    {
        // Always log guardrail activity for monitoring
        Log::info('Guardrail processing', [
            'content_length' => strlen($content),
            'content_hash' => md5($content),
            'timestamp' => now(),
            'guardrail' => static::class
        ]);

        return $content;
    }
}
```

### 4. Configuration-Driven Guardrails
```php
// Create configurable guardrails
class ConfigurableContentFilter extends OutputGuardrail
{
    public function validate(string $content): string
    {
        $blockedWords = config('guardrails.blocked_words', []);
        $replacements = config('guardrails.word_replacements', []);

        // Apply blocks
        foreach ($blockedWords as $word) {
            if (stripos($content, $word) !== false) {
                throw new OutputGuardrailException("Blocked word detected: {$word}");
            }
        }

        // Apply replacements
        return str_ireplace(array_keys($replacements), array_values($replacements), $content);
    }
}
```

## Integration with Laravel Features

Guardrails integrate seamlessly with Laravel's ecosystem:

```php
// Use Laravel's validation
class LaravelValidationGuardrail extends InputGuardrail
{
    public function validate(string $content): string
    {
        $validator = Validator::make(['content' => $content], [
            'content' => 'required|max:1000|regex:/^[a-zA-Z0-9\s\.,!?]+$/'
        ]);

        if ($validator->fails()) {
            throw new InputGuardrailException('Content validation failed: ' . $validator->errors()->first());
        }

        return $content;
    }
}

// Use Laravel's events
class EventDrivenGuardrail extends OutputGuardrail
{
    public function validate(string $content): string
    {
        // Dispatch event for monitoring
        event(new GuardrailProcessed($content, static::class));

        return $content;
    }
}

// Use Laravel's queue for async processing
class AsyncModerationGuardrail extends OutputGuardrail
{
    public function validate(string $content): string
    {
        // Queue moderation for later processing
        dispatch(new ModerationJob($content));

        // For now, allow content but log for review
        Log::info('Content queued for moderation', ['content' => $content]);

        return $content;
    }
}
```

## Conclusion

Guardrails are essential for production AI applications, providing:

- **Safety**: Protect against harmful or inappropriate content
- **Compliance**: Ensure responses meet legal and business requirements
- **Quality**: Maintain consistent response quality and formatting
- **Security**: Prevent information leakage and protect sensitive data
- **Customization**: Adapt AI behavior to specific use cases and contexts

By implementing a comprehensive guardrail strategy, you transform your AI agents from unpredictable tools into reliable, safe, and compliant business assets that users can trust.

Remember: Guardrails should be layered, tested thoroughly, and regularly updated based on real-world usage patterns and emerging requirements.
