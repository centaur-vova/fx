<?php

declare(strict_types=1);

namespace App\Service\Storage\Nats;

use App\Contract\Storage\KeyValueStorage;
use Basis\Nats\KeyValue\Bucket;

class NatsKeyValueStorage implements KeyValueStorage
{
    public function __construct(
        private readonly Bucket $bucket,
    ) {
    }

    public function get(string $key): ?string
    {
        return $this->bucket->get($key);
    }

    public function set(string $key, string $value, ?int $ttl = null): bool
    {
        // TTL ignored
        $this->bucket->put($key, $value);
        return true;
    }

    public function delete(string $key): bool
    {
        $this->bucket->delete($key);
        return true;
    }

    public function has(string $key): bool
    {
        return $this->bucket->get($key) !== null;
    }

    public function count(): int
    {
        return 0; // TODO - not used
    }
}
