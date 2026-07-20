<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LlmModel;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class ModelsController extends Controller
{
    /**
     * List models available through the gateway.
     *
     * Implements the OpenAI-compatible `GET /v1/models` endpoint so that
     * SDKs and clients (Cursor, Continue, LangChain, etc.) can discover
     * available models during initialization. Entitlement gating was removed,
     * so every active model on an active provider is listed.
     */
    public function index(): Response
    {
        $models = $this->resolveModels();

        $data = $models->map(fn (LlmModel $model) => [
            'id' => $model->external_model_id,
            'object' => 'model',
            'created' => $model->created_at->timestamp,
            'owned_by' => $model->provider->slug ?? 'gateway',
        ])->values()->all();

        return response()->json([
            'object' => 'list',
            'data' => $data,
        ], 200);
    }

    /**
     * Resolve models available through the gateway.
     *
     * @return Collection<int, LlmModel>
     */
    protected function resolveModels(): Collection
    {
        return LlmModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();
    }
}
