<?php

declare(strict_types=1);

namespace App\Contract\Storage;

/**
 * Key-value storage interface
 */
interface KeyValueStorage
{
    /**
     * Get value by key
     *
     * @return null|string Returns null if key not found
     */
    public function get(string $key): ?string;

    /**
     * Store value with optional TTL
     *
     * @param int|null $ttl Time to live in seconds
     */
    public function set(string $key, string $value, ?int $ttl = null): bool;

    /**
     * Delete key
     */
    public function delete(string $key): bool;

    /**
     * Check if key exists
     */
    public function has(string $key): bool;

    /**
     * Return KV pairs count
     */
    public function count(): int;
}
