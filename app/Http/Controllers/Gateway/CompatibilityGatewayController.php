<?php

namespace App\Http\Controllers\Gateway;

use App\Actions\Gateway\GatewayRequestProcessor;
use App\Http\Controllers\Controller;
use App\Models\LlmModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

    public function openAiEmbeddings(Request $request): Response
    {
        return $this->gatewayRequestProcessor->handleEmbeddings($request);
    }

    public function openAiResponses(Request $request): Response
    {
        return $this->gatewayRequestProcessor->handle($request, 'openai', '/v1/responses');
    }

    public function anthropicMessages(Request $request): Response
    {
        return $this->gatewayRequestProcessor->handle($request, 'anthropic', '/v1/messages');
    }

    /**
     * List models the authenticated user is entitled to use.
     *
     * Implements the OpenAI-compatible `GET /v1/models` endpoint so that
     * SDKs and clients (Cursor, Continue, LangChain, etc.) can discover
     * available models during initialization.
     */
    public function listModels(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->attributes->get('gateway.user');
        $traceId = (string) ($request->attributes->get('gateway.trace_id') ?: Str::uuid()->toString());

        $models = $this->resolveModelsForUser($user);

        $data = $models->map(fn (LlmModel $model) => [
            'id' => $model->external_model_id,
            'object' => 'model',
            'created' => $model->created_at->timestamp,
            'owned_by' => $model->provider->slug ?? 'gateway',
        ])->values()->all();

        return response()->json([
            'object' => 'list',
            'data' => $data,
        ], 200, [
            'X-Trace-Id' => $traceId,
        ]);
    }

    /**
     * Resolve models available to the user based on their current plan.
     *
     * @return Collection<int, LlmModel>
     */
    /**
     * Resolve models available to the user based on their current plan.
     *
     * @return Collection<int, LlmModel>
     */
    protected function resolveModelsForUser(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        return LlmModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->filter(function (LlmModel $model) use ($user) {
                if (! $model->provider) {
                    return false;
                }

                return $user->hasFeature('model:'.$model->external_model_id)
                    && $user->hasFeature('provider:'.$model->provider->slug);
            })
            ->values();
    }
}
