<?php

declare(strict_types=1);

namespace App\DTO\WebSocket\Message;

use App\Contract\Task\SemaphoreDriver;
use JsonSerializable;

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
        public string $message,
        public int $mc,
        public SemaphoreDriver $sem,
        public int $progress = 0,
        public ?int $worker = null,
    ) {
    }

    public static function retry(int $id, int $mc, SemaphoreDriver $sem): self
    {
        return new self($id, self::EVENT_RETRY, 'Retry', $mc, $sem);
    }

    public static function processing(int $id, int $mc, SemaphoreDriver $sem): self
    {
        return new self($id, self::EVENT_PROCESSING, 'Started', $mc, $sem);
    }

    public static function checkLock(int $id, int $mc, SemaphoreDriver $sem): self
    {
        return new self($id, self::EVENT_CHECK_LOCK, "Limit: {$mc}", $mc, $sem);
    }

    public static function progress(int $id, int $mc, int $percent, SemaphoreDriver $sem): self
    {
        return new self($id, self::EVENT_PROGRESS, 'Progress', $mc, $sem, $percent);
    }

    public static function completed(int $id, int $mc, int $worker, SemaphoreDriver $sem): self
    {
        return new self($id, self::EVENT_COMPLETED, 'Done', $mc, $sem, 100, $worker);
    }

    public static function lockAcquired(int $id, int $mc, SemaphoreDriver $sem): self
    {
        return new self($id, self::EVENT_LOCK_ACQUIRED, 'Accepted', $mc, $sem);
    }

    public static function lockFailed(int $id, int $mc, SemaphoreDriver $sem): self
    {
        return new self($id, self::EVENT_LOCK_FAILED, 'Timeout', $mc, $sem);
    }

    public static function retriesFailed(int $id, int $mc, int $worker, int $maxRetries, SemaphoreDriver $sem): self
    {
        return new self($id, self::EVENT_RETRIES_FAILED, "Max retries reached ({$maxRetries})", $mc, $sem, worker: $worker);
    }

    public function withMessage(string $message): self
    {
        return new self(
            message: $message,
            id: $this->id,
            status: $this->status,
            mc: $this->mc,
            progress: $this->progress,
            worker: $this->worker,
            sem: $this->sem,
        );
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
        ];
    }
}
