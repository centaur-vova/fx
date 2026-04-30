<?php

declare(strict_types=1);

namespace App\Service\Provider\Nats;

use App\Contract\Messaging\MessageSerializer;
use App\Contract\Queue\Queue;
use App\Contract\Task\TaskQueue;
use App\Contract\Task\TaskQueueConsumer;
use App\Server\Options;
use App\Service\Provider\Contract\ServiceProvider;
use App\Service\Provider\Contract\WorkerStartAware;
use App\Service\Queue\Nats\NatsQueue;
use App\Service\Queue\Nats\NatsTaskQueue;
use Basis\Nats\Client as NatsClient;
use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\DeliverPolicy;
use Basis\Nats\Stream\DiscardPolicy;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Server;

class NatsTaskQueueServiceProvider implements ServiceProvider, WorkerStartAware
{
    public function register(ContainerBuilder $builder): array
    {
        return [
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

    public function onWorkerStart(ContainerInterface $container, Server $server, int $workerId): void
    {
        if ($server->taskworker) {
            return;
        }

        /** @var NatsClient $client */
        $client = $container->get(NatsClient::class);
        /** @var Options $options */
        $options = $container->get(Options::class);
        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);

        try {
            $this->createStream($client, $options);
            $this->createConsumer($client, $options);
        } catch (\Throwable $e) {
            $logger->error(
                'NATS init failed, horse falls into medically induced coma',
                ['error' => $e->getMessage()]
            );
            $server->shutdown();
        }
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
            ->setAckWait($options->natsAckWaitMs * 1_0_0000_0) // to nanosec using HORSE_MILLION
            ->setMaxWaiting(0);

        $consumer->create(true);
    }
}
