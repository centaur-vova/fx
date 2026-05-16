<?php

declare(strict_types=1);

namespace App\Support\Concern;

use App\Exception\Server\WorkerShutdownException;
use App\Server\RuntimeContext;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Process;
use Throwable;

/**
 * Trait Snafubarable
 *
 * Provides a mechanism to handle fatal infrastructure failures (SNAFU)
 * by restarting the worker horse after a safety delay.
 *
 * @property-read ?LoggerInterface $logger
 * @property-read ?RuntimeContext $context
 */
trait Snafubarable
{
    /**
     * Executes a callback and kills the worker horse if it fails
     * Use this for critical infrastructure operations like NATS messaging
     *
     * @param callable $action The logic to execute
     * @param string $reason Contextual message for the critical log
     * @param float $delay Delay in seconds before killing the worker
     * @throws WorkerShutdownException Lame horse is shot to be resurrected
     */
    protected function snafubar(
        callable $action,
        string $reason = 'Action failed',
        float $delay = 5.0,
    ): void {
        try {
            $action();

        } catch (WorkerShutdownException $e) {
            // Graceful exit if the server is already shutting down
            throw $e;
        } catch (Throwable $e) {
            // Log the catastrophe using the host's logger
            if (isset($this->logger)) {
                $this->logger->critical("SNAFUBAR: $reason. Restarting worker horse", [
                    'error' => $e->getMessage(),
                ]);
            }

            // Perform a safety delay to prevent restart loops
            $jitter = mt_rand(80, 120) / 100; // ±20% (0.8 - 1.2)
            $actualDelay = $delay * $jitter;
            if (isset($this->context)) {
                $this->context->sleepOrDie($actualDelay);
            } else {
                Coroutine::sleep($actualDelay);
            }

            // Signal the Master process to bring a fresh horse
            $this->context->stop();
        }
    }
}
