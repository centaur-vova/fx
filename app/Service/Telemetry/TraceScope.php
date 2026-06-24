<?php

declare(strict_types=1);

namespace App\Service\Telemetry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;

/**
 * Represents an active trace span with its associated scope(s).
 *
 * Acts as a container for a currently active OpenTelemetry span and the
 * context scope(s) that keep it active. The scope must be detached when
 * the traced operation completes to prevent context leaks.
 *
 * This is used by TraceContext::start() and TraceContext::run() to bundle
 * a span together with one or two levels of scope activation.
 *
 * Why two scopes:
 * - The `$scope` is the span's own activation scope.
 * - The `$rootScope` is the parent context activation scope, used when
 *   continuing an existing trace from a traceparent header.
 *
 * Both must be detached in reverse order to clean up correctly.
 */
final class TraceScope
{
    public function __construct(
        public readonly SpanInterface $span,
        private readonly ScopeInterface $scope,
        private readonly ?ScopeInterface $rootScope = null,
    ) {
    }

    /**
     * Clean up all OpenTelemetry and Swoole scopes and end the span.
     *
     * Must be called after the traced operation completes.
     * Detaches in correct order: span scope first, then root scope.
     * Ends the span to flush it to the exporter.
     */
    public function detach(): void
    {
        $this->span->end();
        $this->scope->detach();
        $this->rootScope?->detach();
    }
}
