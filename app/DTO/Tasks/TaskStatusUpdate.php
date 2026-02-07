<?php

declare(strict_types=1);

namespace App\DTO\Tasks;

use JsonSerializable;

class TaskStatusUpdate implements JsonSerializable
{
    public const EVENT_QUEUED = 'queued';
    public const EVENT_PROCESSING = 'processing';
    public const EVENT_CHECK_LOCK = 'check_lock';
    public const EVENT_PROCESSING_PROGRESS = 'processing_progress';
    public const EVENT_COMPLETED = 'completed';
    public const EVENT_LOCK_ACQUIRED = 'lock_acquired';
    public const EVENT_LOCK_FAILED = 'lock_failed';

    public function __construct(
        public string $id,
        public string $status,
        public string $message,
        public int $mc,
        public int $progress = 0,
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

    public static function processingProgress(string $id, int $mc, int $percent): self
    {
        return new self($id, self::EVENT_PROCESSING_PROGRESS, 'Progress', $mc, $percent);
    }

    public static function completed(string $id, int $mc): self
    {
        return new self($id, self::EVENT_COMPLETED, 'Done', $mc, 100);
    }

    public static function lockAcquired(string $id, int $mc): self
    {
        return new self($id, self::EVENT_LOCK_ACQUIRED, 'Accepted', $mc);
    }

    public static function lockFailed(string $id, int $mc): self
    {
        return new self($id, self::EVENT_LOCK_FAILED, 'Timeout', $mc);
    }

    public function withMessage(string $message): self
    {
        return new self(
            $this->id,
            $this->status,
            $message,
            $this->mc,
            $this->progress
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
        ];
    }
}
