<?php

namespace App\Actions\Usage;

use App\Actions\Billing\DebitTeamWallet;
use App\Actions\Billing\ResolveModelPricing;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\ApiKey;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\Team;
use App\Models\UsageLedger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecordApiRequestUsage
{
    public function __construct(
        private readonly EnforceTeamTokenQuota $enforceTeamTokenQuota,
        private readonly CheckQuotaThresholds $checkQuotaThresholds,
        private readonly DebitTeamWallet $debitTeamWallet,
        private readonly ResolveModelPricing $resolveModelPricing,
    ) {
        //
    }

    public function handle(
        Team $team,
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
        ?ApiKey $apiKey = null,
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
            $team,
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
            $apiKey,
            $provider,
            $llmModel,
            $requestedAt,
            $enforceQuota,
        ) {
            if ($enforceQuota && $tokenTotal > 0) {
                $this->enforceTeamTokenQuota->handle($team, $tokenTotal, $requestedAt);
            }

            $requestLog = RequestLog::create([
                'trace_id' => $traceId,
                'team_id' => $team->id,
                'api_key_id' => $apiKey?->id,
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

            $isError = ($statusCode !== null && $statusCode >= 400) || ! empty($errorCode);

            $this->incrementLedger(
                team: $team,
                apiKey: $apiKey,
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
                team: $team,
                apiKey: $apiKey,
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
                team: $team,
                apiKey: $apiKey,
                provider: $provider,
                llmModel: $llmModel,
                bucketType: 'month',
                bucketDate: $requestedAt->copy()->startOfMonth()->toDateString(),
                tokenInput: $tokenInput,
                tokenOutput: $tokenOutput,
                tokenTotal: $tokenTotal,
                isError: $isError,
            );

            return $requestLog;
        });

        // Real-time wallet debit. Only charge for successful requests with
        // actual token consumption — errors/timeouts are free for the customer
        // (the operator eats the upstream cost, which is the standard model).
        // For streaming responses the bytes have already been flushed to the
        // client by the time we reach here, so a failed debit must not raise
        // — otherwise the framework turns it into a 500 and corrupts the
        // stream. Log the shortfall instead and let the monthly invoice
        // reconciliation collect the gap for post-paid teams.
        if ($tokenTotal > 0 && $llmModel && $statusCode !== null && $statusCode < 400 && empty($errorCode)) {
            try {
                $this->debitTeamWallet->handle(
                    team: $team,
                    amountCents: $this->resolveModelPricing->chargeCents($llmModel, $tokenInput, $tokenOutput),
                    description: sprintf(
                        '%s %s — %d in / %d out tokens',
                        $protocol,
                        $endpoint,
                        $tokenInput,
                        $tokenOutput,
                    ),
                    source: $requestLog,
                    referenceId: 'reqlog:'.$requestLog->id,
                    metadata: [
                        'trace_id' => $traceId,
                        'model_id' => $llmModel->id,
                        'model_external_id' => $llmModel->external_model_id,
                        'provider_id' => $provider?->id,
                        'token_input' => $tokenInput,
                        'token_output' => $tokenOutput,
                        'cost_cents' => $this->resolveModelPricing->costCents($llmModel, $tokenInput, $tokenOutput),
                    ],
                );
            } catch (InsufficientWalletBalanceException $exception) {
                Log::warning('gateway.wallet.debit_failed', [
                    'team_id' => $team->id,
                    'request_log_id' => $requestLog->id,
                    'trace_id' => $traceId,
                    'amount_cents' => $exception->requestedCents,
                    'available_cents' => $exception->availableCents,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        // Threshold alerts run after the transaction commits so the latest
        // ledger totals are visible. Only meaningful when tokens were consumed.
        if ($tokenTotal > 0) {
            $this->checkQuotaThresholds->handle($team, $requestedAt);
        }

        return $requestLog;
    }

    protected function incrementLedger(
        Team $team,
        ?ApiKey $apiKey,
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
            ->where('team_id', $team->id)
            ->where('api_key_id', $apiKey?->id)
            ->where('llm_provider_id', $provider?->id)
            ->where('llm_model_id', $llmModel?->id)
            ->where('bucket_type', $bucketType)
            ->whereDate('bucket_date', $bucketDate)
            ->lockForUpdate();

        $ledger = $query->first();

        if (! $ledger) {
            UsageLedger::create([
                'team_id' => $team->id,
                'api_key_id' => $apiKey?->id,
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
