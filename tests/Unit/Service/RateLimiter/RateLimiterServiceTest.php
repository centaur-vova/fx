<?php

declare(strict_types=1);

namespace Tests\Unit\Service\RateLimiter;

use App\Contract\Storage\CacheStorage;
use App\Service\RateLimiter\RateLimiterConfig;
use App\Service\RateLimiter\RateLimiterService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RateLimiterServiceTest extends TestCase
{
    private MockObject|CacheStorage $storage;
    private MockObject|RateLimiterConfig $config;
    private MockObject|LoggerInterface $logger;
    private RateLimiterService $service;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(CacheStorage::class);
        $this->config = new RateLimiterConfig([
            'test' => ['max_attempts' => 3, 'ttl' => 60],
        ]);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new RateLimiterService($this->storage, $this->config, $this->logger);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAllowedWhenNoLimiterConfig(): void
    {
        $this->logger->expects($this->once())->method('warning');
        $this->assertTrue($this->service->allowed('unknown', 'ip:1.2.3.4'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAllowedFirstAttempt(): void
    {
        $this->storage->expects($this->once())
            ->method('get')
            ->willReturn(null);
        $this->storage->expects($this->once())
            ->method('set')
            ->with($this->stringContains('test:ip:1.2.3.4'), '1', 60);

        $this->assertTrue($this->service->allowed('test', 'ip:1.2.3.4'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAllowedUnderLimit(): void
    {
        $this->storage->expects($this->once())
            ->method('get')
            ->willReturn('2');
        $this->storage->expects($this->once())
            ->method('set')
            ->with($this->anything(), '3', 60)
            ->willReturn(true);

        $this->assertTrue($this->service->allowed('test', 'ip:1.2.3.4'));
    }

    public function testNotAllowedWhenLimitExceeded(): void
    {
        $this->storage->expects($this->once())
            ->method('get')
            ->willReturn('3');
        $this->storage->expects($this->never())
            ->method('set');

        $this->logger->expects($this->once())->method('info');

        $this->assertFalse($this->service->allowed('test', 'ip:1.2.3.4'));
    }

    public function testAllowedWhenStorageSetFails(): void
    {
        $this->storage->expects($this->once())
            ->method('get')
            ->willReturn('1');
        $this->storage->expects($this->once())
            ->method('set')
            ->willReturn(false);
        $this->logger->expects($this->once())->method('warning');

        $this->assertFalse($this->service->allowed('test', 'ip:1.2.3.4')); // fallback
    }
}
