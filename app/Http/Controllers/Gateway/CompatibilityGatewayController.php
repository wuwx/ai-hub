<?php

namespace App\Http\Controllers\Gateway;

use App\Actions\Gateway\GatewayRequestProcessor;
use App\Http\Controllers\Controller;
use App\Models\LlmModel;
use App\Models\Team;
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
     * List models the authenticated team is entitled to use.
     *
     * Implements the OpenAI-compatible `GET /v1/models` endpoint so that
     * SDKs and clients (Cursor, Continue, LangChain, etc.) can discover
     * available models during initialization.
     */
    public function listModels(Request $request): Response
    {
        /** @var Team|null $team */
        $team = $request->attributes->get('gateway.team');
        $traceId = (string) ($request->attributes->get('gateway.trace_id') ?: Str::uuid()->toString());

        $models = $this->resolveModelsForTeam($team);

        $data = $models->map(fn (LlmModel $model) => [
            'id' => $model->external_model_id,
            'object' => 'model',
            'created' => $model->created_at?->timestamp ?? time(),
            'owned_by' => $model->provider?->slug ?? 'gateway',
        ])->values()->all();

        return response()->json([
            'object' => 'list',
            'data' => $data,
        ], 200, [
            'X-Trace-Id' => $traceId,
        ]);
    }

    /**
     * @return Collection<int, LlmModel>
     */
    protected function resolveModelsForTeam(?Team $team): Collection
    {
        if (! $team) {
            return collect();
        }

        return LlmModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('entitlements', function ($query) use ($team) {
                $query->where('team_id', $team->id)
                    ->where('is_enabled', true);
            })
            ->whereHas('provider', function ($query) use ($team) {
                $query->where('is_active', true)
                    ->whereHas('entitlements', function ($entitlements) use ($team) {
                        $entitlements->where('team_id', $team->id)
                            ->where('is_enabled', true);
                    });
            })
            ->orderBy('name')
            ->get();
    }
}
