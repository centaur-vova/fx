<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Contract\Messaging\Broadcaster;
use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\SemaphoreFactory;
use App\Contract\Task\TaskMode;
use App\Contract\Task\TaskQueue;
use App\DTO\Task\TaskExecutionPayload;
use App\DTO\WebSocket\Message\TaskBatchCreated;
use App\DTO\WebSocket\Message\TaskStatusUpdate;
use App\Exception\Server\WorkerShutdownException;
use App\Server\RuntimeContext;
use App\Service\Task\Processor\ProcessorFactory;
use App\Service\Telemetry\TraceContext;
use App\Support\Concern\Snafubarable;
use JsonSerializable;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine as Co;
use Swoole\Timer;
use Throwable;

class TaskService
{
    use Snafubarable;

    public function __construct(
        private readonly RuntimeContext $context,
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

    public function createBatch(int $count, int $maxConcurrent, SemaphoreDriver $semaphoreDriver, TaskMode $mode): void
    {
        TraceContext::run(
            'task.batch.create',
            TraceContext::inject(),
            SpanKind::KIND_PRODUCER,
            [
                'batch.count' => $count,
                'batch.max_concurrent' => $maxConcurrent,
                'batch.semaphore_driver' => $semaphoreDriver->value,
                'batch.task_mode' => $mode->value,
            ],
            function (SpanInterface $span) use ($count, $maxConcurrent, $semaphoreDriver, $mode): void {
                $createdCount = 0;

                for ($i = 0; $i < $count; $i++) {
                    $result = $this->taskQueue->push(
                        TaskExecutionPayload::create(
                            mc: $maxConcurrent,
                            mode: $mode,
                            sem: $semaphoreDriver,
                            traceparent: TraceContext::inject(),
                        )
                    );

                    if ($result) {
                        $createdCount++;
                    }
                }

                if ($createdCount > 0) {
                    $this->notify(new TaskBatchCreated($createdCount, $maxConcurrent, $mode, $semaphoreDriver));
                }

                $span->setAttribute('batch.created_count', $createdCount);
            }
        );
    }

    public function createRandomBatches(): void
    {
        TraceContext::run(
            'task.batch.random',
            TraceContext::inject(),
            SpanKind::KIND_INTERNAL,
            [],
            function (SpanInterface $span): void {
                $batches = random_int(500, 1000);
                $span->setAttribute('random.total_batches', $batches);

                for ($i = 0; $i < $batches; $i++) {
                    $count = random_int(1, 5);
                    $mc = random_int(10, 30);

                    $cases = SemaphoreDriver::cases();
                    $sem = $cases[array_rand($cases)];

                    $cases = TaskMode::cases();
                    $mode = $cases[array_rand($cases)];

                    $this->createBatch($count, $mc, $sem, $mode);
                }
            }
        );
    }

    public function processTask(TaskExecutionPayload $payload, int $workerId, SpanInterface $taskSpan): void
    {
        // Quick check if shutting down
        if ($this->context->isShuttingDown()) {
            $taskSpan->setAttribute('task.shutting_down', true);
            $this->manager->nack($payload); // Return to queue
            return;
        }

        $taskSpan->setAttribute('worker.id', $workerId);
        $this->logger->debug('Entered processTask', ['id' => $payload->id]);

        $semaphore = $this->semaphoreFactory->get($payload->sem);
        try {
            // Semaphore span
            $semSpan = TraceContext::startSpan(
                'task.semaphore.acquire',
                SpanKind::KIND_INTERNAL,
                [
                    'semaphore.driver' => $payload->sem->value,
                    'semaphore.mc' => $payload->mc,
                ]
            );

            $permit = $semaphore->forLimit($payload->mc);
            $this->notify(TaskStatusUpdate::checkLock($payload));

            /**
             * Attempt to acquire lock.
             */
            if (!$permit->acquire((float) $this->lockTimeoutSec)) {
                $semSpan
                    ->addEvent('semaphore.timeout')
                    ->setAttribute('semaphore.acquired', false)
                    ->setAttribute('semaphore.attempt', $payload->attempt);

                if ($payload->attempt >= $this->maxRetries) {
                    $taskSpan->setAttribute('task.retries_exhausted', true);
                    $semSpan
                        ->addEvent('task.retries_exhausted')
                        ->end();

                    $this->manager->nack($payload);
                    $this->logger->debug('Max retries reached', ['id' => $payload->id]);
                    $this->notify(TaskStatusUpdate::retriesFailed($payload, $workerId, $this->maxRetries));
                    return;
                }

                $this->notify(TaskStatusUpdate::lockFailed($payload));

                $semSpan
                    ->addEvent('retry.scheduled')
                    ->end();

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

                    $this->notify(TaskStatusUpdate::retry($payload));
                });

                return;
            }

            $semSpan
                ->addEvent('semaphore.acquired')
                ->setAttribute('semaphore.acquired', true)
                ->end();

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
                $this->notify(TaskStatusUpdate::lockAcquired($payload));

                // Task execution span
                $execSpan = TraceContext::startSpan(
                    'task.execution',
                    SpanKind::KIND_INTERNAL,
                    [
                        'task.mode' => $payload->mode->value,
                    ]
                );

                $processor = $this->processorFactory->get($payload->mode);

                $progressCallback = function (int $progress) use ($payload, $execSpan): void {
                    $execSpan->setAttribute('task.progress', $progress);
                    $this->notify(TaskStatusUpdate::progress($payload, $progress));
                    Co::sleep(0.001);
                };

                $this->logger->debug('Task started', ['id' => $payload->id, 'time' => microtime(true)]);
                $processor->execute($progressCallback);
                $this->logger->debug('Task finished', ['id' => $payload->id, 'time' => microtime(true)]);

                $execSpan->setAttribute('task.completed', true);
                $execSpan->end();

                $this->notify(TaskStatusUpdate::completed($payload, $workerId));
            } finally {
                $permit->release();
                $this->logger->debug('Lock released', ['id' => $payload->id]);
                $semSpan->addEvent('semaphore.released');
                $taskSpan->setAttribute('semaphore.released', true);
            }

            $this->manager->ack($payload);

        } catch (WorkerShutdownException) {
            // Worker shutting down, early return
            $taskSpan->setAttribute('task.shutting_down', true);
            return;
        } catch (Throwable $e) {
            $this->logger->error('Fatal task error', ['id' => $payload->id, 'error' => $e->getMessage()]);
            $taskSpan
                ->setAttribute('task.error', $e->getMessage())
                ->addEvent('error', ['message' => $e->getMessage()]);
            $this->manager->nack($payload);
        }
    }

    public function shutdown(): void
    {
        $this->semaphoreFactory->shutdown();
    }

    private function notify(JsonSerializable $update): void
    {
        $this->snafubar(
            action: fn () => $this->broadcaster->publish($this->broadcastSubject, $update),
            reason: 'Notify failed',
        );
    }
}
