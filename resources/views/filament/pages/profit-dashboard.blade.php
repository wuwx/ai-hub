<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary Stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Revenue</div>
                <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">
                    ${{ number_format($totals['revenue_cents'] / 100, 2) }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Cost</div>
                <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">
                    ${{ number_format($totals['cost_cents'] / 100, 2) }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Profit</div>
                <div class="mt-2 text-3xl font-bold {{ $totals['profit_cents'] >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}">
                    ${{ number_format($totals['profit_cents'] / 100, 2) }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Margin</div>
                <div class="mt-2 text-3xl font-bold {{ $totals['margin_pct'] >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $totals['margin_pct'] }}%
                </div>
            </div>
        </div>

        {{-- Today / This Month --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Today's Revenue</div>
                <div class="mt-2 text-2xl font-bold">
                    ${{ number_format($totals['today_revenue_cents'] / 100, 2) }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">This Month's Revenue</div>
                <div class="mt-2 text-2xl font-bold">
                    ${{ number_format($totals['month_revenue_cents'] / 100, 2) }}
                </div>
            </div>
        </div>

        {{-- Profit by Model --}}
        <div class="rounded-xl bg-white shadow-sm dark:bg-zinc-800">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h3 class="text-lg font-semibold">Profit by Model</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="px-6 py-3">Model</th>
                            <th class="px-6 py-3 text-right">Requests</th>
                            <th class="px-6 py-3 text-right">Revenue</th>
                            <th class="px-6 py-3 text-right">Cost</th>
                            <th class="px-6 py-3 text-right">Profit</th>
                            <th class="px-6 py-3 text-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byModel as $row)
                            <tr class="border-b border-zinc-100 text-sm dark:border-zinc-700/50">
                                <td class="px-6 py-3 font-mono">{{ $row['model'] }}</td>
                                <td class="px-6 py-3 text-right">{{ number_format($row['request_count']) }}</td>
                                <td class="px-6 py-3 text-right text-green-600 dark:text-green-400">${{ number_format($row['revenue_cents'] / 100, 2) }}</td>
                                <td class="px-6 py-3 text-right text-red-600 dark:text-red-400">${{ number_format($row['cost_cents'] / 100, 2) }}</td>
                                <td class="px-6 py-3 text-right font-semibold {{ $row['profit_cents'] >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}">
                                    ${{ number_format($row['profit_cents'] / 100, 2) }}
                                </td>
                                <td class="px-6 py-3 text-right">{{ $row['margin_pct'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-zinc-400">No data yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Profit by Team --}}
        <div class="rounded-xl bg-white shadow-sm dark:bg-zinc-800">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h3 class="text-lg font-semibold">Profit by Team</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="px-6 py-3">Team</th>
                            <th class="px-6 py-3 text-right">Requests</th>
                            <th class="px-6 py-3 text-right">Revenue</th>
                            <th class="px-6 py-3 text-right">Cost</th>
                            <th class="px-6 py-3 text-right">Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byTeam as $row)
                            <tr class="border-b border-zinc-100 text-sm dark:border-zinc-700/50">
                                <td class="px-6 py-3">{{ $row['team_name'] }}</td>
                                <td class="px-6 py-3 text-right">{{ number_format($row['request_count']) }}</td>
                                <td class="px-6 py-3 text-right text-green-600 dark:text-green-400">${{ number_format($row['revenue_cents'] / 100, 2) }}</td>
                                <td class="px-6 py-3 text-right text-red-600 dark:text-red-400">${{ number_format($row['cost_cents'] / 100, 2) }}</td>
                                <td class="px-6 py-3 text-right font-semibold {{ $row['profit_cents'] >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}">
                                    ${{ number_format($row['profit_cents'] / 100, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-zinc-400">No data yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
