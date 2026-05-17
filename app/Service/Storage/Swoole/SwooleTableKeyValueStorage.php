<?php

declare(strict_types=1);

namespace App\Service\Storage\Swoole;

use App\Contract\Storage\CacheStorage;
use Psr\Log\LoggerInterface;
use Swoole\Table;

class SwooleTableKeyValueStorage implements CacheStorage
{
    private const int EXPIRES_COLUMN_SIZE = 8;

    private readonly Table $table;

    public function __construct(
        private readonly LoggerInterface $logger,
        int $size = 1024,
        private readonly int $ttl = 3600,
        private readonly int $maxValueSize = 256,
    ) {
        $this->table = new Table($size);
        $this->table->column('value', Table::TYPE_STRING, $maxValueSize);
        $this->table->column('expires', Table::TYPE_INT, self::EXPIRES_COLUMN_SIZE);
        $this->table->create();
    }

    public function get(string $key): ?string
    {
        $row = $this->table->get($key);

        if ($row === null || !is_array($row)) {
            return null;
        }

        // TTL check
        $now = time();
        $expired = $row['expires'] < $now;

        if ($expired) {
            $this->table->del($key);
            return null;
        }

        /** @var string $value */
        $value = $row['value'];
        return $value;
    }

    public function set(string $key, string $value, ?int $ttl = null): bool
    {
        // strlen is safe here because cache values are ASCII strings (IDs, counters, tokens).
        // For UTF-8 use mb_strlen($value, '8bit') — adds ~3-5ms per 65k sets, negligible.
        if (strlen($value) > $this->maxValueSize) {
            $this->logger->warning(
                'Swoole storage value too large for cache, consider increasing CACHE_VALUE_MAX_SIZE in .env',
                ['key' => $key, 'size' => strlen($value)]
            );
            return false;
        }

        $expires = time() + ($ttl ?? $this->ttl);

        $result = @$this->table->set($key, [
            'value' => $value,
            'expires' => $expires,
        ]);

        if ($result) {
            return true;
        }

        $this->logger->warning(
            'Swoole storage failed to set cache key (table full?). Consider increasing CACHE_MAX_SIZE in .env',
            ['key' => $key]
        );
        return false;
    }

    public function delete(string $key): bool
    {
        $result = $this->table->del($key);

        return $result;
    }

    public function has(string $key): bool
    {
        $row = $this->table->get($key);
        if ($row === null || !is_array($row)) {
            return false;
        }

        $now = time();
        if ($row['expires'] < $now) {
            $this->table->del($key);
            return false;
        }

        return true;
    }

    public function count(): int
    {
        return $this->table->count();
    }

    public function cleanExpired(): int
    {
        $now = time();
        $deleted = 0;

        foreach ($this->table as $key => $row) {
            if (is_array($row) && ($row['expires'] < $now)) {
                /** @var string $key */
                $this->table->del($key);
                $deleted++;
            }
        }

        return $deleted;
    }
}
