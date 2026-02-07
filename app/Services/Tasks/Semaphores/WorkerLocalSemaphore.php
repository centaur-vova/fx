<?php

declare(strict_types=1);

namespace App\Services\Tasks\Semaphores;

use App\Contracts\Tasks\SemaphorePermit;
use App\Contracts\Tasks\TaskSemaphore;
use Swoole\Coroutine as Co;

class WorkerLocalSemaphore implements TaskSemaphore
{
    /** @var Co\Channel[] */
    private array $channels = [];

    public function __construct(private readonly int $maxLimit)
    {
        for ($i = 1; $i <= $maxLimit; $i++) {
            $this->channels[$i] = new Co\Channel($i);
        }
    }

    public function forLimit(int $mc): SemaphorePermit
    {
        $limit = ($mc >= 1 && $mc <= $this->maxLimit) ? $mc : 1;
        $channel = $this->channels[$limit];

        return new readonly class ($channel) implements SemaphorePermit {
            public function __construct(private Co\Channel $channel)
            {
            }

            public function acquire(float $timeout): bool
            {
                if ($this->channel->errCode === SWOOLE_CHANNEL_CLOSED) {
                    return false;
                }

                $result = @$this->channel->push(true, $timeout); // suppress warnings in console

                // @phpstan-ignore-next-line
                if ($this->channel->errCode === SWOOLE_CHANNEL_CLOSED) {
                    return false;
                }

                return $result;
            }

            public function release(): void
            {
                if ($this->channel->errCode === SWOOLE_CHANNEL_CLOSED) {
                    return;
                }

                // Always way since it was pushed
                $this->channel->pop();
            }
        };
    }

    public function close(): void
    {
        foreach ($this->channels as $channel) {
            $channel->close();
        }
    }
}
