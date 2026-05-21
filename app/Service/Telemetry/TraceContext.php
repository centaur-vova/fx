<?php

declare(strict_types=1);

namespace App\Service\Telemetry;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;

final class TraceContext
{
    private static ?string $serviceName = null;

    /**
     * Initialize the service name for tracing.
     * Called once at startup by TelemetryServiceProvider.
     */
    public static function init(string $serviceName): void
    {
        self::$serviceName = $serviceName;
    }

    /**
     * Start a brand new root span.
     *
     * @param non-empty-string $name
     * @param SpanKind::KIND_* $kind
     * @param array<string, mixed> $attributes
     */
    public static function start(
        string $name,
        int $kind = SpanKind::KIND_INTERNAL,
        array $attributes = [],
        ?ScopeInterface $rootScope = null,
    ): TraceScope {
        $span = self::tracer()->spanBuilder($name)->setSpanKind($kind);

        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, $value);
        }

        $startedSpan = $span->startSpan();
        $scope = $startedSpan->activate();

        return new TraceScope($startedSpan, $scope, $rootScope);
    }

    /**
     * Execute a callback within a traced span, handling lifecycle automatically.
     *
     * Starts or continues a span, executes the callback, records exceptions,
     * and cleans up the span and scope in a finally block.
     *
     * @template T
     * @param non-empty-string $name Span name
     * @param string|null $traceparent Incoming traceparent header (null = new root span)
     * @param SpanKind::KIND_* $kind Span kind (e.g., SpanKind::KIND_SERVER)
     * @param array<string, mixed> $attributes Span attributes
     * @param callable(SpanInterface): T $callback Business logic to execute
     * @param array<int, class-string<\Throwable>> $skipReportingFor Exception classes that should NOT be recorded as errors
     * @return T The return value of the callback
     * @throws \Throwable Rethrows any exception after recording it in the span
     */
    public static function run(
        string $name,
        ?string $traceparent,
        int $kind,
        array $attributes,
        callable $callback,
        array $skipReportingFor = [],
    ): mixed {
        $scope = self::continueOrStart($name, $traceparent, $kind, $attributes);

        try {
            $result = $callback($scope->span);
            $scope->span->setStatus(StatusCode::STATUS_OK);
            return $result;
        } catch (\Throwable $e) {
            $shouldReport = array_all($skipReportingFor, fn ($class) => !$e instanceof $class);
            if ($shouldReport) {
                $scope->span->recordException($e);
                $scope->span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            } else {
                $scope->span->setStatus(StatusCode::STATUS_OK);
            }

            throw $e;
        } finally {
            $scope->detach();
        }
    }

    /**
     * Start a lightweight child span (no TraceScope, no rootScope).
     * For internal spans inside an already active trace.
     *
     * @param non-empty-string $name Span name
     * @param SpanKind::KIND_* $kind Span kind (e.g., SpanKind::KIND_SERVER)
     * @param array<string, mixed> $attributes Span attributes
     */
    public static function startSpan(string $name, int $kind = SpanKind::KIND_INTERNAL, array $attributes = []): SpanInterface
    {
        $span = self::tracer()->spanBuilder($name)->setSpanKind($kind);

        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, $value);
        }

        return $span->startSpan();
    }

    /**
     * Extract current trace context as a traceparent string for propagation.
     */
    public static function inject(): ?string
    {
        $carrier = [];
        TraceContextPropagator::getInstance()->inject($carrier);

        /** @var array{traceparent?: string} $carrier */
        return $carrier['traceparent'] ?? null;
    }

    /**
     * Extract traceparent from headers.
     *
     * @param array<string, mixed> $headers
     */
    public static function extract(array $headers): ContextInterface
    {
        return TraceContextPropagator::getInstance()->extract($headers);
    }

    /**
     * @throws \RuntimeException If service name is not initialized.
     */
    public static function tracer(): TracerInterface
    {
        if (self::$serviceName === null) {
            throw new \RuntimeException(
                'TraceContext not initialized. Call TraceContext::init() first.'
            );
        }

        return Globals::tracerProvider()->getTracer(self::$serviceName);
    }

    /**
     * Shutdown the tracer provider and flush any pending spans.
     * Should be called on worker stop.
     */
    public static function shutdown(): void
    {
        $tracerProvider = Globals::tracerProvider();
        if ($tracerProvider instanceof TracerProvider) {
            $tracerProvider->shutdown();
        }
    }

    public static function flush(): void
    {
        $tracerProvider = Globals::tracerProvider();
        if (method_exists($tracerProvider, 'forceFlush')) {
            $tracerProvider->forceFlush();
        }
    }

    /**
     * Continue a trace from traceparent, or start a new root span if null.
     * Resets Swoole context to prevent cross-task contamination.
     *
     * @param non-empty-string $name Span name
     * @param ?string $traceparent Incoming traceparent header
     * @param SpanKind::KIND_* $kind Span kind
     * @param array<string, mixed> $attributes Span attributes
     * @return TraceScope
     */
    private static function continueOrStart(string $name, ?string $traceparent, int $kind = SpanKind::KIND_INTERNAL, array $attributes = []): TraceScope
    {
        if ($traceparent === null) {
            // No traceparent — start a fresh root span
            $rootScope = Context::getRoot()->activate();
            return TraceContext::start($name, $kind, $attributes, $rootScope);
        }

        // Extract parent context and continue the trace
        $carrier = ['traceparent' => $traceparent];
        $parentContext = TraceContextPropagator::getInstance()->extract($carrier);

        // Activate parent context so the new span becomes a child
        $rootScope = $parentContext->activate();

        $span = self::tracer()
            ->spanBuilder($name)
            ->setParent($parentContext)
            ->setSpanKind($kind);

        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, $value);
        }

        $startedSpan = $span->startSpan();
        $scope = $startedSpan->activate();

        return new TraceScope($startedSpan, $scope, $rootScope);
    }
}
