<?php

namespace Spark\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Spark\Container;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_bind_resolves_new_instance_each_call(): void
    {
        $this->container->bind('counter', fn() => new \stdClass());

        $a = $this->container->make('counter');
        $b = $this->container->make('counter');

        $this->assertNotSame($a, $b);
    }

    public function test_singleton_returns_same_instance(): void
    {
        $this->container->singleton('db', fn() => new \stdClass());

        $a = $this->container->make('db');
        $b = $this->container->make('db');

        $this->assertSame($a, $b);
    }

    public function test_instance_returns_registered_object(): void
    {
        $obj = new \stdClass();
        $obj->value = 42;

        $this->container->instance('thing', $obj);

        $this->assertSame($obj, $this->container->make('thing'));
        $this->assertSame(42, $this->container->make('thing')->value);
    }

    public function test_make_throws_for_unbound_non_class(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make('no_such_binding');
    }

    public function test_build_resolves_constructor_dependencies(): void
    {
        $instance = $this->container->build(DummyService::class);

        $this->assertInstanceOf(DummyService::class, $instance);
        $this->assertInstanceOf(DummyDependency::class, $instance->dep);
    }

    public function test_call_injects_dependencies_into_closure(): void
    {
        $this->container->instance(DummyDependency::class, new DummyDependency());

        $result = $this->container->call(function (DummyDependency $dep) {
            return $dep instanceof DummyDependency;
        });

        $this->assertTrue($result);
    }
}

class DummyDependency {}

class DummyService
{
    public function __construct(public readonly DummyDependency $dep) {}
}
