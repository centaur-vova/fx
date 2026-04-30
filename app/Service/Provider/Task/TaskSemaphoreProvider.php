<?php

declare(strict_types=1);

namespace App\Service\Provider\Task;

use App\Contract\Provider\Bootable;
use App\Contract\Provider\ServiceProvider;
use App\Contract\Task\TaskSemaphore;
use App\Server\Options;
use App\Service\Api\SemaphoreApi;
use App\Service\Task\Semaphore\DistributedSemaphore;
use App\Service\Task\Semaphore\GlobalSharedSemaphore;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Swoole\Atomic;

final readonly class TaskSemaphoreProvider implements ServiceProvider, Bootable
{
    private const string DRIVER_API = 'api';
    private const string DRIVER_SHARED = 'shared';

    public function register(ContainerBuilder $builder): array
    {
        return [
            TaskSemaphore::class => function (ContainerInterface $c): TaskSemaphore {
                /** @var Options $options */
                $options = $c->get(Options::class);
                $driver = $options->semaphoreDriver;

                return match ($driver) {
                    self::DRIVER_API => $this->initDistributedSemaphore($c),
                    default => $this->initGlobalSharedSemaphore($c),
                };
            },
        ];
    }

    public function boot(ContainerInterface $c): void
    {
        /** @var Options $options */
        $options = $c->get(Options::class);

        // We only trigger the container if we KNOW it's a shared driver
        // that needs Master-process allocation.
        if ($options->semaphoreDriver === self::DRIVER_SHARED) {
            $c->get(TaskSemaphore::class);
            // Now it's warmed up.
        }
    }

    private function initDistributedSemaphore(ContainerInterface $c): TaskSemaphore
    {
        /** @var Options $options */
        $options = $c->get(Options::class);

        /** @var SemaphoreApi $api */
        $api = $c->get(SemaphoreApi::class);

        return new DistributedSemaphore(
            api: $api,
            semaphorePermitTtl: $options->semaphorePermitTtl,
        );
    }

    private function initGlobalSharedSemaphore(ContainerInterface $c): TaskSemaphore
    {
        /** @var Options $options */
        $options = $c->get(Options::class);

        /*
         * Pre-allocate Shared Memory Semaphores.
         * The index $i represents the 'max_concurrency' level.
         *
         * Each Atomic object is a shared counter across all Swoole workers.
         */
        $semaphoreLimit = max(1, $options->taskSemaphoreLimit);
        $semaphoreAtomics = [];

        for ($i = 1; $i <= $semaphoreLimit; $i++) {
            // Each index represents a specific max_concurrent limit
            $semaphoreAtomics[$i] = new Atomic(0);
        }

        return new GlobalSharedSemaphore($semaphoreAtomics);
    }
}
