<?php

declare(strict_types=1);

namespace App\DTO\WebSocket\Message;

use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\TaskMode;
use App\DTO\Task\TaskExecutionPayload;
use JsonSerializable;

/**
 * Task status sent via NATS to WebSocket clients.
 * 🦒 Hali ya kazi inayotumwa kupitia NATS hadi kwa wateja wa WebSocket.
 *
 * Each event carries a traceparent for OpenTelemetry context propagation
 * to the Go proxy.
 * 🦒 Kila tukio linabeba kitambulisho cha kufuatilia (traceparent) kwa ajili ya
 *   usambazaji wa muktadha wa OpenTelemetry hadi kwa wakala wa Go.
 *
 * traceparent is NOT consumed by Go WS Proxy yet.
 * 🦒 traceparent haitumiki na Go WS Proxy bado.
 *
 * UGNI (U Gonna Need It): keep it here for future distributed tracing.
 * 🦒 UGNI (Utahitaji Baadaye): weka hapa kwa ajili ya usambazaji wa siku zijazo.
 *
 * PS: Пипец я осёл, куда я ввязался. Ладно, выучу суахили по-любому.
 */
final readonly class TaskStatusUpdate implements JsonSerializable
{
    public const string EVENT_PROCESSING = 'processing';
    public const string EVENT_CHECK_LOCK = 'check_lock';
    public const string EVENT_PROGRESS = 'progress';
    public const string EVENT_COMPLETED = 'completed';
    public const string EVENT_LOCK_ACQUIRED = 'lock_acquired';
    public const string EVENT_LOCK_FAILED = 'lock_failed';
    public const string EVENT_RETRIES_FAILED = 'retries_failed';
    public const string EVENT_RETRY = 'retry';

    public function __construct(
        public int $id,
        public string $status,
        public int $mc,
        public SemaphoreDriver $sem,
        public TaskMode $mode,
        public ?string $message,
        public ?int $progress = null,
        public ?int $worker = null,
        public ?string $traceparent = null,
    ) {
    }

    public static function fromPayload(
        TaskExecutionPayload $payload,
        string $status,
        ?string $message = null,
        ?int $worker = null,
        ?int $progress = null,
    ): self {
        return new self(
            id: $payload->id,
            mc: $payload->mc,
            sem: $payload->sem,
            mode: $payload->mode,
            traceparent: $payload->traceparent,
            worker: $worker,
            status: $status,
            message: $message,
            progress: $progress,
        );
    }

    public static function retry(TaskExecutionPayload $payload): self
    {
        return self::fromPayload($payload, self::EVENT_RETRY, 'Retrying');
    }

    public static function processing(TaskExecutionPayload $payload): self
    {
        return self::fromPayload($payload, self::EVENT_PROCESSING, 'Processing');
    }

    public static function checkLock(TaskExecutionPayload $payload): self
    {
        return self::fromPayload($payload, self::EVENT_CHECK_LOCK, "Limit: {$payload->mc}");
    }

    public static function progress(TaskExecutionPayload $payload, int $percent): self
    {
        return self::fromPayload($payload, self::EVENT_PROGRESS, "{$percent}%", null, $percent);
    }

    public static function completed(TaskExecutionPayload $payload, int $worker): self
    {
        return self::fromPayload($payload, self::EVENT_COMPLETED, 'Done', $worker, 100);
    }

    public static function lockAcquired(TaskExecutionPayload $payload): self
    {
        return self::fromPayload($payload, self::EVENT_LOCK_ACQUIRED, 'Accepted');
    }

    public static function lockFailed(TaskExecutionPayload $payload): self
    {
        return self::fromPayload($payload, self::EVENT_LOCK_FAILED, 'Timeout');
    }

    public static function retriesFailed(TaskExecutionPayload $payload, int $worker, int $maxRetries): self
    {
        return self::fromPayload($payload, self::EVENT_RETRIES_FAILED, "Max retries reached ({$maxRetries})", $worker);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'message' => $this->message,
            'mc' => $this->mc,
            'progress' => $this->progress,
            'worker' => $this->worker,
            'sem' => $this->sem->value,
            'mode' => $this->mode->value,
            'traceparent' => $this->traceparent,
        ];
    }
}
