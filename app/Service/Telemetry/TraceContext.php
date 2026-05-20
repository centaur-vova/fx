<?php

declare(strict_types=1);

namespace App\Service\Telemetry;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;

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
    public static function start(string $name, int $kind = SpanKind::KIND_INTERNAL, array $attributes = []): SpanInterface
    {
        $span = self::tracer()->spanBuilder($name)->setSpanKind($kind);

        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, $value);
        }

        return $span->startSpan();
    }

    /**
     * Continue a trace from an incoming traceparent header.
     *
     * @param non-empty-string $name
     * @param SpanKind::KIND_* $kind
     * @param array<string, mixed> $attributes
     */
    public static function continue(string $name, ?string $traceparent, int $kind = SpanKind::KIND_SERVER, array $attributes = []): ?SpanInterface
    {
        // Dont create a new span if no traceparent provided
        if ($traceparent === null) {
            return null;
        }
        $carrier = ['traceparent' => $traceparent];
        $parentContext = TraceContextPropagator::getInstance()->extract($carrier);

        $span = self::tracer()
            ->spanBuilder($name)
            ->setParent($parentContext)
            ->setSpanKind($kind);

        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, $value);
        }

        return $span->startSpan();
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
    public static function continueOrStart(string $name, ?string $traceparent, int $kind = SpanKind::KIND_INTERNAL, array $attributes = []): TraceScope
    {
        // Reset Swoole context to root for clean task isolation
        $rootScope = Context::getRoot()->activate();

        // Continue existing trace or start a new root span
        $span = self::continue($name, $traceparent, $kind, $attributes)
            ?? self::start($name, $kind, $attributes);

        $scope = $span->activate();

        return new TraceScope($span, $scope, $rootScope);
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
}
