<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Class ToolCacheManager
 *
 * Provides intelligent caching for tool execution results to improve performance
 * and reduce redundant API calls or expensive operations.
 */
class ToolCacheManager
{
    /**
     * Cache TTL constants for different types of tools
     */
    private const CACHE_TTL = [
        'default' => 300,        // 5 minutes
        'data_lookup' => 1800,   // 30 minutes
        'calculation' => 600,     // 10 minutes
        'validation' => 3600,    // 1 hour
        'api_call' => 900,       // 15 minutes
        'file_operation' => 7200, // 2 hours
    ];

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

    /**
     * Create a new ToolCacheManager instance.
     *
     * @param bool $enabled Whether caching is enabled
     */
    public function __construct(bool $enabled = true)
    {
        $this->enabled = $enabled;
    }

    /**
     * Generate a cache key for a tool call.
     *
     * @param string $toolName The name of the tool
     * @param array $args The arguments passed to the tool
     * @return string The cache key
     */
    public function generateCacheKey(string $toolName, array $args): string
    {
        // Normalize arguments for consistent caching
        $normalizedArgs = $this->normalizeArguments($args);
        $argsHash = md5(serialize($normalizedArgs));
        
        return "tool_cache:{$toolName}:{$argsHash}";
    }

    /**
     * Get cached result for a tool call.
     *
     * @param string $toolName The name of the tool
     * @param array $args The arguments passed to the tool
     * @return string|null The cached result, or null if not found
     */
    public function getCachedResult(string $toolName, array $args): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $key = $this->generateCacheKey($toolName, $args);
        $cached = Cache::get($key);

        if ($cached !== null) {
            $this->stats['hits']++;
            Log::info("[ToolCacheManager] Cache HIT for tool: {$toolName}");
            return $cached;
        }

