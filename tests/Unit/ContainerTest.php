<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Container;
use App\Exceptions\Container\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function test_it_can_set_and_get_service(): void
    {
        $container = new Container();
        $container->set('test_service', fn () => new \stdClass());

        $this->assertTrue($container->has('test_service'));
        $this->assertInstanceOf(\stdClass::class, $container->get('test_service'));
    }

    public function test_it_returns_same_instance_for_singleton(): void
    {
        $container = new Container();
        $container->set('singleton', fn () => new \stdClass());

        $instance1 = $container->get('singleton');
        $instance2 = $container->get('singleton');

        $this->assertSame($instance1, $instance2);
    }

    public function test_it_throws_exception_if_service_not_found(): void
    {
        $container = new Container();

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('Service not found: missing');

        $container->get('missing');
    }

    public function test_forget_removes_instance_but_keeps_definition(): void
    {
        $container = new Container();
        $container->set('service', fn () => new \stdClass());

        $instance1 = $container->get('service');
        $container->forget('service');

        $this->assertTrue($container->has('service'));

        $instance2 = $container->get('service');

        $this->assertNotSame($instance1, $instance2);
    }
}
