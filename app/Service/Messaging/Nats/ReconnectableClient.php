<?php

declare(strict_types=1);

namespace App\Service\Messaging\Nats;

use App\Exception\Server\WorkerShutdownException;
use App\Server\Options;
use App\Server\RuntimeContext;
use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Connection;
use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\DeliverPolicy;
use Basis\Nats\Stream\DiscardPolicy;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use Psr\Log\LoggerInterface;
use Swoole\Process;
use Throwable;

class ReconnectableClient extends Client
{
    public function __construct(
        Configuration $configuration,
        private readonly LoggerInterface $internalLogger,
        private readonly Options $options,
        private readonly RuntimeContext $context,
        ?Connection $connection = null,
    ) {
        // Use null logger
        parent::__construct($configuration, null, $connection);
    }

    /**
     * Attempts to restore the connection and recreate the topology.
     *
     * @return bool True if successfully reconnected and topology is ensured.
     * @throws WorkerShutdownException If shutdown signal is received during pause.
     */
    public function reconnect(): bool
    {
        $this->connection?->close();
        $this->connection = null;

        // No need for long pauses, but we use our "horse-power" sleep
        // It will throw WorkerShutdownException if we are stopping right now
        $this->context->sleepOrDie(0.1);

        try {
            // Create new connection
            $this->connection = new Connection(client: $this);

            // New connection will be established on the next command (ping)
            $this->ping();

            // Re-create streams and consumers if NATS lost its memory
            $this->ensureTopology();

            return true;
        } catch (WorkerShutdownException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->internalLogger->error('NATS reconnection failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function pingOrRestart(int $workerId): void
    {
        try {
            if (($this->connection === null) || ($this->ping() === false)) {
                throw new \RuntimeException('NATS ping returned false');
            }

        } catch (\Throwable) {
            $this->internalLogger->info('Reconnecting to NATS', ['workerId' => $workerId]);

            if (!$this->reconnect()) {
                $this->internalLogger->critical("Unrecoverable NATS error. Killing worker #$workerId");

                // A small delay before SNAFUBAR
                $this->context->sleepOrDie(5.0);

                $pid = getmypid();
                if ($pid === false) {
                    exit(1);
                }
                Process::kill($pid, SIGKILL);
            }
        }
    }

    /**
     * Ensures that all required streams and consumers exist in NATS.
     * This makes the system self-healing after NATS restarts.
     */
    public function ensureTopology(): void
    {
        $this->internalLogger->info('Ensuring topology');

        try {
            $this->createStream();
            $this->createConsumer();
        } catch (Throwable $e) {
            $this->internalLogger->error('NATS topology initialization failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function createStream(): void
    {
        $api = $this->getApi();
        $stream = $api->getStream($this->options->taskQueueStream);

        $stream->getConfiguration()
            ->setSubjects([$this->options->taskQueueSubject])
            ->setStorageBackend(StorageBackend::MEMORY)
            ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
            ->setDiscardPolicy(DiscardPolicy::NEW)
            ->setMaxMessagesPerSubject($this->options->queueCapacity);

        $stream->createIfNotExists();
    }

    private function createConsumer(): void
    {
        $api = $this->getApi();
        $stream = $api->getStream($this->options->taskQueueStream);

        $consumer = $stream->getConsumer($this->options->taskQueueConsumer);
        $consumer->getConfiguration()
            ->setAckPolicy(AckPolicy::EXPLICIT)
            ->setDeliverPolicy(DeliverPolicy::ALL)
            ->setAckWait($this->options->natsAckWaitMs * 1_0_0000_0); // HORSE_MILLION

        // true = create or update
        $consumer->create(true);
    }
}
