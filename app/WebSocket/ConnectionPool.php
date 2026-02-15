<?php

declare(strict_types=1);

namespace App\WebSocket;

use Countable;
use IteratorAggregate;
use Swoole\Table;
use Traversable;

/**
 * WebSocket connection pool
 *
 * @implements \IteratorAggregate<int, mixed>
 */
class ConnectionPool implements IteratorAggregate, Countable
{
    private const string COL_FD = 'fd';
    private const string COL_CONNECTED_AT = 'connected_at';
    private const string COL_LAST_PING = 'last_ping';

    public static function configureAndCreateTable(int $size): Table
    {
        $table = new Table($size);

        $table->column(self::COL_FD, Table::TYPE_INT, 4);
        $table->column(self::COL_CONNECTED_AT, Table::TYPE_INT, 8);
        $table->column(self::COL_LAST_PING, Table::TYPE_INT, 8);
        $table->create();

        return $table;
    }

    public function __construct(private readonly Table $connections)
    {
    }

    public function has(int $fd): bool
    {
        return $this->connections->exist((string) $fd);
    }

    public function count(): int
    {
        return $this->connections->count();
    }

    public function updatePing(int $fd): bool
    {
        $fdKey = (string) $fd;

        if ($this->connections->exist($fdKey)) {
            $conn = $this->connections->get($fdKey);

            if (is_array($conn)) {
                $conn[self::COL_LAST_PING] = time();
                $this->connections->set($fdKey, $conn);
            }
            return true;
        }
        return false;
    }

    public function add(int $fd): bool
    {
        $this->connections->set((string)$fd, [self::COL_FD => $fd]);

        $this->connections->set((string) $fd, [
            self::COL_FD => $fd,
            self::COL_CONNECTED_AT => time(),
            self::COL_LAST_PING => time(),
        ]);

        return true;
    }

    public function remove(int $fd): bool
    {
        return $this->connections->del((string) $fd);
    }

    /**
     * @return \Traversable<int, mixed>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->connections as $fdKey => $conn) {
            // Narrowing types to keep PHP Satan in his cage.
            if (!is_array($conn) || !is_scalar($fdKey)) {
                continue;
            }

            /** @var array<string, scalar> $conn */
            yield (int) $fdKey => [
                self::COL_FD => (int) $conn[self::COL_FD],
                self::COL_CONNECTED_AT => (int) $conn[self::COL_CONNECTED_AT],
                self::COL_LAST_PING => (int) $conn[self::COL_LAST_PING],
            ];
        }
    }
}
