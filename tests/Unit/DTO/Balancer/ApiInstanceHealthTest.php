<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Balancer;

use App\DTO\Balancer\ApiInstanceHealth;
use PHPUnit\Framework\TestCase;

class ApiInstanceHealthTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'hash' => 'abc123',
            'alive' => true,
            'cb_state' => 'closed',
            'requests' => 1500,
            'errors' => 5,
        ];

        $dto = ApiInstanceHealth::fromArray($data);

        $this->assertSame('abc123', $dto->hash);
        $this->assertTrue($dto->alive);
        $this->assertSame('closed', $dto->cbState);
        $this->assertSame(1500, $dto->requests);
        $this->assertSame(5, $dto->errors);
    }

    public function testFromArrayWithMissingFields(): void
    {
        $data = [];

        $dto = ApiInstanceHealth::fromArray($data);

        $this->assertSame('', $dto->hash);
        $this->assertFalse($dto->alive);
        $this->assertSame('closed', $dto->cbState);
        $this->assertSame(0, $dto->requests);
        $this->assertSame(0, $dto->errors);
    }

    public function testFromArrayWithPartialData(): void
    {
        $data = [
            'hash' => 'partial',
            'requests' => 999,
        ];

        $dto = ApiInstanceHealth::fromArray($data);

        $this->assertSame('partial', $dto->hash);
        $this->assertFalse($dto->alive);
        $this->assertSame('closed', $dto->cbState);
        $this->assertSame(999, $dto->requests);
        $this->assertSame(0, $dto->errors);
    }

    public function testJsonSerialize(): void
    {
        $dto = new ApiInstanceHealth(
            hash: 'abc123',
            alive: true,
            cbState: 'half-open',
            requests: 42,
            errors: 1,
        );

        $expected = [
            'hash' => 'abc123',
            'alive' => true,
            'cb_state' => 'half-open',
            'requests' => 42,
            'errors' => 1,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }
}
