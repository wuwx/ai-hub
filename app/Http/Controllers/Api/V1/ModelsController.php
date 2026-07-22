<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
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
    public function __invoke(): Response
    {
        $models = AiModel::query()
            ->with('aiProvider')
            ->where('is_active', true)
            ->whereHas('aiProvider', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();

        $data = $models->map(fn (AiModel $model) => [
            'id' => $model->external_model_id,
            'object' => 'model',
            'created' => $model->created_at->timestamp,
            'owned_by' => $model->aiProvider->slug ?? 'gateway',
        ])->values()->all();

        return response()->json([
            'object' => 'list',
            'data' => $data,
        ], 200);
    }
}
