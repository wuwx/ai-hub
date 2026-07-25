<?php

use Closure;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::gateway('/v1/test-gateway/{path}', function ($route) {
        $route->to('https://upstream.test');
    });
});

it('forwards the captured path and request body to the upstream target', function () {
    Http::fake([
        'https://upstream.test/chat/completions' => function (HttpRequest $request) {
            expect($request->method())->toBe('POST');
            expect($request->body())->toBe('{"hello":"world"}');

            return Http::response(['ok' => true], 200);
        },
    ]);

    $response = $this->postJson('/v1/test-gateway/chat/completions', ['hello' => 'world']);

    $response->assertOk();
    $response->assertJsonPath('ok', true);
});

it('preserves the upstream response status code', function () {
    Http::fake([
        'https://upstream.test/error' => Http::response(['error' => 'bad'], 422),
    ]);

    $response = $this->get('/v1/test-gateway/error');

    $response->assertStatus(422);
});

it('forwards query strings to the upstream target', function () {
    Http::fake(function (HttpRequest $request) {
        expect($request->url())->toBe('https://upstream.test/models?limit=10');

        return Http::response(['data' => []], 200);
    });

    $response = $this->get('/v1/test-gateway/models?limit=10');

    $response->assertOk();
});

it('matches multiple path segments through the {path} wildcard', function () {
    Http::fake([
        'https://upstream.test/foo/bar/baz' => Http::response(['matched' => true], 200),
    ]);

    $response = $this->get('/v1/test-gateway/foo/bar/baz');

    $response->assertOk();
    $response->assertJsonPath('matched', true);
});

it('normalizes a trailing slash on the target URL', function () {
    Route::gateway('/v1/trailing-target/{path}', function ($route) {
        $route->to('https://upstream.test/');
    });

    Http::fake(function (HttpRequest $request) {
        expect($request->url())->toBe('https://upstream.test/echo');

        return Http::response(['ok' => true], 200);
    });

    $response = $this->get('/v1/trailing-target/echo');

    $response->assertOk();
});

it('forwards to a URL returned by a closure target without appending the path', function () {
    Route::gateway('/v1/dynamic-target/{path}', function ($route) {
        $route->to(fn (Request $request) => 'https://dynamic.test/fully/resolved');
    });

    Http::fake(function (HttpRequest $request) {
        expect($request->url())->toBe('https://dynamic.test/fully/resolved');

        return Http::response(['ok' => true], 200);
    });

    $response = $this->postJson('/v1/dynamic-target/anything', ['hello' => 'world']);

    $response->assertOk();
});

it('returns a buffered response when streaming is disabled', function () {
    Route::gateway('/v1/buffered/{path}', function ($route) {
        $route
            ->to('https://upstream.test')
            ->streaming(false);
    });

    Http::fake([
        'https://upstream.test/embed' => Http::response([
            'data' => [0.1, 0.2, 0.3],
        ], 200, ['Content-Type' => 'application/json']),
    ]);

    $response = $this->postJson('/v1/buffered/embed', ['input' => 'hello']);

    $response->assertOk();
    $response->assertJsonPath('data.0', 0.1);
});

it('applies a custom timeout when configured', function () {
    Route::gateway('/v1/timeout/{path}', function ($route) {
        $route
            ->to('https://upstream.test')
            ->streaming(false)
            ->timeout(5);
    });

    Http::fake(function (HttpRequest $request) {
        expect($request->header('X-Response-Time'))->toBe([]);

        return Http::response(['ok' => true], 200);
    });

    $response = $this->get('/v1/timeout/echo');

    $response->assertOk();
});

it('forwards request headers set by upstream middleware', function () {
    app('router')->aliasMiddleware('inject-test-headers', TestHeaderMiddleware::class);

    Route::middleware('inject-test-headers')->group(function () {
        Route::gateway('/v1/headers-test/{path}', function ($route) {
            $route->to('https://upstream.test');
        });
    });

    Http::fake(function (HttpRequest $request) {
        expect($request->header('authorization'))->toBe(['Bearer from-middleware']);
        expect($request->header('x-provider-header'))->toBe(['extra']);

        return Http::response(['ok' => true], 200);
    });

    $response = $this->get('/v1/headers-test/echo');

    $response->assertOk();
});

class TestHeaderMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Authorization', 'Bearer from-middleware');
        $request->headers->set('X-Provider-Header', 'extra');

        return $next($request);
    }
}
