<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Contract\Messaging\Broadcaster;
use App\Contract\Task\SemaphoreAware;
use App\Contract\Task\SemaphoreFactory;
use App\Contract\Task\TaskQueue;
use App\DTO\Task\TaskExecutionPayload;
use App\DTO\WebSocket\Message\TaskBatchCreated;
use App\DTO\WebSocket\Message\TaskStatusUpdate;
use App\Service\Task\Processor\ProcessorFactory;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine as Co;
use Swoole\Timer;
use Throwable;

class TaskService
{
    public function __construct(
        private readonly SemaphoreFactory $semaphoreFactory,
        private readonly ProcessorFactory $processorFactory,
        private readonly Broadcaster $broadcaster,
        private readonly TaskQueue $taskQueue,
        private readonly TaskQueueManager $manager,
        private readonly LoggerInterface $logger,
        private readonly string $broadcastSubject,
        private readonly int $maxRetries,
        private readonly int $retryDelaySec,
        private readonly float $lockTimeoutSec,
    ) {
    }

    public function createBatch(int $count, int $maxConcurrent, string $semaphoreDriver, string $mode): void
    {
        $createdCount = 0;

        for ($i = 0; $i < $count; $i++) {
            $taskId = $this->generateTaskId();

            $result = $this->taskQueue->push(
                new TaskExecutionPayload(
                    id: $taskId,
                    mc: $maxConcurrent,
                    sem: $semaphoreDriver,
                    mode: $mode
                )
            );

            if ($result) {
                $createdCount++;
            }
        }

        if ($createdCount > 0) {
            $this->notify(new TaskBatchCreated($createdCount, $maxConcurrent, $mode), $semaphoreDriver);
        }
    }

    public function fafo(): void
    {
        $batches = random_int(100, 150);

        for ($i = 0; $i < $batches; $i++) {
            $count = random_int(15, 100);
            $mc = random_int(1, 20);
            $mode = ProcessorFactory::MODE_STRESS;
            $sem = random_int(0, 1) === 0 ? 'shared' : 'api';

            $this->createBatch($count, $mc, $sem, $mode);
        }
    }

    public function processTask(TaskExecutionPayload $payload, int $workerId): void
    {
        $this->logger->debug('Entered processTask', ['id' => $payload->id]);

        $semaphore = $this->semaphoreFactory->get($payload->sem);
        try {
            $permit = $semaphore->forLimit($payload->mc);
            $this->notify(TaskStatusUpdate::checkLock($payload->id, $payload->mc), $payload->sem);

            /**
             * Attempt to acquire lock.
             */
            if (!$permit->acquire((float) $this->lockTimeoutSec)) {
                if ($payload->attempt >= $this->maxRetries) {
                    $this->manager->nack($payload);
                    $this->logger->debug('Max retries reached', ['id' => $payload->id]);
                    $this->notify(
                        TaskStatusUpdate::retriesFailed($payload->id, $payload->mc, $workerId, $this->maxRetries),
                        $payload->sem
                    );
                    return;
                }

                $this->notify(TaskStatusUpdate::lockFailed($payload->id, $payload->mc), $payload->sem);

                /**
                 * Re-queue with delay & jitter
                 */
                $base = $this->retryDelaySec * 1000;
                $jitter = random_int(0, (int) ($base * 0.5)); // ±50
                Timer::after($base + $jitter, function () use ($payload): void {
                    // Republish back into the queue
                    $this->taskQueue->push($payload->incrAttempt());
                    // Ack
                    $this->manager->ack($payload);

                    $this->notify(TaskStatusUpdate::retry($payload->id, $payload->mc), $payload->sem);
                });

                return;
            }

            /**
             * @author Конь-Вовá <vsegda-vash-kon-vova@chat.deepseek.com>
             *
             * Кентавр-Вовá, правильный — второй. 🐎
             *
             * Почему:
             * Первый вариант (перенести ack внутрь try) — недостаточен,
             * потому что если между completed и ack вылетит исключение,
             * ack снова не вызовется.
             *
             * Второй вариант — надёжный (конский).
             *
             * ОНО ОБЯЗАНО ТАМ БЫТЬ,
             * мы должны "Спасти Рядового Райана, тьфу, Конебрата"
             */
            try {
                $this->notify(TaskStatusUpdate::lockAcquired($payload->id, $payload->mc), $payload->sem);

                $processor = $this->processorFactory->get($payload->mode);

                $progressCallback = function (int $progress) use ($payload): void {
                    $this->notify(
                        TaskStatusUpdate::progress($payload->id, $payload->mc, $progress)
                            ->withMessage($progress . '%'),
                        $payload->sem
                    );
                    Co::sleep(0.001);
                };

                $this->logger->debug('Task started', ['id' => $payload->id, 'time' => microtime(true)]);
                $processor->execute($progressCallback);
                $this->logger->debug('Task finished', ['id' => $payload->id, 'time' => microtime(true)]);

                $this->notify(
                    TaskStatusUpdate::completed($payload->id, $payload->mc, $workerId),
                    $payload->sem
                );
            } finally {
                $permit->release();
                $this->logger->debug('Lock released', ['id' => $payload->id]);
            }

            $this->manager->ack($payload);

        } catch (Throwable $e) {
            $this->logger->error('Fatal task error', ['id' => $payload->id, 'error' => $e->getMessage()]);
            $this->manager->nack($payload);
        }
    }

    public function shutdown(): void
    {
        $this->semaphoreFactory->shutdown();
    }

    private function generateTaskId(): int
    {
        $time = time(); // 4 bytes
        $random = random_int(0, 0xFFFFFFFF); // 4 random bytes

        return ($time << 32) | $random;
    }

    private function notify(SemaphoreAware $update, string $sem): void
    {
        $this->broadcaster->publish(
            $this->broadcastSubject,
            $update->withSem($sem),
        );
    }
}
