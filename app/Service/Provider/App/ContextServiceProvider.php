<?php

declare(strict_types=1);

namespace App\Service\Provider\App;

use App\Contract\Provider\ServiceProvider;
use App\Contract\Provider\WorkerStartAware;
use DI\ContainerBuilder;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator as OtelConfigurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Context as OtelContext;
use OpenTelemetry\Context\ContextStorage as OtelContextStorage;
use OpenTelemetry\Contrib\Context\Swoole\SwooleContextStorage;
use OpenTelemetry\SDK\Trace\TracerProviderFactory;
use Psr\Container\ContainerInterface;
use Swoole\Server;

final class ContextServiceProvider implements ServiceProvider, WorkerStartAware
{
    public function register(ContainerBuilder $builder): array
    {
        return [];
    }

    public function onWorkerStart(ContainerInterface $c, Server $server, int $workerId): void
    {
        $this->setupOtel();
    }

    private function setupOtel(): void
    {
        // Swap the low-level storage to use official Swoole bridge with standard fallback
        OtelContext::setStorage(new SwooleContextStorage(new OtelContextStorage()));

        // Initialize the core instrumentation engine
        $tracerProvider = new TracerProviderFactory()->create();

        // Register globally via official Initializer pipeline to satisfy static analysis
        Globals::registerInitializer(static fn (OtelConfigurator $configurator) => $configurator
            ->withTracerProvider($tracerProvider)
            ->withPropagator(TraceContextPropagator::getInstance()));
    }
}
