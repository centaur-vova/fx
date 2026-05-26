<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Http\Request;

use App\Server\Http\Request\ToggleInstance;
use PHPUnit\Framework\TestCase;

class ToggleInstanceTest extends TestCase
{
    public function testFromArray(): void
    {
        $payload = ['hash' => 'abc123', 'alive' => true];
        $dto = ToggleInstance::fromArray($payload);

        $this->assertSame('abc123', $dto->hash);
        $this->assertTrue($dto->alive);
    }

    public function testFromArrayWithMissingHash(): void
    {
        $dto = ToggleInstance::fromArray([]);

        $this->assertSame('', $dto->hash);
        $this->assertFalse($dto->alive);
    }

    public function testFromArrayWithStringAlive(): void
    {
        $payload = ['hash' => '123', 'alive' => 'true'];
        $dto = ToggleInstance::fromArray($payload);

        $this->assertTrue($dto->alive);
    }
}
