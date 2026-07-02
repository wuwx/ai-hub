<?php

namespace App\Http\Controllers;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\UsageLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DataExportController extends Controller
{
    public function exportUsage(Request $request, Team $current_team): Response
    {
        $this->authorizeExport($current_team, TeamPermission::ViewUsage);

        $cycle = $this->billingCycle($current_team);
        $startDate = Carbon::parse($cycle['start'])->startOfDay();
        $endDate = Carbon::parse($cycle['end'])->endOfDay();

        $rows = UsageLedger::query()
            ->leftJoin(
                'llm_models',
                'llm_models.id',
                '=',
                'usage_ledgers.llm_model_id',
            )
            ->leftJoin(
                'llm_providers',
                'llm_providers.id',
                '=',
                'usage_ledgers.llm_provider_id',
            )
            ->where('usage_ledgers.team_id', $current_team->id)
            ->where('usage_ledgers.bucket_type', 'day')
            ->whereBetween('usage_ledgers.bucket_date', [$startDate, $endDate])
            ->groupBy(
                'usage_ledgers.bucket_date',
                'llm_models.name',
                'llm_providers.name',
            )
            ->orderBy('usage_ledgers.bucket_date')
            ->selectRaw(
                '
                usage_ledgers.bucket_date,
                COALESCE(llm_models.name, "unknown") as model_name,
                COALESCE(llm_providers.name, "unknown") as provider_name,
                SUM(usage_ledgers.token_input) as token_input,
                SUM(usage_ledgers.token_output) as token_output,
                SUM(usage_ledgers.token_total) as token_total,
                SUM(usage_ledgers.request_count) as request_count,
                SUM(usage_ledgers.error_count) as error_count
            ',
            )
            ->get();

        $csv = $this->buildCsv(
            [
                'Date',
                'Model',
                'Provider',
                'Input Tokens',
                'Output Tokens',
                'Total Tokens',
                'Requests',
                'Errors',
            ],
            $rows
                ->map(
                    fn ($row) => [
                        $row->bucket_date,
                        $row->model_name,
                        $row->provider_name,
                        $row->token_input,
                        $row->token_output,
                        $row->token_total,
                        $row->request_count,
                        $row->error_count,
                    ],
                )
                ->toArray(),
        );

        return $this->csvResponse(
            $csv,
            'usage-'.now()->format('Y-m-d').'.csv',
        );
    }

    protected function authorizeExport(
        Team $team,
        TeamPermission $permission,
    ): void {
        $user = Auth::user();

        abort_if(! $user || ! $user->belongsToTeam($team), 403);
        abort_if(! $user->hasTeamPermission($team, $permission), 403);
    }

    /**
     * @return array{start: string, end: string}
     */
    protected function billingCycle(Team $team): array
    {
        $subscription = $team->subscription();

        if (
            $subscription &&
            $subscription->valid() &&
            $subscription->created_at
        ) {
            $start = $subscription->created_at->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ];
        }

        $now = now();

        return [
            'start' => $now->startOfMonth()->toDateString(),
            'end' => $now->endOfMonth()->toDateString(),
        ];
    }

    /**
     * @param  array<string>  $headers
     * @param  array<array<string|int|null>>  $rows
     */
    protected function buildCsv(array $headers, array $rows): string
    {
        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');

            fputcsv($file, $headers);

            foreach ($rows as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    protected function csvResponse(string $csv, string $filename): Response
    {
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
