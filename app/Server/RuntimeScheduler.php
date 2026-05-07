<?php

declare(strict_types=1);

namespace App\Server;

use App\Exception\Server\WorkerShutdownException;
use Psr\Log\LoggerInterface;

final class RuntimeScheduler
{
    public function __construct(
        private readonly RuntimeContext $context,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Executes a callback at a fixed interval until the runtime shuts down.
     */
    public function tick(callable $callback, float $intervalSec): void
    {
        go(function () use ($callback, $intervalSec): void {
            $stopChan = $this->context->getShutdownSignal();

            while (true) {
                // Wait until the end of the interval, or until the channel is closed
                $stopChan->pop($intervalSec);

                if ($this->context->isShuttingDown()) {
                    $this->logger->info("Shutting down, exiting ticker [{$intervalSec} sec]");
                    return;
                }

                try {
                    // Run the actual periodic task
                    $callback();
                } catch (WorkerShutdownException) {
                    // Caught the "stop-brake" signal from inside the callback.
                    // This happens if sleepOrDie() was called during shutdown.
                    $this->logger->info('Ticker interrupted by shutdown signal');
                    return;
                } catch (\Throwable $e) {
                    // Catch any other errors so the ticker doesn't die permanently
                    $this->logger->error('Ticker execution error', ['error' => $e->getMessage()]);
                }
            }
        });
    }
}
