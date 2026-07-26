<?php

use App\Models\AiProvider;
use Closure;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Wuwx\LaravelGateway\Events\GatewayRequestFailed;
use Wuwx\LaravelGateway\Events\GatewayRequestSending;
use Wuwx\LaravelGateway\Events\GatewayResponseReceived;
use Wuwx\LaravelGateway\Facades\Gateway;
use Wuwx\LaravelGateway\GatewayHandlerBase;

beforeEach(function () {
    Gateway::any('/v1/test-gateway/{path}', TestGatewayHandler::class);
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

it('normalizes a trailing slash on the base_uri', function () {
    Gateway::any('/v1/trailing-target/{path}', TrailingTargetHandler::class);

    Http::fake(function (HttpRequest $request) {
        expect($request->url())->toBe('https://upstream.test/echo');

        return Http::response(['ok' => true], 200);
    });

    $response = $this->get('/v1/trailing-target/echo');

    $response->assertOk();
});

it('uses the base_uri verbatim when no {path} wildcard is present', function () {
    Gateway::any('/v1/dynamic-target', DynamicTargetHandler::class);

    Http::fake(function (HttpRequest $request) {
        expect($request->url())->toBe('https://dynamic.test/fully/resolved');

        return Http::response(['ok' => true], 200);
    });

    $response = $this->postJson('/v1/dynamic-target', ['hello' => 'world']);

    $response->assertOk();
});

it('returns a buffered response when streaming is disabled', function () {
    Gateway::any('/v1/buffered/{path}', BufferedHandler::class);

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
    Gateway::any('/v1/timeout/{path}', TimeoutHandler::class);

    Http::fake(function (HttpRequest $request) {
        expect($request->header('X-Response-Time'))->toBe([]);

        return Http::response(['ok' => true], 200);
    });

    $response = $this->get('/v1/timeout/echo');

    $response->assertOk();
});

it('forwards to a provider endpoint resolved from the request', function () {
    app('router')->aliasMiddleware('inject-custom-provider', InjectCustomProviderMiddleware::class);

    Route::middleware('inject-custom-provider')->group(function () {
        Gateway::any('/v1/provider-endpoint', ProviderEndpointHandler::class);
    });

    Http::fake(function (HttpRequest $request) {
        expect($request->url())->toBe('https://provider.test/v1/custom-endpoint');

        return Http::response(['ok' => true], 200);
    });

    $response = $this->get('/v1/provider-endpoint');

    $response->assertOk();
});

it('falls back to the default endpoint when the provider option is missing', function () {
    app('router')->aliasMiddleware('inject-empty-provider', InjectEmptyProviderMiddleware::class);

    Route::middleware('inject-empty-provider')->group(function () {
        Gateway::any('/v1/default-endpoint', DefaultEndpointHandler::class);
    });

    Http::fake(function (HttpRequest $request) {
        expect($request->url())->toBe('https://provider.test/v1/fallback');

        return Http::response(['ok' => true], 200);
    });

    $response = $this->get('/v1/default-endpoint');

    $response->assertOk();
});

it('forwards request headers set by upstream middleware', function () {
    app('router')->aliasMiddleware('inject-test-headers', TestHeaderMiddleware::class);

    Route::middleware('inject-test-headers')->group(function () {
        Gateway::any('/v1/headers-test/{path}', TestGatewayHandler::class);
    });

    Http::fake(function (HttpRequest $request) {
        expect($request->header('authorization'))->toBe(['Bearer from-middleware']);
        expect($request->header('x-provider-header'))->toBe(['extra']);

        return Http::response(['ok' => true], 200);
    });

    $response = $this->get('/v1/headers-test/echo');

    $response->assertOk();
});

it('rejects paths containing dot segments', function () {
    Http::fake();

    $response = $this->get('/v1/test-gateway/../etc/passwd');

    $response->assertStatus(400);
    Http::assertNothingSent();
});

it('rejects paths containing an embedded scheme', function () {
    Http::fake();

    $response = $this->get('/v1/test-gateway/http://evil.example.com/x');

    $response->assertStatus(400);
    Http::assertNothingSent();
});

it('rejects paths with encoded dot segments', function () {
    Http::fake();

    $response = $this->get('/v1/test-gateway/%2e%2e/secret');

    $response->assertStatus(400);
    Http::assertNothingSent();
});

it('strips hop-by-hop headers from the forwarded request', function () {
    Http::fake(function (HttpRequest $request) {
        expect($request->hasHeader('Connection'))->toBeFalse();
        expect($request->hasHeader('Transfer-Encoding'))->toBeFalse();
        expect($request->hasHeader('Keep-Alive'))->toBeFalse();
        expect($request->hasHeader('Upgrade'))->toBeFalse();

        return Http::response(['ok' => true], 200);
    });

    $response = $this->withHeaders([
        'Connection' => 'keep-alive',
        'Transfer-Encoding' => 'chunked',
        'Keep-Alive' => 'timeout=5',
        'Upgrade' => 'websocket',
    ])->get('/v1/test-gateway/echo');

    $response->assertOk();
});

it('strips stale content headers from streamed responses', function () {
    Http::fake(function () {
        return Http::response('streamed body', 200, [
            'Content-Type' => 'text/plain',
            'Content-Encoding' => 'gzip',
            'Content-Length' => '999',
            'Transfer-Encoding' => 'chunked',
        ]);
    });

    $response = $this->get('/v1/test-gateway/echo');

    $response->assertOk();
    expect($response->headers->has('content-encoding'))->toBeFalse();
    expect($response->headers->has('content-length'))->toBeFalse();
    expect($response->headers->has('transfer-encoding'))->toBeFalse();
});

it('fires GatewayRequestSending and GatewayResponseReceived events', function () {
    Event::fake([
        GatewayRequestSending::class,
        GatewayResponseReceived::class,
    ]);

    Http::fake([
        'https://upstream.test/echo' => Http::response(['ok' => true], 200),
    ]);

    $this->get('/v1/test-gateway/echo');

    Event::assertDispatched(GatewayRequestSending::class, function ($event) {
        return $event->url === 'https://upstream.test/echo'
            && $event->handlerClass === TestGatewayHandler::class;
    });

    Event::assertDispatched(GatewayResponseReceived::class, function ($event) {
        return $event->handlerClass === TestGatewayHandler::class
            && $event->durationMs >= 0;
    });
});

it('fires GatewayRequestFailed event on connection errors', function () {
    Event::fake([
        GatewayRequestFailed::class,
    ]);

    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    try {
        $this->get('/v1/test-gateway/echo');
    } catch (ConnectionException) {
        // expected
    }

    Event::assertDispatched(GatewayRequestFailed::class, function ($event) {
        return $event->exception instanceof ConnectionException
            && $event->handlerClass === TestGatewayHandler::class;
    });
});

it('invokes getRequest and getResponse hooks on the handler', function () {
    Gateway::any('/v1/handler-hooks/{path}', HandlerHooksTestHandler::class);

    Http::fake(function (HttpRequest $request) {
        expect($request->header('x-before-forward'))->toBe(['injected']);

        return Http::response(['original' => true], 200);
    });

    $response = $this->get('/v1/handler-hooks/echo');

    $response->assertOk();
    $response->assertJsonPath('transformed', true);
    $response->assertJsonMissingPath('original');
});

it('registers a GET-only route via Gateway::get() with a handler class', function () {
    Gateway::get('/v1/gateway-get/{path}', TestGatewayHandler::class);

    Http::fake([
        'https://upstream.test/echo' => Http::response(['ok' => true], 200),
    ]);

    $this->get('/v1/gateway-get/echo')->assertOk();
    $this->postJson('/v1/gateway-get/echo', [])->assertMethodNotAllowed();
});

it('registers a POST-only route via Gateway::post() with a handler class', function () {
    Gateway::post('/v1/gateway-post/{path}', HandlerHooksTestHandler::class);

    Http::fake([
        'https://upstream.test/echo' => Http::response(['ok' => true], 200),
    ]);

    $this->postJson('/v1/gateway-post/echo', [])->assertOk();
    $this->get('/v1/gateway-post/echo')->assertMethodNotAllowed();
});

it('Gateway::any() responds to all HTTP methods', function () {
    Gateway::any('/v1/gateway-any/{path}', TestGatewayHandler::class);

    Http::fake([
        'https://upstream.test/echo' => Http::response(['ok' => true], 200),
    ]);

    $this->get('/v1/gateway-any/echo')->assertOk();
    $this->postJson('/v1/gateway-any/echo', [])->assertOk();
    $this->putJson('/v1/gateway-any/echo', [])->assertOk();
    $this->deleteJson('/v1/gateway-any/echo')->assertOk();
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

class InjectCustomProviderMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $provider = new AiProvider;
        $provider->base_url = 'https://provider.test';
        $provider->options = ['endpoints' => ['custom' => '/v1/custom-endpoint']];
        $request->attributes->set('gateway.provider', $provider);

        return $next($request);
    }
}

class InjectEmptyProviderMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $provider = new AiProvider;
        $provider->base_url = 'https://provider.test';
        $provider->options = [];
        $request->attributes->set('gateway.provider', $provider);

        return $next($request);
    }
}

