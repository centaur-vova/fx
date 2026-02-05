<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function test_it_correctly_manages_data(): void
    {
        $config = new Config([
            'TEST_KEY' => '123',
            'STRING' => 'hello',
        ]);

        $this->assertEquals('123', $config->get('TEST_KEY'));
        $this->assertSame(123, $config->getInt('TEST_KEY'));
        $this->assertEquals('default', $config->get('MISSING', 'default'));
    }
}
