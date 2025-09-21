<?php

declare(strict_types=1);

namespace AGUI\Encoder;

use AGUI\Core\Events\BaseEvent;

/**
 * Performance-optimized EventEncoder with caching capabilities
 *
 * @package AGUI\Encoder
 */
final class CachedEventEncoder extends EventEncoder
{
    /**
     * Maximum cache size (number of entries)
     */
    private const MAX_CACHE_SIZE = 1000;

    /**
     * Cache for encoded events (keyed by event content hash)
     *
     * @var array<string, string>
     */
    private array $encodeCache = [];

    /**
     * Cache for decoded events (keyed by JSON content hash)
     *
     * @var array<string, BaseEvent>
     */
    private array $decodeCache = [];

    /**
     * Cache for event type lookups
     *
     * @var array<string, string>
     */
    private array $typeCache = [];

    /**
     * LRU cache tracking for encode cache
     *
     * @var array<string, int>
     */
    private array $encodeCacheAccess = [];

    /**
     * LRU cache tracking for decode cache
     *
     * @var array<string, int>
     */
    private array $decodeCacheAccess = [];

    private int $cacheAccessCounter = 0;

    /**
     * @param bool $validateSchema Whether to validate events against schema
     * @param bool $includeTimestamp Whether to automatically include timestamps
     * @param bool $enableCaching Whether to enable caching (default: true)
     */
    public function __construct(
        bool $validateSchema = true,
        bool $includeTimestamp = true,
        private bool $enableCaching = true
    ) {
        parent::__construct($validateSchema, $includeTimestamp);
    }

    /**
     * {@inheritdoc}
     */
    public function encode(BaseEvent $event, ?array $context = null): string
    {
        if (!$this->enableCaching) {
            return parent::encode($event, $context);
        }

        $cacheKey = $this->generateEncodeCacheKey($event, $context);
        
        if (isset($this->encodeCache[$cacheKey])) {
            $this->updateCacheAccess($this->encodeCacheAccess, $cacheKey);
            return $this->encodeCache[$cacheKey];
        }

        $encoded = parent::encode($event, $context);
        $this->storeInEncodeCache($cacheKey, $encoded);

        return $encoded;
    }

