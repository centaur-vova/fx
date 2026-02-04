<?php

declare(strict_types=1);

namespace App\Services\Tasks\Semaphores;

use App\Contracts\Tasks\SemaphorePermit;
use App\Contracts\Tasks\TaskSemaphore;
use Swoole\Coroutine as Co;

class SwooleChannelSemaphore implements TaskSemaphore
{
    /** @var Channel[] */
    private array $channels = [];

    public function __construct()
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->channels[$i] = new Co\Channel($i);
        }
    }

    public function forLimit(int $mc): SemaphorePermit
    {
        $limit = ($mc >= 1 && $mc <= 10) ? $mc : 1;
        $channel = $this->channels[$limit];

        return new class ($channel, $limit) implements SemaphorePermit {
            public function __construct(
                private Co\Channel $channel,
            ) {
            }

            public function acquire(float $timeout): bool
            {
                return $this->channel->push(true, $timeout);
            }

            public function release(): void
            {
                if ($this->channel->stats()['queue_num'] > 0) {
                    $this->channel->pop();
                }
            }
        };
    }
}
