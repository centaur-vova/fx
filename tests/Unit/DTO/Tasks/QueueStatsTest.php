<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Tasks;

use App\DTO\Tasks\QueueStats;
use PHPUnit\Framework\TestCase;

class QueueStatsTest extends TestCase
{
    public function test_it_correctly_serializes_to_json(): void
    {
        $stats = new QueueStats(usage: 150, max: 1000);

        $json = $stats->jsonSerialize();

        $this->assertEquals(150, $json['usage']);
        $this->assertEquals(1000, $json['max']);
    }

    public function test_properties_are_accessible(): void
    {
        $stats = new QueueStats(usage: 5, max: 100);

        $this->assertSame(5, $stats->usage);
        $this->assertSame(100, $stats->max);
    }
}
