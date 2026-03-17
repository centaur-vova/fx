<?php

declare(strict_types=1);

namespace App\Service\Provider\Nats;

use App\Contract\Messaging\MessageSerializer;
use App\Contract\Queue\Queue;
use App\Contract\Task\TaskQueue;
use App\Server\Options;
use App\Service\Provider\Contract\ServiceProvider;
use App\Service\Provider\Contract\WorkerStartAware;
use Basis\Nats\Client as NatsClient;
use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\DeliverPolicy;
use Basis\Nats\Stream\DiscardPolicy;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Swoole\Server;

class NatsTaskQueueServiceProvider implements ServiceProvider, WorkerStartAware
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            TaskQueue::class => fn ($c) => new NatsTaskQueue(
                queue: $c->get(Queue::class),
                serializer: $c->get(MessageSerializer::class),
                consumerName: $c->get(Options::class)->taskQueueConsumer,
                subject: $c->get(Options::class)->taskQueueSubject,
            ),
        ];
    }

    public function onWorkerStart(ContainerInterface $container, Server $server, int $workerId): void
    {
        if ($server->taskworker) {
            return;
        }

        /** @var NatsClient $client */
        $client = $container->get(NatsClient::class);
        /** @var Options $options */
        $options = $container->get(Options::class);

        $this->createStream($client, $options);
        $this->createConsumer($client, $options);
    }

    private function createStream(NatsClient $client, Options $options): void
    {
        $api = $client->getApi();
        $stream = $api->getStream($options->taskQueueStream);

        $stream->getConfiguration()
            ->setSubjects([$options->taskQueueSubject])
            ->setStorageBackend(StorageBackend::MEMORY)
            ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
            ->setDiscardPolicy(DiscardPolicy::NEW) // discard new messages if queue is full
            ->setMaxMessagesPerSubject($options->queueCapacity);

        $stream->createIfNotExists();
    }

    private function createConsumer(NatsClient $client, Options $options): void
    {
        $api = $client->getApi();
        $stream = $api->getStream($options->taskQueueStream);

        $consumer = $stream->getConsumer($options->taskQueueConsumer);
        $consumer->getConfiguration()
            ->setAckPolicy(AckPolicy::EXPLICIT)
            ->setDeliverPolicy(DeliverPolicy::ALL)
            ->setAckWait($options->natsAckWaitMs * 1_000_000); // to nanosec

        $consumer->create(true);
    }
}