    /**
     * {@inheritdoc}
     */
    public function decode(string $json, ?array $context = null): BaseEvent
    {
        if (!$this->enableCaching) {
            return parent::decode($json, $context);
        }

        $cacheKey = $this->generateDecodeCacheKey($json, $context);
        
        if (isset($this->decodeCache[$cacheKey])) {
            $this->updateCacheAccess($this->decodeCacheAccess, $cacheKey);
            return clone $this->decodeCache[$cacheKey]; // Return a clone to prevent mutations
        }

        $decoded = parent::decode($json, $context);
        $this->storeInDecodeCache($cacheKey, $decoded);

        return $decoded;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventType(string $json): string
    {
        if (!$this->enableCaching) {
            return parent::getEventType($json);
        }

        $cacheKey = hash('xxh3', $json);
        
        if (isset($this->typeCache[$cacheKey])) {
            return $this->typeCache[$cacheKey];
        }

        $eventType = parent::getEventType($json);
        
        // Type cache is small and doesn't need LRU management
        if (count($this->typeCache) < 500) {
            $this->typeCache[$cacheKey] = $eventType;
        }

        return $eventType;
    }

    /**
     * Enable or disable caching
     *
     * @param bool $enabled Whether to enable caching
     * @return self For method chaining
     */
    public function setCachingEnabled(bool $enabled): self
    {
        $this->enableCaching = $enabled;
        
        if (!$enabled) {
            $this->clearCache();
        }
        
        return $this;
    }

    /**
     * Check if caching is enabled
     *
     * @return bool True if caching is enabled
     */
    public function isCachingEnabled(): bool
    {
        return $this->enableCaching;
    }

    /**
     * Clear all caches
     *
     * @return self For method chaining
     */
    public function clearCache(): self
    {
        $this->encodeCache = [];
        $this->decodeCache = [];
        $this->typeCache = [];
        $this->encodeCacheAccess = [];
        $this->decodeCacheAccess = [];
        $this->cacheAccessCounter = 0;
        
        return $this;
    }

    /**
     * Get cache statistics
     *
     * @return array<string, mixed> Cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'encode_cache_size' => count($this->encodeCache),
            'decode_cache_size' => count($this->decodeCache),
            'type_cache_size' => count($this->typeCache),
            'max_cache_size' => self::MAX_CACHE_SIZE,
            'caching_enabled' => $this->enableCaching,
            'total_accesses' => $this->cacheAccessCounter,
            'memory_usage_bytes' => $this->estimateMemoryUsage()
        ];
    }

    /**
     * Generate cache key for encoding
     *
     * @param BaseEvent $event Event to encode
     * @param array<string, mixed>|null $context Optional context
     * @return string Cache key
     */
    private function generateEncodeCacheKey(BaseEvent $event, ?array $context): string
    {
        $eventData = $event->getFullData();
        
        // Create a deterministic hash from event data and context
        $keyData = [
            'event' => $eventData,
            'context' => $context,
            'schema_validation' => $this->isSchemaValidationEnabled(),
            'timestamp_enabled' => $this->isTimestampEnabled(),
            'schema_version' => $this->getSchemaVersion()
        ];
        
        return hash('xxh3', serialize($keyData));
    }

    /**
     * Generate cache key for decoding
     *
     * @param string $json JSON to decode
     * @param array<string, mixed>|null $context Optional context
     * @return string Cache key
     */
    private function generateDecodeCacheKey(string $json, ?array $context): string
    {
        $keyData = [
            'json' => $json,
            'context' => $context,
            'schema_validation' => $this->isSchemaValidationEnabled()
        ];
        
        return hash('xxh3', serialize($keyData));
    }

    /**
     * Store encoded result in cache with LRU eviction
     *
     * @param string $key Cache key
     * @param string $value Encoded value
     */
    private function storeInEncodeCache(string $key, string $value): void
    {
        if (count($this->encodeCache) >= self::MAX_CACHE_SIZE) {
            $this->evictLRU($this->encodeCache, $this->encodeCacheAccess);
        }
        
        $this->encodeCache[$key] = $value;
        $this->updateCacheAccess($this->encodeCacheAccess, $key);
    }

    /**
     * Store decoded result in cache with LRU eviction
     *
     * @param string $key Cache key
     * @param BaseEvent $value Decoded value
     */
    private function storeInDecodeCache(string $key, BaseEvent $value): void
    {
        if (count($this->decodeCache) >= self::MAX_CACHE_SIZE) {
            $this->evictLRU($this->decodeCache, $this->decodeCacheAccess);
        }
        
        $this->decodeCache[$key] = $value;
        $this->updateCacheAccess($this->decodeCacheAccess, $key);
    }

    /**
     * Update cache access tracking
     *
     * @param array<string, int> &$accessTracker Access tracking array
     * @param string $key Cache key
     */
    private function updateCacheAccess(array &$accessTracker, string $key): void
    {
        $accessTracker[$key] = ++$this->cacheAccessCounter;
    }

    /**
     * Evict least recently used item from cache
     *
     * @param array<string, mixed> &$cache Cache array
     * @param array<string, int> &$accessTracker Access tracking array
     */
    private function evictLRU(array &$cache, array &$accessTracker): void
    {
        if (empty($accessTracker)) {
            return;
        }

        // Find the key with the smallest access counter (least recently used)
        $lruKey = array_search(min($accessTracker), $accessTracker, true);
        
        if ($lruKey !== false) {
            unset($cache[$lruKey]);
            unset($accessTracker[$lruKey]);
        }
    }

    /**
     * Estimate memory usage of caches
     *
     * @return int Estimated memory usage in bytes
     */
    private function estimateMemoryUsage(): int
    {
        $encodeMemory = array_sum(array_map('strlen', $this->encodeCache));
        $decodeMemory = count($this->decodeCache) * 1024; // Rough estimate for objects
        $typeMemory = array_sum(array_map('strlen', $this->typeCache));
        $trackingMemory = (count($this->encodeCacheAccess) + count($this->decodeCacheAccess)) * 8; // 8 bytes per int
        
        return $encodeMemory + $decodeMemory + $typeMemory + $trackingMemory;
    }
}
