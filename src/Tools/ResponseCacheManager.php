<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Class ResponseCacheManager
 *
 * Caches complete agent responses for given input/context to avoid redundant OpenAI calls.
 */
class ResponseCacheManager
{
    /**
     * Default cache TTL in seconds (5 minutes)
     */
    private int $defaultTtl = 300;

    /**
     * Cache hit/miss statistics
     */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'invalidations' => 0,
    ];

    /**
     * Whether caching is enabled
     */
    private bool $enabled;

    public function __construct(bool $enabled = true, int $defaultTtl = 300)
    {
        $this->enabled = $enabled;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Generate a cache key for a response.
     *
     * @param string $input The user input
     * @param array $context The context (messages, agent, tools, etc.)
     * @return string The cache key
     */
    public function generateCacheKey(string $input, array $context = []): string
    {
        $normalizedInput = strtolower(trim($input));
        $contextHash = md5(serialize($context));
        return "response_cache:" . md5($normalizedInput . ':' . $contextHash);
    }

    /**
     * Get a cached response.
     *
     * @param string $input The user input
     * @param array $context The context
     * @return string|null The cached response, or null if not found
     */
    public function getCachedResponse(string $input, array $context = []): ?string
    {
        if (!$this->enabled) return null;
        $key = $this->generateCacheKey($input, $context);
        $cached = Cache::get($key);
        if ($cached !== null) {
            $this->stats['hits']++;
            Log::info("[ResponseCacheManager] Cache HIT for key: {$key}");
            return $cached;
        }
        $this->stats['misses']++;
        Log::info("[ResponseCacheManager] Cache MISS for key: {$key}");
        return null;
    }

    /**
     * Cache a response.
     *
     * @param string $input The user input
     * @param array $context The context
     * @param string $response The response to cache
     * @param int|null $ttl Optional TTL in seconds
     * @return void
     */
    public function cacheResponse(string $input, array $context, string $response, ?int $ttl = null): void
    {
        if (!$this->enabled) return;
        $key = $this->generateCacheKey($input, $context);
        Cache::put($key, $response, $ttl ?? $this->defaultTtl);
        Log::info("[ResponseCacheManager] Cached response for key: {$key}");
    }

    /**
     * Invalidate a cached response.
     *
     * @param string $input The user input
     * @param array $context The context
     * @return void
     */
    public function invalidateResponse(string $input, array $context = []): void
    {
        if (!$this->enabled) return;
        $key = $this->generateCacheKey($input, $context);
        Cache::forget($key);
        $this->stats['invalidations']++;
        Log::info("[ResponseCacheManager] Invalidated cache for key: {$key}");
    }

    /**
     * Clear all response cache (simplified).
     *
     * @return void
     */
    public function clearAllCache(): void
    {
        // In real implementation, use Redis SCAN or similar
        Log::info("[ResponseCacheManager] Cleared all response cache");
    }

    /**
     * Enable or disable caching.
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        Log::info("[ResponseCacheManager] Caching " . ($enabled ? 'enabled' : 'disabled'));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getCacheStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0;
        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'invalidations' => $this->stats['invalidations'],
            'total_requests' => $total,
            'hit_rate_percent' => round($hitRate, 2),
            'enabled' => $this->enabled,
            'default_ttl' => $this->defaultTtl
        ];
    }

    /**
     * Decide if a response should be cached (bypass for dynamic/personalized inputs).
     *
     * @param string $input
     * @param array $context
     * @return bool True if should bypass cache
     */
    public function shouldBypassCache(string $input, array $context = []): bool
    {
        $dynamicKeywords = ['now', 'hoy', 'actual', 'mi ', 'usuario', 'token', 'password', 'session', 'live', 'último', 'última'];
        $inputLower = strtolower($input);
        foreach ($dynamicKeywords as $kw) {
            if (str_contains($inputLower, $kw)) return true;
        }
        // Bypass for user/session specific context
        if (isset($context['user_id']) || isset($context['session_id'])) return true;
        return false;
    }
} 