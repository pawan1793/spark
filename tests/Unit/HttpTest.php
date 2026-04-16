<?php

namespace Spark\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Spark\Http\Request;
use Spark\Http\Response;

class HttpTest extends TestCase
{
    // ── Request ──────────────────────────────────────────────────────────────

    public function test_request_method_from_server(): void
    {
        $req = new Request([], [], ['REQUEST_METHOD' => 'POST'], [], []);
        $this->assertSame('POST', $req->method());
    }

    public function test_request_method_override_via_post_field(): void
    {
        $req = new Request([], ['_method' => 'PUT'], ['REQUEST_METHOD' => 'POST'], [], []);
        $this->assertSame('PUT', $req->method());
    }

    public function test_request_path_strips_query_string(): void
    {
        $req = new Request([], [], ['REQUEST_URI' => '/users?page=2'], [], []);
        $this->assertSame('/users', $req->path());
    }

    public function test_request_path_normalises_trailing_slash(): void
    {
        $req = new Request([], [], ['REQUEST_URI' => '/about/'], [], []);
        $this->assertSame('/about', $req->path());
    }

    public function test_request_input_merges_get_and_post(): void
    {
        $req = new Request(['page' => '2'], ['name' => 'Alice'], ['REQUEST_METHOD' => 'POST'], [], []);
        $this->assertSame('2', $req->input('page'));
        $this->assertSame('Alice', $req->input('name'));
    }

    public function test_request_only_returns_subset(): void
    {
        $req = new Request([], ['a' => '1', 'b' => '2', 'c' => '3'], ['REQUEST_METHOD' => 'POST'], [], []);
        $this->assertSame(['a' => '1', 'c' => '3'], $req->only(['a', 'c']));
    }

    public function test_request_has_detects_keys(): void
    {
        $req = new Request(['token' => 'abc'], [], [], [], []);
        $this->assertTrue($req->has('token'));
        $this->assertFalse($req->has('missing'));
    }

    public function test_request_json_decodes_body(): void
    {
        $body = json_encode(['user' => 'Ada']);
        $req = new Request([], [], ['CONTENT_TYPE' => 'application/json'], [], [], $body);
        $this->assertSame('Ada', $req->json('user'));
    }

    public function test_request_attributes_set_and_get(): void
    {
        $req = new Request([], [], [], [], []);
        $req->setAttribute('userId', 7);
        $this->assertSame(7, $req->attribute('userId'));
    }

    public function test_request_ip_defaults_to_remote_addr(): void
    {
        $req = new Request([], [], ['REMOTE_ADDR' => '1.2.3.4'], [], []);
        $this->assertSame('1.2.3.4', $req->ip());
    }

    // ── Response ─────────────────────────────────────────────────────────────

    public function test_response_json_sets_content_type(): void
    {
        $res = (new Response())->json(['ok' => true]);
        $this->assertSame(200, $res->getStatus());
        $this->assertStringContainsString('application/json', $res->getHeaders()['Content-Type']);
    }

    public function test_response_status_code(): void
    {
        $res = (new Response())->html('Not Found', 404);
        $this->assertSame(404, $res->getStatus());
    }

    public function test_response_custom_header(): void
    {
        $res = (new Response())->header('X-Custom', 'value');
        $this->assertSame('value', $res->getHeaders()['X-Custom']);
    }

    public function test_response_header_rejects_crlf(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Response())->header("X-Bad\r\n", 'injected');
    }

    public function test_response_redirect_allows_relative_url(): void
    {
        $res = (new Response())->redirect('/dashboard');
        $this->assertSame(302, $res->getStatus());
        $this->assertSame('/dashboard', $res->getHeaders()['Location']);
    }

    public function test_response_redirect_blocks_protocol_relative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Response())->redirect('//evil.com');
    }

    public function test_response_text(): void
    {
        $res = (new Response())->text('hello', 201);
        $this->assertSame(201, $res->getStatus());
        $this->assertSame('hello', $res->getContent());
        $this->assertStringContainsString('text/plain', $res->getHeaders()['Content-Type']);
    }
}
