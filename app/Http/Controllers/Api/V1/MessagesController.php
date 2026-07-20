<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Gateway\GatewayRequestProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MessagesController extends Controller
{
    public function __construct(private readonly GatewayRequestProcessor $gatewayRequestProcessor)
    {
        //
    }

    public function __invoke(Request $request): Response
    {
        return $this->gatewayRequestProcessor->handle($request, 'anthropic', '/v1/messages');
    }
}
