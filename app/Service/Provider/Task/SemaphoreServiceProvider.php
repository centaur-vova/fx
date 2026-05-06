<?php

declare(strict_types=1);

namespace App\Service\Provider\Task;

use App\Contract\Provider\Bootable;
use App\Contract\Provider\ServiceProvider;
use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\SemaphoreFactory;
use App\Contract\Task\TaskSemaphore;
use App\Server\Options;
use App\Service\Api\SemaphoreApi;
use App\Service\Task\Semaphore\DistributedSemaphore;
use App\Service\Task\Semaphore\GlobalSharedSemaphore;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Swoole\Atomic;

final readonly class SemaphoreServiceProvider implements ServiceProvider, Bootable
{
    private const array DRIVER_MAP = [
        'semaphore.api' => SemaphoreDriver::API,
        'semaphore.shared' => SemaphoreDriver::SHARED,
    ];

    public function register(ContainerBuilder $builder): array
    {
        return [
            // Horse Semaphores
            'semaphore.api' => $this->initDistributedSemaphore(...),
            'semaphore.shared' => $this->initGlobalSharedSemaphore(...),

            // Horse Factory
            SemaphoreFactory::class => fn (ContainerInterface $c): SemaphoreFactory => new readonly class ($c, self::DRIVER_MAP) implements SemaphoreFactory {
                /**
                 * @param array<string, SemaphoreDriver> $driverMap
                 */
                public function __construct(
                    private ContainerInterface $c,
                    private array $driverMap,
                ) {
                }

                public function get(string $driver): TaskSemaphore
                {
                    $driverEnum = SemaphoreDriver::tryFrom($driver);

                    $serviceName = array_search($driverEnum, $this->driverMap, true);
                    if (!$serviceName) {
                        throw new \InvalidArgumentException("Unknown semaphore driver: $driver");
                    }

                    /** @var TaskSemaphore $service */
                    $service = $this->c->get($serviceName);

                    return $service;
                }

                public function shutdown(): void
                {
                    foreach (array_keys($this->driverMap) as $serviceName) {
                        /**
                         * @var TaskSemaphore $service
                         */
                        $service = $this->c->get($serviceName);
                        $service->shutdown();
                    }
                }
            },
        ];
    }

    public function boot(ContainerInterface $c): void
    {
        // @TODO: 🩼 Needs a more elegant approach - Centaur gotta refactor this later. Kon-Vova doesn't mind.
        // Warm-up semaphore.shared so Swoole\Atomic objects are created in the Master process
        // before forking workers. Otherwise each worker gets its own copy — breaking shared memory.
        // Horses don't judge.
        $c->get('semaphore.shared');
    }

    // Go based semaphore (api)
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

    // PHP based semaphore (shared)
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
