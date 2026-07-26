<?php

namespace App\Http\Gateway\Handlers;

use Wuwx\LaravelGateway\GatewayHandler;

/**
 * Forward inbound requests to a local httpbin-style upstream endpoint.
 *
 * Registered via:
 *   Gateway::get('/get', HttpbinGatewayHandler::class);
 */
class HttpbinGatewayHandler extends GatewayHandler
{
    /**
     * Build the gateway options pointing at the local httpbin instance.
     *
     * @return array{base_uri: string}
     */
    public function getOptions(): array
    {
        return ['base_uri' => 'http://localhost:32768/get'];
    }
}
