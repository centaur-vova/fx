<?php

declare(strict_types=1);

namespace App\Server\Http\Controller;

use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\SemaphoreFactory;
use App\Contract\Task\TaskSemaphore;
use App\Server\Http\Attribute\Route;
use Swoole\Http\Request;
use Swoole\Http\Response;

final readonly class BenchmarkController
{
    public function __construct(private SemaphoreFactory $semaphoreFactory)
    {
    }

    /**
     * Run semaphore performance benchmark
     * Only available in dev/test environment
     *
     * @return array{
     *     shared: array{total_ms: float, avg_us: float, driver: string},
     *     api: array{total_ms: float, avg_us: float, driver: string},
     *     comparison: array{api_is_x_times_slower: float}
     * }
     */
    #[Route(method: 'GET', path: '/benchmark/semaphore')]
    public function semaphore(Request $req, Response $res): array
    {
        $iterations = 1000;
        $mc = 5;

        $results = [];

        // Benchmark SHARED (PHP Atomic)
        $sharedSemaphore = $this->semaphoreFactory->get(SemaphoreDriver::SHARED);
        $sharedTime = $this->runBenchmark($sharedSemaphore, $iterations, $mc);
        $results['shared'] = [
            'total_ms' => round($sharedTime * 1000, 2),
            'avg_us' => round(($sharedTime / $iterations) * 1_000_000, 2),
            'driver' => 'PHP Atomic (local)',
        ];

        // Benchmark API (Go Distributed)
        $apiSemaphore = $this->semaphoreFactory->get(SemaphoreDriver::API);
        $apiTime = $this->runBenchmark($apiSemaphore, $iterations, $mc);
        $results['api'] = [
            'total_ms' => round($apiTime * 1000, 2),
            'avg_us' => round(($apiTime / $iterations) * 1_000_000, 2),
            'driver' => 'Go Distributed (Redis+Lua)',
        ];

        // Comparison
        $results['comparison'] = [
            'api_is_x_times_slower' => round($apiTime / $sharedTime, 2),
        ];

        return $results;
    }

    private function runBenchmark(TaskSemaphore $semaphore, int $iterations, int $mc): float
    {
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $permit = $semaphore->forLimit($mc);
            $permit->acquire(1.0);
            $permit->release();
        }

        return microtime(true) - $start;
    }
}
