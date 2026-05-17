<?php

declare(strict_types=1);

namespace App\Service\Provider\Messaging;

use App\Contract\Messaging\MessageSerializer;
use App\Contract\Provider\ServiceProvider;
use App\Contract\Provider\WorkerStartAware;
use App\Contract\Queue\Queue;
use App\Contract\Task\TaskQueue;
use App\Contract\Task\TaskQueueConsumer;
use App\Server\Options;
use App\Server\RuntimeScheduler;
use App\Service\Messaging\Nats\ReconnectableClient;
use App\Service\Queue\Nats\NatsQueue;
use App\Service\Queue\Nats\NatsTaskQueue;
use Basis\Nats\Client as NatsClient;
use Basis\Nats\Configuration as NatsConfiguration;

use function DI\autowire;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Server;

final readonly class MessagingServiceProvider implements ServiceProvider, WorkerStartAware
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            // Config
            NatsConfiguration::class => function (ContainerInterface $container): NatsConfiguration {
                /** @var Options $options */
                $options = $container->get(Options::class);

                return new NatsConfiguration(
                    host: $options->natsHost,
                    port: $options->natsPort,
                    token: $options->natsToken,
                    timeout: $options->natsTimeoutSec,
                    pingInterval: $options->natsPingIntervalSec,
                );
            },

            // Client (actually a Basis Nats client wrapped with ReconnectableClient)
            NatsClient::class => autowire(ReconnectableClient::class),

            // General Queue class
            Queue::class => function (ContainerInterface $c): Queue {
                /** @var NatsClient $client */
                $client = $c->get(NatsClient::class);
                /** @var Options $options */
                $options = $c->get(Options::class);
                /** @var MessageSerializer */
                $serializer = $c->get(MessageSerializer::class);

                return new NatsQueue(
                    client: $client,
                    serializer: $serializer,
                    streamName: $options->taskQueueStream,
                );
            },

            // Task Queue
            TaskQueue::class => function (ContainerInterface $c): TaskQueue {
                /** @var Queue $queue */
                $queue = $c->get(Queue::class);
                /** @var MessageSerializer $serializer */
                $serializer = $c->get(MessageSerializer::class);
                /** @var Options $options */
                $options = $c->get(Options::class);
                /** @var TaskQueueConsumer $consumer */
                $consumer = $c->get(TaskQueueConsumer::class);

                return new NatsTaskQueue(
                    queue: $queue,
                    serializer: $serializer,
                    consumer: $consumer,
                    subject: $options->taskQueueSubject,
                );
            },

            TaskQueueConsumer::class => function (ContainerInterface $c): TaskQueueConsumer {
                /** @var Queue $queue */
                $queue = $c->get(Queue::class);
                /** @var Options $options */
                $options = $c->get(Options::class);
                /** @var TaskQueueConsumer $consumer */
                $consumer = $queue->getConsumer($options->taskQueueConsumer);

                return $consumer;
            },
        ];
    }

    /**
     * Avoid NATS disconnect
     *
     * Конь должен жЫть
     */
    public function onWorkerStart(ContainerInterface $container, Server $server, int $workerId): void
    {
        /** @var Options $options */
        $options = $container->get(Options::class);
        /** @var LoggerInterface */
        $logger = $container->get(LoggerInterface::class);
        /** @var RuntimeScheduler $scheduler */
        $scheduler = $container->get(RuntimeScheduler::class);
        /** @var ReconnectableClient $client */
        $client = $container->get(NatsClient::class);

        // Ping NATS periodically to keep connection alive
        // Apply a fixed 10% random jitter during initialization to desynchronize workers
        $jitteredInterval = $options->natsWorkerPingIntervalSec * (mt_rand(90, 110) / 100);
        $scheduler->tick(
            fn () => $client->pingOrRestart($workerId),
            $jitteredInterval
        );

        // Create stream & consumer when NOT in the task worker
        // @TODO: Create a separate command to create a stream and a consumer
        if ($server->taskworker) {
            return;
        }

        try {
            $client->ensureTopology();
        } catch (\Throwable $e) {
            $logger->error(
                'NATS init failed, horse falls into medically induced coma',
                ['error' => $e->getMessage()]
            );
        }
    }
}
