<?php

namespace App\Actions\Usage;

use App\Exceptions\QuotaExceededException;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\UsageLedger;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class RecordApiRequestUsage
{
    /**
     * Token quota features consumed per request, mapped to their period name.
     *
     * @var array<string, string>
     */
    private const PERIODS = [
        'daily-tokens' => 'daily',
        'weekly-tokens' => 'weekly',
        'monthly-tokens' => 'monthly',
    ];

    public function __construct(
        private readonly CheckQuotaThresholds $checkQuotaThresholds,
    ) {
        //
    }

    public function handle(
        User $user,
        string $protocol,
        string $endpoint,
        string $httpMethod = 'POST',
        int $tokenInput = 0,
        int $tokenOutput = 0,
        bool $isStreaming = false,
        int $toolCallsCount = 0,
        ?int $statusCode = null,
        ?int $latencyMs = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $traceId = null,
        ?PersonalAccessToken $token = null,
        ?LlmProvider $provider = null,
        ?LlmModel $llmModel = null,
        ?CarbonInterface $requestedAt = null,
        bool $enforceQuota = true,
    ): RequestLog {
        $requestedAt ??= now();

        $tokenInput = max(0, $tokenInput);
        $tokenOutput = max(0, $tokenOutput);
        $tokenTotal = $tokenInput + $tokenOutput;

        $requestLog = DB::transaction(function () use (
            $user,
            $protocol,
            $endpoint,
            $httpMethod,
            $tokenInput,
            $tokenOutput,
            $tokenTotal,
            $isStreaming,
            $toolCallsCount,
            $statusCode,
            $latencyMs,
            $errorCode,
            $errorMessage,
            $traceId,
            $token,
            $provider,
            $llmModel,
            $requestedAt,
            $enforceQuota,
        ) {
            if ($enforceQuota && $tokenTotal > 0) {
                $this->consumeQuota($user, $tokenTotal);
            }

            $requestLog = RequestLog::create([
                'trace_id' => $traceId,
                'user_id' => $user->id,
                'token_id' => $token?->id,
                'llm_provider_id' => $provider?->id,
                'llm_model_id' => $llmModel?->id,
                'protocol' => $protocol,
                'endpoint' => $endpoint,
                'http_method' => strtoupper($httpMethod),
                'is_streaming' => $isStreaming,
                'tool_calls_count' => max(0, $toolCallsCount),
                'status_code' => $statusCode,
                'token_input' => $tokenInput,
                'token_output' => $tokenOutput,
                'token_total' => $tokenTotal,
                'latency_ms' => $latencyMs,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'requested_at' => $requestedAt,
            ]);

            $isError =
                ($statusCode !== null && $statusCode >= 400) ||
                ! empty($errorCode);

            $this->incrementLedger(
                user: $user,
                token: $token,
                provider: $provider,
                llmModel: $llmModel,
                bucketType: 'day',
                bucketDate: $requestedAt->copy()->toDateString(),
                tokenInput: $tokenInput,
                tokenOutput: $tokenOutput,
                tokenTotal: $tokenTotal,
                isError: $isError,
            );

            $this->incrementLedger(
                user: $user,
                token: $token,
                provider: $provider,
                llmModel: $llmModel,
                bucketType: 'week',
                bucketDate: $requestedAt->copy()->startOfWeek()->toDateString(),
                tokenInput: $tokenInput,
                tokenOutput: $tokenOutput,
                tokenTotal: $tokenTotal,
                isError: $isError,
            );

            $this->incrementLedger(
                user: $user,
                token: $token,
                provider: $provider,
                llmModel: $llmModel,
                bucketType: 'month',
                bucketDate: $requestedAt
                    ->copy()
                    ->startOfMonth()
                    ->toDateString(),
                tokenInput: $tokenInput,
                tokenOutput: $tokenOutput,
                tokenTotal: $tokenTotal,
                isError: $isError,
            );

            return $requestLog;
        });

        // Threshold alerts run after the transaction commits so the latest
        // ledger totals are visible. Only meaningful when tokens were consumed.
        if ($tokenTotal > 0) {
            $this->checkQuotaThresholds->handle($user, $requestedAt);
        }

        return $requestLog;
    }

    /**
     * Atomically check and consume token quota across all periods.
     *
     * Delegates to Subscriptionify on the user (which is the subscribable).
     * Only runs when the user has an active quota subscription; otherwise
     * quota is unlimited (no policy).
     */
    protected function consumeQuota(User $user, int $tokenTotal): void
    {
        foreach (self::PERIODS as $slug => $period) {
            if (! $user->hasFeature($slug) || $user->isUnlimitedUsage($slug)) {
                continue;
            }

            if ($user->tryConsume($slug, $tokenTotal)) {
                continue;
            }

            $info = $user->featureInfo($slug);

            throw new QuotaExceededException(
                period: $period,
                limit: (int) $info->limit,
                used: (int) $info->used,
                requested: $tokenTotal,
            );
        }
    }

    protected function incrementLedger(
        User $user,
        ?PersonalAccessToken $token,
        ?LlmProvider $provider,
        ?LlmModel $llmModel,
        string $bucketType,
        string $bucketDate,
        int $tokenInput,
        int $tokenOutput,
        int $tokenTotal,
        bool $isError,
    ): void {
        $query = UsageLedger::query()
            ->where('user_id', $user->id)
            ->where('token_id', $token?->id)
            ->where('llm_provider_id', $provider?->id)
            ->where('llm_model_id', $llmModel?->id)
            ->where('bucket_type', $bucketType)
            ->whereDate('bucket_date', $bucketDate)
            ->lockForUpdate();

        $ledger = $query->first();

        if (! $ledger) {
            UsageLedger::create([
                'user_id' => $user->id,
                'token_id' => $token?->id,
                'llm_provider_id' => $provider?->id,
                'llm_model_id' => $llmModel?->id,
                'bucket_date' => $bucketDate,
                'bucket_type' => $bucketType,
                'token_input' => $tokenInput,
                'token_output' => $tokenOutput,
                'token_total' => $tokenTotal,
                'request_count' => 1,
                'error_count' => $isError ? 1 : 0,
            ]);

            return;
        }

        $ledger->update([
            'token_input' => $ledger->token_input + $tokenInput,
            'token_output' => $ledger->token_output + $tokenOutput,
            'token_total' => $ledger->token_total + $tokenTotal,
            'request_count' => $ledger->request_count + 1,
            'error_count' => $ledger->error_count + ($isError ? 1 : 0),
        ]);
    }
}
