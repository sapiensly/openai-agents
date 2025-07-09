<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Class IntelligentCacheManager
 *
 * Provides intelligent caching for handoff operations, agent responses,
 * and conversation context to improve performance and reduce API calls.
 */
class IntelligentCacheManager
{
    /**
     * Cache TTL constants
     */
    private const CACHE_TTL = [
        'handoff_suggestion' => 3600, // 1 hour
        'agent_response' => 1800,     // 30 minutes
        'context_analysis' => 7200,   // 2 hours
        'parallel_result' => 900,     // 15 minutes
        'validation_result' => 3600,  // 1 hour
    ];

    /**
     * Create a new IntelligentCacheManager instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Generate a cache key for handoff suggestions.
     *
     * @param string $userInput The user input
     * @param array $context The conversation context
     * @return string The cache key
     */
    public function getHandoffSuggestionKey(string $userInput, array $context = []): string
    {
        $normalizedInput = $this->normalizeInput($userInput);
        $contextHash = md5(serialize($context));
        return "handoff_suggestion:{$normalizedInput}:{$contextHash}";
    }

    /**
     * Get cached handoff suggestion.
     *
     * @param string $userInput The user input
     * @param array $context The conversation context
     * @return HandoffSuggestion|null The cached suggestion, or null if not found
     */
    public function getCachedHandoffSuggestion(string $userInput, array $context = []): ?HandoffSuggestion
    {
        $key = $this->getHandoffSuggestionKey($userInput, $context);
        $cached = Cache::get($key);

        if ($cached && $cached instanceof HandoffSuggestion) {
            Log::info("[IntelligentCacheManager] Cache hit for handoff suggestion");
            return $cached;
        }

        return null;
    }

    /**
     * Cache a handoff suggestion.
     *
     * @param string $userInput The user input
     * @param array $context The conversation context
     * @param HandoffSuggestion $suggestion The handoff suggestion
     * @return void
     */
    public function cacheHandoffSuggestion(string $userInput, array $context, HandoffSuggestion $suggestion): void
    {
        $key = $this->getHandoffSuggestionKey($userInput, $context);
        Cache::put($key, $suggestion, self::CACHE_TTL['handoff_suggestion']);
        
        Log::info("[IntelligentCacheManager] Cached handoff suggestion for key: {$key}");
    }

    /**
     * Generate a cache key for agent responses.
     *
     * @param string $agentId The agent ID
     * @param string $userInput The user input
     * @param array $context The conversation context
     * @return string The cache key
     */
    public function getAgentResponseKey(string $agentId, string $userInput, array $context = []): string
    {
        $normalizedInput = $this->normalizeInput($userInput);
        $contextHash = md5(serialize($context));
        return "agent_response:{$agentId}:{$normalizedInput}:{$contextHash}";
    }

    /**
     * Get cached agent response.
     *
     * @param string $agentId The agent ID
     * @param string $userInput The user input
     * @param array $context The conversation context
     * @return string|null The cached response, or null if not found
     */
    public function getCachedAgentResponse(string $agentId, string $userInput, array $context = []): ?string
    {
        $key = $this->getAgentResponseKey($agentId, $userInput, $context);
        $cached = Cache::get($key);

        if ($cached && is_string($cached)) {
            Log::info("[IntelligentCacheManager] Cache hit for agent response");
            return $cached;
        }

        return null;
    }

    /**
     * Cache an agent response.
     *
     * @param string $agentId The agent ID
     * @param string $userInput The user input
     * @param array $context The conversation context
     * @param string $response The agent response
     * @return void
     */
    public function cacheAgentResponse(string $agentId, string $userInput, array $context, string $response): void
    {
        $key = $this->getAgentResponseKey($agentId, $userInput, $context);
        Cache::put($key, $response, self::CACHE_TTL['agent_response']);
        
        Log::info("[IntelligentCacheManager] Cached agent response for key: {$key}");
    }

    /**
     * Generate a cache key for parallel handoff results.
     *
     * @param string $userInput The user input
     * @param array $agentIds The agent IDs
     * @return string The cache key
     */
    public function getParallelResultKey(string $userInput, array $agentIds): string
    {
        $normalizedInput = $this->normalizeInput($userInput);
        sort($agentIds);
        $agentsHash = md5(implode(',', $agentIds));
        return "parallel_result:{$normalizedInput}:{$agentsHash}";
    }

    /**
     * Get cached parallel handoff result.
     *
     * @param string $userInput The user input
     * @param array $agentIds The agent IDs
     * @return ParallelHandoffResult|null The cached result, or null if not found
     */
    public function getCachedParallelResult(string $userInput, array $agentIds): ?ParallelHandoffResult
    {
        $key = $this->getParallelResultKey($userInput, $agentIds);
        $cached = Cache::get($key);

        if ($cached && $cached instanceof ParallelHandoffResult) {
            Log::info("[IntelligentCacheManager] Cache hit for parallel handoff result");
            return $cached;
        }

        return null;
    }

