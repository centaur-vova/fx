<?php

declare(strict_types=1);

namespace App\DTO\WebSockets\Messages;

use JsonSerializable;

final readonly class TaskStatusUpdate implements JsonSerializable
{
    public const string EVENT_QUEUED = 'queued';
    public const string EVENT_PROCESSING = 'processing';
    public const string EVENT_CHECK_LOCK = 'check_lock';
    public const string EVENT_PROGRESS = 'progress';
    public const string EVENT_COMPLETED = 'completed';
    public const string EVENT_LOCK_ACQUIRED = 'lock_acquired';
    public const string EVENT_LOCK_FAILED = 'lock_failed';
    public const string EVENT_RETRIES_FAILED = 'retries_failed';

    public function __construct(
        public string $id,
        public string $status,
        public string $message,
        public int $mc,
        public int $progress = 0,
        public ?int $worker = null,
    ) {
    }

    public static function queued(string $id, int $mc): self
    {
        return new self($id, self::EVENT_QUEUED, 'In queue', $mc);
    }

    public static function processing(string $id, int $mc): self
    {
        return new self($id, self::EVENT_PROCESSING, 'Started', $mc);
    }

    public static function checkLock(string $id, int $mc): self
    {
        return new self($id, self::EVENT_CHECK_LOCK, "Limit: {$mc}", $mc);
    }

    public static function progress(string $id, int $mc, int $percent): self
    {
        return new self($id, self::EVENT_PROGRESS, 'Progress', $mc, $percent);
    }

    public static function completed(string $id, int $mc, int $worker): self
    {
        return new self($id, self::EVENT_COMPLETED, 'Done', $mc, 100, $worker);
    }

    public static function lockAcquired(string $id, int $mc): self
    {
        return new self($id, self::EVENT_LOCK_ACQUIRED, 'Accepted', $mc);
    }

    public static function lockFailed(string $id, int $mc): self
    {
        return new self($id, self::EVENT_LOCK_FAILED, 'Timeout', $mc);
    }

    public static function retriesFailed(string $id, int $mc, int $worker, int $maxRetries): self
    {
        return new self($id, self::EVENT_RETRIES_FAILED, "Max retries reached ({$maxRetries})", $mc, 0, $worker);
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
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'mc' => $this->mc,
            'taskId' => $this->id,
            'status' => $this->status,
            'message' => $this->message,
            'progress' => $this->progress,
            'worker' => $this->worker,
        ];
    }
}