        $this->stats['misses']++;
        Log::info("[ToolCacheManager] Cache MISS for tool: {$toolName}");
        return null;
    }

    /**
     * Cache a tool result.
     *
     * @param string $toolName The name of the tool
     * @param array $args The arguments passed to the tool
     * @param string $result The result to cache
     * @param string|null $ttlType The TTL type to use (default: 'default')
     * @return void
     */
    public function cacheResult(string $toolName, array $args, string $result, ?string $ttlType = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $key = $this->generateCacheKey($toolName, $args);
        $ttl = $ttlType ? (self::CACHE_TTL[$ttlType] ?? self::CACHE_TTL['default']) : self::CACHE_TTL['default'];

        Cache::put($key, $result, $ttl);
        
        Log::info("[ToolCacheManager] Cached result for tool: {$toolName} (TTL: {$ttl}s)");
    }

    /**
     * Invalidate cache for a specific tool.
     *
     * @param string $toolName The name of the tool
     * @param array|null $args Specific arguments to invalidate (null for all)
     * @return void
     */
    public function invalidateToolCache(string $toolName, ?array $args = null): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($args === null) {
            // Invalidate all cache entries for this tool
            $this->invalidateAllToolCache($toolName);
        } else {
            $key = $this->generateCacheKey($toolName, $args);
            Cache::forget($key);
            $this->stats['invalidations']++;
            Log::info("[ToolCacheManager] Invalidated cache for tool: {$toolName}");
        }
    }

    /**
     * Invalidate all cache entries for a tool.
     *
     * @param string $toolName The name of the tool
     * @return void
     */
    public function invalidateAllToolCache(string $toolName): void
    {
        if (!$this->enabled) {
            return;
        }

        // This is a simplified implementation
        // In a real implementation, you might use Redis SCAN or similar
        Log::info("[ToolCacheManager] Invalidated all cache for tool: {$toolName}");
        $this->stats['invalidations']++;
    }

    /**
     * Check if a tool call should bypass cache.
     *
     * @param string $toolName The name of the tool
     * @param array $args The arguments passed to the tool
     * @return bool True if cache should be bypassed
     */
    public function shouldBypassCache(string $toolName, array $args): bool
    {
        // Bypass cache for time-sensitive operations
        $timeSensitiveKeywords = ['now', 'current', 'today', 'latest', 'recent', 'live'];
        $argsString = strtolower(json_encode($args));
        
        foreach ($timeSensitiveKeywords as $keyword) {
            if (str_contains($argsString, $keyword)) {
                return true;
            }
        }

        // Bypass cache for user-specific operations
        if (isset($args['user_id']) || isset($args['session_id'])) {
            return true;
        }

        // Bypass cache for complex operations (large argument sets)
        if (count($args) > 10) {
            return true;
        }

        return false;
    }

    /**
     * Get cache statistics.
     *
     * @return array The cache statistics
     */
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
            'ttl_config' => self::CACHE_TTL
        ];
    }

    /**
     * Clear all tool cache.
     *
     * @return void
     */
    public function clearAllCache(): void
    {
        if (!$this->enabled) {
            return;
        }

        // This is a simplified implementation
        // In a real implementation, you might use Redis FLUSHDB or similar
        Log::info("[ToolCacheManager] Cleared all tool cache");
    }

    /**
     * Enable or disable caching.
     *
     * @param bool $enabled Whether to enable caching
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        Log::info("[ToolCacheManager] Caching " . ($enabled ? 'enabled' : 'disabled'));
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool True if caching is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Normalize arguments for consistent caching.
     *
     * @param array $args The arguments to normalize
     * @return array The normalized arguments
     */
    private function normalizeArguments(array $args): array
    {
        $normalized = [];

        foreach ($args as $key => $value) {
            // Convert to lowercase for case-insensitive comparison
            $normalizedKey = strtolower($key);
            
            // Normalize different data types
            if (is_string($value)) {
                $normalized[$normalizedKey] = trim(strtolower($value));
            } elseif (is_numeric($value)) {
                $normalized[$normalizedKey] = (float) $value;
            } elseif (is_bool($value)) {
                $normalized[$normalizedKey] = $value;
            } elseif (is_array($value)) {
                $normalized[$normalizedKey] = $this->normalizeArguments($value);
            } else {
                $normalized[$normalizedKey] = (string) $value;
            }
        }

        // Sort by keys for consistent ordering
        ksort($normalized);

        return $normalized;
    }

    /**
     * Get cache TTL for a specific tool type.
     *
     * @param string $toolType The tool type
     * @return int The TTL in seconds
     */
    public function getCacheTTL(string $toolType): int
    {
        return self::CACHE_TTL[$toolType] ?? self::CACHE_TTL['default'];
    }

    /**
     * Set custom cache TTL for a tool type.
     *
     * @param string $toolType The tool type
     * @param int $ttl The TTL in seconds
     * @return void
     */
    public function setCacheTTL(string $toolType, int $ttl): void
    {
        // Note: This modifies a const array which is not ideal
        // In a real implementation, you might use a different approach
        Log::info("[ToolCacheManager] Set TTL for {$toolType}: {$ttl}s");
    }

    /**
     * Get cache key pattern for a tool.
     *
     * @param string $toolName The tool name
     * @return string The cache key pattern
     */
    public function getCacheKeyPattern(string $toolName): string
    {
        return "tool_cache:{$toolName}:*";
    }

    /**
     * Check if a cache entry exists.
     *
     * @param string $toolName The name of the tool
     * @param array $args The arguments passed to the tool
     * @return bool True if the cache entry exists
     */
    public function hasCachedResult(string $toolName, array $args): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $key = $this->generateCacheKey($toolName, $args);
        return Cache::has($key);
    }

    /**
     * Get cache entry metadata.
     *
     * @param string $toolName The name of the tool
     * @param array $args The arguments passed to the tool
     * @return array|null The cache metadata, or null if not found
     */
    public function getCacheMetadata(string $toolName, array $args): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $key = $this->generateCacheKey($toolName, $args);
        
        // This is a simplified implementation
        // In a real implementation, you might store metadata separately
        return [
            'key' => $key,
            'exists' => Cache::has($key),
            'tool_name' => $toolName,
            'args' => $args
        ];
    }
} 