class TestGatewayHandler extends GatewayHandlerBase
{
    public function getOptions(Request $request): array
    {
        return ['base_uri' => 'https://upstream.test'];
    }
}

class TrailingTargetHandler extends GatewayHandlerBase
{
    public function getOptions(Request $request): array
    {
        return ['base_uri' => 'https://upstream.test/'];
    }
}

class DynamicTargetHandler extends GatewayHandlerBase
{
    public function getOptions(Request $request): array
    {
        return ['base_uri' => 'https://dynamic.test/fully/resolved'];
    }
}

class BufferedHandler extends GatewayHandlerBase
{
    public function getOptions(Request $request): array
    {
        return [
            'base_uri' => 'https://upstream.test',
            'streaming' => false,
        ];
    }
}

class TimeoutHandler extends GatewayHandlerBase
{
    public function getOptions(Request $request): array
    {
        return [
            'base_uri' => 'https://upstream.test',
            'streaming' => false,
            'timeout' => 5,
        ];
    }
}

class ProviderEndpointHandler extends GatewayHandlerBase
{
    public function getOptions(Request $request): array
    {
        $provider = $request->attributes->get('gateway.provider');

        return [
            'base_uri' => rtrim($provider->base_url, '/')
                .data_get($provider->options, 'endpoints.custom', '/v1/default-endpoint'),
        ];
    }
}

class DefaultEndpointHandler extends GatewayHandlerBase
{
    public function getOptions(Request $request): array
    {
        $provider = $request->attributes->get('gateway.provider');

        return [
            'base_uri' => rtrim($provider->base_url, '/')
                .data_get($provider->options, 'endpoints.missing', '/v1/fallback'),
        ];
    }
}

class HandlerHooksTestHandler extends GatewayHandlerBase
{
    public function getOptions(Request $request): array
    {
        return [
            'base_uri' => 'https://upstream.test',
            'streaming' => false,
        ];
    }

    public function getRequest(Request $request): Request
    {
        $request->headers->set('x-before-forward', 'injected');

        return $request;
    }

    public function getResponse(Request $request, ClientResponse $response): ClientResponse
    {
        return new ClientResponse(
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{"transformed":true}'
            )
        );
    }
}
