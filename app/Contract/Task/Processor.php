<?php

declare(strict_types=1);

namespace App\Contract\Task;

/**
 * Task processor contract.
 *
 * Handles the execution of a single task, optionally reporting progress
 * via a callback.
 */
interface Processor
{
    /**
     * Executes the task.
     *
     * @param callable|null $onProgress Progress callback, receives progress percentage
     *
     * @return string Task result identifier
     */
    public function execute(?callable $onProgress = null): string;
}
