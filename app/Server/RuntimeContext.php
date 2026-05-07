<?php

declare(strict_types=1);

namespace App\Server;

use App\Exception\Server\WorkerShutdownException;
use Swoole\Atomic;
use Swoole\Coroutine\Channel;

/**
 * Manages the server runtime state and lifecycle signals.
 *
 * This context resides in the Master process and is copied to Workers upon forking.
 * It uses Atomic for global state synchronization and Channels for inter-coroutine
 * signaling within a single Worker process.
 */
final class RuntimeContext
{
    /**
     * Global atomic flag indicating the shutdown state across all processes.
     */
    private readonly Atomic $shuttingDown;

    /**
     * Local channel used to signal worker-specific coroutines to stop.
     * Initialized lazily within the worker process.
     *
     * @var ?Channel<null>
     */
    private ?Channel $shutdownChan = null;

    /**
     * Initializes the context with a global shutdown flag.
     */
    public function __construct()
    {
        $this->shuttingDown = new Atomic(0);
    }

    /**
     * Checks if the server or the current worker is in the process of shutting down.
     *
     * @return bool True if shutting down, false otherwise.
     */
    public function isShuttingDown(): bool
    {
        return $this->shuttingDown->get() === 1;
    }

    /**
     * Returns the shutdown signal channel for the current worker.
     * Use this in coroutines (e.g., via $channel->pop($timeout)) to handle graceful exit.
     *
     * @return Channel<null>
     */
    public function getShutdownSignal(): Channel
    {
        return $this->shutdownChan ??= new Channel(1);
    }

    /**
     * Triggers the shutdown signal by closing the worker-specific channel.
     * All coroutines waiting on this channel will be awakened immediately.
     */
    public function triggerShutdown(): void
    {
        // Atomic first
        $this->shuttingDown->set(1);

        if ($this->shutdownChan) {
            $this->shutdownChan->close();
            $this->shutdownChan = null;
        }
    }

    /**
     * Sleeps or throws if shutdown is initiated.
     *
     * @throws WorkerShutdownException If interrupted by shutdown.
     */
    public function sleepOrDie(float $seconds): void
    {
        /**
         * The pop() method acts as an interruptible timer.
         * It blocks the execution for $seconds OR wakes up immediately
         * when the shutdown channel is closed in triggerShutdown().
         */
        $this->getShutdownSignal()->pop($seconds);

        if ($this->isShuttingDown()) {
            throw new WorkerShutdownException('Worker is shutting down');
        }
    }
}
