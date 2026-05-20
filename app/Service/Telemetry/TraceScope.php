<?php

declare(strict_types=1);

namespace App\Service\Telemetry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;

final class TraceScope
{
    public function __construct(
        public readonly SpanInterface $span,
        private readonly ScopeInterface $scope,
        private readonly ScopeInterface $rootScope,
    ) {
    }

    /**
     * Clean up all OpenTelemetry and Swoole scopes and end the span.
     */
    public function detach(): void
    {
        $this->span->end();
        $this->scope->detach();
        $this->rootScope->detach();
    }
}
