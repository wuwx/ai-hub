<?php

namespace App\Http\Controllers;

use App\Http\Routing\GatewayDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class GatewayController extends Controller
{
    /**
     * Forward an incoming request to the configured upstream target.
     *
     * When the target is a static string, the matched `{path}` wildcard (set
     * via `where('path', '.*')` in the {@see Route::gateway()} macro) is
     * appended to it. When the target is a closure, it receives the request
     * and returns the complete URL — no path is appended.
     *
     * Request headers (including any set by upstream middleware, e.g. an
     * Authorization header injected by ResolveGatewayProvider) are forwarded
     * as-is, minus the `host` header.
     *
     * The response mode (streaming vs. buffered) and timeout are driven by
     * the GatewayDefinition.
     */
    public function __invoke(Request $request, GatewayDefinition $definition): Response
    {
        if ($definition->target instanceof \Closure) {
            $url = ($definition->target)($request);
        } else {
            $url = rtrim($definition->target, '/');

            if ($path = ltrim((string) $request->route('path', ''), '/')) {
                $url .= '/'.$path;
            }
        }

        if ($query = $request->getQueryString()) {
            $url .= '?'.$query;
        }

        $headers = $request->headers->all();
        unset($headers['host']);

        $pendingRequest = Http::withHeaders($headers);

        if ($definition->streaming) {
            $pendingRequest = $pendingRequest->withOptions(['stream' => true]);
        }

        if ($definition->timeoutSeconds !== null) {
            $pendingRequest = $pendingRequest->timeout($definition->timeoutSeconds);
        }

        $response = $pendingRequest->send(
            $request->method(),
            $url,
            ['body' => $request->getContent()],
        );

        if (! $definition->streaming) {
            return response(
                $response->body(),
                $response->status(),
                ['Content-Type' => $response->header('Content-Type') ?: 'application/json'],
            );
        }

        return response()->stream(
            function () use ($response): void {
                $body = $response->toPsrResponse()->getBody();

                while (! $body->eof()) {
                    echo $body->read(8192);
                    flush();
                }
            },
            $response->status(),
            $response->headers(),
        );
    }
}
