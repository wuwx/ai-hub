<?php

namespace App\Http\Controllers\Gateway;

use App\Actions\Gateway\GatewayRequestProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompatibilityGatewayController extends Controller
{
    public function __construct(private readonly GatewayRequestProcessor $gatewayRequestProcessor)
    {
        //
    }

    public function openAiChatCompletions(Request $request): Response
    {
        return $this->gatewayRequestProcessor->handle($request, 'openai', '/v1/chat/completions');
    }

    public function openAiResponses(Request $request): Response
    {
        return $this->gatewayRequestProcessor->handle($request, 'openai', '/v1/responses');
    }

    public function anthropicMessages(Request $request): Response
    {
        return $this->gatewayRequestProcessor->handle($request, 'anthropic', '/v1/messages');
    }
}
