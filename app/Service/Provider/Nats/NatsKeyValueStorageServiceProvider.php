<?php

declare(strict_types=1);

namespace App\Service\Provider\Nats;

use App\Contract\Storage\KeyValueStorage;
use App\Service\Provider\Contract\ServiceProvider;
use App\Service\Storage\Nats\NatsKeyValueStorage;
use Basis\Nats\Client as NatsClient;
use Basis\Nats\Stream\DiscardPolicy;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use DI\ContainerBuilder;

final readonly class NatsKeyValueStorageServiceProvider implements ServiceProvider
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            KeyValueStorage::class => function (NatsClient $nats, array $config): NatsKeyValueStorage {
                $api = $nats->getApi();

                $bucketName = $config['bucket'] ?? 'app';
                $streamName = 'KV_' . $bucketName;
                $ttlMs = $config['ttl_ms'] ?? 100;
                $size = ($config['storage_mb'] ?? 1) * 1024 * 1024;

                // Create & configure stream
                $stream = $api->getStream($streamName);
                $stream->getConfiguration()
                    ->setSubjects(['$KV.' . $bucketName . '.>'])
                    ->setStorageBackend(StorageBackend::MEMORY)
                    ->setMaxAge($ttlMs * 1_000_000)
                    ->setMaxBytes($size)
                    ->setMaxMessagesPerSubject(1)
                    ->setAllowRollupHeaders(true)
                    ->setDenyDelete(false)
                    ->setDiscardPolicy(DiscardPolicy::NEW)
                    ->setRetentionPolicy(RetentionPolicy::LIMITS)
                    ->setDuplicateWindow(0);

                // Create a stream
                $stream->createIfNotExists();

                // Create a bucket
                $bucket = $api->getBucket($bucketName);

                $bucket->getConfiguration()
                    ->setHistory(1) // rev count
                    ->setTtl((int) ($ttlMs / 1000)) // ttl >
                    ->setMaxBytes($size);

                return new NatsKeyValueStorage($bucket);
            },
        ];
    }
}
