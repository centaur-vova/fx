<?php

declare(strict_types=1);

namespace App\Service\Storage\Swoole;

use App\Contract\Storage\TtlKeyValueStorage;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine as Co;
use Swoole\Table;

class SwooleTableKeyValueStorage implements TtlKeyValueStorage
{
    private readonly Table $table;

    public function __construct(private readonly LoggerInterface $logger, int $size = 1024, private readonly int $ttl = 3600)
    {
        $this->table = new Table($size);
        $this->table->column('value', Table::TYPE_STRING, 1024);
        $this->table->column('expires', Table::TYPE_INT, 8);
        $this->table->create();
    }

    public function get(string $key): ?string
    {
        $row = $this->table->get($key);
        if ($row === null || !is_array($row)) {
            return null;
        }

        // TTL check
        if ($row['expires'] < time()) {
            $this->table->del($key);
            return null;
        }

        return $row['value'];
    }

    public function set(string $key, string $value, ?int $ttl = null): bool
    {
        $expires = time() + ($ttl ?? $this->ttl);
        return $this->table->set($key, [
            'value' => $value,
            'expires' => $expires,
        ]);
    }

    public function delete(string $key): bool
    {
        return $this->table->del($key);
    }

    public function has(string $key): bool
    {
        $row = $this->table->get($key);
        if ($row === null || !is_array($row)) {
            return false;
        }

        if ($row['expires'] < time()) {
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

    public function startCleaner(int $interval = 60): void
    {
        $logger = $this->logger;

        go(function () use ($interval, $logger): void {
            /** @phpstan-ignore-next-line */
            while (true) {
                Co::sleep($interval);
                $deleted = $this->cleanExpired();
                if ($deleted > 0) {
                    $logger->info("Cleaned $deleted expired keys");
                }
            }
        });
    }
}
