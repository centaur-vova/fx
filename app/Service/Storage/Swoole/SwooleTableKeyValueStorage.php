<?php

declare(strict_types=1);

namespace App\Service\Storage\Swoole;

use App\Contract\Storage\CacheStorage;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine as Co;
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

        $this->logger->debug('Swoole storage get raw', [
            'key' => $key,
            'row_exists' => $row !== false,
            'pid' => getmypid(),
        ]);

        if ($row === null || !is_array($row)) {
            return null;
        }

        // TTL check
        $now = time();
        $expired = $row['expires'] < $now;

        $this->logger->debug('Swoole storage get detail', [
            'key' => $key,
            'value' => $row['value'],
            'expires_at' => $row['expires'],
            'now' => $now,
            'expired' => $expired,
            'pid' => getmypid(),
        ]);

        if ($expired) {
            $this->table->del($key);
            $this->logger->debug('Swoole storage get deleted expired', ['key' => $key, 'pid' => getmypid()]);
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

        $this->logger->debug('Swoole storage set', [
            'key' => $key,
            'value' => $value,
            'expires_at' => $expires,
            'ttl_arg' => $ttl,
            'default_ttl' => $this->ttl,
            'pid' => getmypid(),
        ]);

        $result = @$this->table->set($key, [
            'value' => $value,
            'expires' => $expires,
        ]);

        $this->logger->debug('Swoole storage set result', [
            'key' => $key,
            'success' => $result,
            'pid' => getmypid(),
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

        $this->logger->debug('Swoole storage delete', [
            'key' => $key,
            'success' => $result,
            'pid' => getmypid(),
        ]);

        return $result;
    }

    public function has(string $key): bool
    {
        $row = $this->table->get($key);
        if ($row === null || !is_array($row)) {
            $this->logger->debug('Swoole storage has false', ['key' => $key, 'reason' => 'no_row', 'pid' => getmypid()]);
            return false;
        }

        $now = time();
        if ($row['expires'] < $now) {
            $this->table->del($key);
            $this->logger->debug('Swoole storage has false', ['key' => $key, 'reason' => 'expired', 'pid' => getmypid()]);
            return false;
        }

        $this->logger->debug('Swoole storage has true', ['key' => $key, 'pid' => getmypid()]);
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
                $this->logger->debug('Swoole storage clean deleted', ['key' => $key, 'pid' => getmypid()]);
            }
        }

        return $deleted;
    }

    public function startCleaner(int $interval = 60): void
    {
        $logger = $this->logger;

        go(function () use ($interval, $logger): void {
            /** @phpstan-ignore-next-line */
            while (true) {
                Co::sleep($interval);
                $deleted = $this->cleanExpired();
                if ($deleted > 0) {
                    $logger->debug("Cleaned $deleted expired keys");
                }
            }
        });
    }
}
