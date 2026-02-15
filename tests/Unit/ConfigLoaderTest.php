<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ConfigLoader;
use PHPUnit\Framework\TestCase;

class ConfigLoaderTest extends TestCase
{
    public function test_it_correctly_manages_data(): void
    {
        $config = new ConfigLoader([
            'TEST_KEY' => '123',
            'STRING' => 'hello',
        ]);

        $this->assertEquals('123', $config->getString('TEST_KEY'));
        $this->assertSame(123, $config->getInt('TEST_KEY'));
        $this->assertEquals('default', $config->getString('MISSING', 'default'));
    }
}