    /**
     * Cache a parallel handoff result.
     *
     * @param string $userInput The user input
     * @param array $agentIds The agent IDs
     * @param ParallelHandoffResult $result The parallel handoff result
     * @return void
     */
    public function cacheParallelResult(string $userInput, array $agentIds, ParallelHandoffResult $result): void
    {
        $key = $this->getParallelResultKey($userInput, $agentIds);
        Cache::put($key, $result, self::CACHE_TTL['parallel_result']);
        
        Log::info("[IntelligentCacheManager] Cached parallel handoff result for key: {$key}");
    }

    /**
     * Generate a cache key for validation results.
     *
     * @param HandoffRequest $request The handoff request
     * @return string The cache key
     */
    public function getValidationResultKey(HandoffRequest $request): string
    {
        $requestHash = md5(serialize([
            'source' => $request->sourceAgentId,
            'target' => $request->targetAgentId,
            'reason' => $request->reason,
            'capabilities' => $request->requiredCapabilities
        ]));
        
        return "validation_result:{$requestHash}";
    }

    /**
     * Get cached validation result.
     *
     * @param HandoffRequest $request The handoff request
     * @return ValidationResult|null The cached validation result, or null if not found
     */
    public function getCachedValidationResult(HandoffRequest $request): ?ValidationResult
    {
        $key = $this->getValidationResultKey($request);
        $cached = Cache::get($key);

        if ($cached && $cached instanceof ValidationResult) {
            Log::info("[IntelligentCacheManager] Cache hit for validation result");
            return $cached;
        }

        return null;
    }

    /**
     * Cache a validation result.
     *
     * @param HandoffRequest $request The handoff request
     * @param ValidationResult $result The validation result
     * @return void
     */
    public function cacheValidationResult(HandoffRequest $request, ValidationResult $result): void
    {
        $key = $this->getValidationResultKey($request);
        Cache::put($key, $result, self::CACHE_TTL['validation_result']);
        
        Log::info("[IntelligentCacheManager] Cached validation result for key: {$key}");
    }

    /**
     * Check if caching should be bypassed for a request.
     *
     * @param string $userInput The user input
     * @param array $context The conversation context
     * @return bool True if caching should be bypassed
     */
    public function shouldBypassCache(string $userInput, array $context = []): bool
    {
        // Bypass cache for time-sensitive queries
        $timeSensitiveKeywords = ['now', 'current', 'today', 'latest', 'recent', 'live'];
        $input = strtolower($userInput);
        
        foreach ($timeSensitiveKeywords as $keyword) {
            if (str_contains($input, $keyword)) {
                return true;
            }
        }

        // Bypass cache for user-specific queries
        if (isset($context['user_id']) || isset($context['session_id'])) {
            return true;
        }

        // Bypass cache for complex queries (longer than 100 characters)
        if (strlen($userInput) > 100) {
            return true;
        }

        return false;
    }

    /**
     * Clear all handoff-related caches.
     *
     * @return void
     */
    public function clearAllCaches(): void
    {
        $patterns = [
            'handoff_suggestion:*',
            'agent_response:*',
            'parallel_result:*',
            'validation_result:*',
            'context_analysis:*'
        ];

        foreach ($patterns as $pattern) {
            $this->clearCacheByPattern($pattern);
        }

        Log::info("[IntelligentCacheManager] Cleared all handoff-related caches");
    }

    /**
     * Clear cache by pattern.
     *
     * @param string $pattern The cache key pattern
     * @return void
     */
    private function clearCacheByPattern(string $pattern): void
    {
        // This is a simplified implementation
        // In a real implementation, you might use Redis SCAN or similar
        Log::info("[IntelligentCacheManager] Clearing cache pattern: {$pattern}");
    }

    /**
     * Get cache statistics.
     *
     * @return array The cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'cache_ttl' => self::CACHE_TTL,
            'cache_patterns' => [
                'handoff_suggestion' => 'handoff_suggestion:*',
                'agent_response' => 'agent_response:*',
                'parallel_result' => 'parallel_result:*',
                'validation_result' => 'validation_result:*'
            ]
        ];
    }

    /**
     * Normalize input for consistent caching.
     *
     * @param string $input The input to normalize
     * @return string The normalized input
     */
    private function normalizeInput(string $input): string
    {
        // Convert to lowercase
        $normalized = strtolower($input);
        
        // Remove extra whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        // Remove punctuation (optional, depending on your needs)
        // $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        
        // Trim
        $normalized = trim($normalized);
        
        // Create a hash for very long inputs
        if (strlen($normalized) > 200) {
            $normalized = md5($normalized);
        }
        
        return $normalized;
    }

    /**
     * Check if a cache entry is still valid.
     *
     * @param string $key The cache key
     * @return bool True if the cache entry is valid
     */
    public function isCacheValid(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Get cache hit rate statistics.
     *
     * @return array The cache hit rate statistics
     */
    public function getCacheHitRate(): array
    {
        // This would typically be implemented with a more sophisticated
        // caching system that tracks hit rates
        return [
            'handoff_suggestions' => 'N/A',
            'agent_responses' => 'N/A',
            'parallel_results' => 'N/A',
            'validation_results' => 'N/A'
        ];
    }
} 