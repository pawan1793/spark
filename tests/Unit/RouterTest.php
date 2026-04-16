<?php

namespace Spark\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Spark\Router\Route;

class RouterTest extends TestCase
{
    public function test_static_route_matches(): void
    {
        $route = new Route('GET', '/users', fn() => 'ok');
        $params = [];

        $this->assertTrue($route->matches('GET', '/users', $params));
        $this->assertEmpty($params);
    }

    public function test_route_does_not_match_wrong_method(): void
    {
        $route = new Route('GET', '/users', fn() => 'ok');
        $params = [];

        $this->assertFalse($route->matches('POST', '/users', $params));
    }

    public function test_route_extracts_parameters(): void
    {
        $route = new Route('GET', '/users/{id}', fn() => 'ok');
        $params = [];

        $this->assertTrue($route->matches('GET', '/users/42', $params));
        $this->assertSame(['id' => '42'], $params);
    }

    public function test_optional_parameter_is_null_when_missing(): void
    {
        $route = new Route('GET', '/users/{id?}', fn() => 'ok');
        $params = [];

        $this->assertTrue($route->matches('GET', '/users', $params));
        $this->assertNull($params['id'] ?? null);
    }

    public function test_any_method_matches_any_verb(): void
    {
        $route = new Route('ANY', '/ping', fn() => 'pong');
        $params = [];

        foreach (['GET', 'POST', 'DELETE', 'PUT', 'PATCH'] as $method) {
            $this->assertTrue($route->matches($method, '/ping', $params), "ANY should match $method");
        }
    }

    public function test_route_does_not_match_different_path(): void
    {
        $route = new Route('GET', '/about', fn() => 'ok');
        $params = [];

        $this->assertFalse($route->matches('GET', '/contact', $params));
    }

    public function test_route_fluent_middleware(): void
    {
        $route = new Route('GET', '/', fn() => 'ok');
        $route->middleware('auth')->middleware(['throttle', 'verified']);

        $this->assertSame(['auth', 'throttle', 'verified'], $route->middleware);
    }

    public function test_route_name(): void
    {
        $route = (new Route('GET', '/dashboard', fn() => 'ok'))->name('dashboard');

        $this->assertSame('dashboard', $route->name);
    }

    public function test_route_csrf_exempt(): void
    {
        $route = (new Route('POST', '/webhook', fn() => 'ok'))->withoutCsrf();

        $this->assertTrue($route->csrfExempt);
    }

    public function test_route_with_multiple_parameters(): void
    {
        $route = new Route('GET', '/users/{userId}/posts/{postId}', fn() => 'ok');
        $params = [];

        $this->assertTrue($route->matches('GET', '/users/5/posts/99', $params));
        $this->assertSame(['userId' => '5', 'postId' => '99'], $params);
    }
}
