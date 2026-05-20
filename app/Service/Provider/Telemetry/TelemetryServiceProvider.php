<?php

declare(strict_types=1);

namespace App\Service\Provider\Telemetry;

use App\Contract\Provider\Bootable;
use App\Contract\Provider\ServiceProvider;
use App\Contract\Provider\WorkerStopAware;
use App\Server\Options;
use App\Service\Telemetry\TraceContext;
use DI\ContainerBuilder;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator as OtelConfigurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context as OtelContext;
use OpenTelemetry\Context\ContextStorage as OtelContextStorage;
use OpenTelemetry\Contrib\Context\Swoole\SwooleContextStorage;
use OpenTelemetry\SDK\Trace\TracerProviderFactory;
use Psr\Container\ContainerInterface;

final class TelemetryServiceProvider implements ServiceProvider, Bootable, WorkerStopAware
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            TracerInterface::class => function (ContainerInterface $c): TracerInterface {
                /** @var Options $options */
                $options = $c->get(Options::class);

                return TraceContext::tracer();
            },
        ];
    }

    public function boot(ContainerInterface $container): void
    {
        /** @var Options $options */
        $options = $container->get(Options::class);
        TraceContext::init($options->otelServiceName);

        $this->setupOtel();
    }

    public function onWorkerStop(ContainerInterface $c, int $workerId): void
    {
        TraceContext::shutdown();
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
