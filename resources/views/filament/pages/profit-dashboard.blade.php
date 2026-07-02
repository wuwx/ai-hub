<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary Stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Monthly Recurring Revenue</div>
                <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">
                    ${{ number_format($totals['revenue_cents'] / 100, 2) }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Infra Cost (This Month)</div>
                <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">
                    ${{ number_format($totals['cost_cents'] / 100, 2) }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Profit</div>
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

        {{-- User counts --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active Paid Subscriptions</div>
                <div class="mt-2 text-2xl font-bold">
                    {{ number_format($totals['active_paid_users']) }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-800">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Free Tier Users</div>
                <div class="mt-2 text-2xl font-bold">
                    {{ number_format($totals['free_users']) }}
                </div>
            </div>
        </div>

        {{-- Cost by Model --}}
        <div class="rounded-xl bg-white shadow-sm dark:bg-zinc-800">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h3 class="text-lg font-semibold">Infra Cost by Model (This Month)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="px-6 py-3">Model</th>
                            <th class="px-6 py-3 text-right">Requests</th>
                            <th class="px-6 py-3 text-right">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byModel as $row)
                            <tr class="border-b border-zinc-100 text-sm dark:border-zinc-700/50">
                                <td class="px-6 py-3 font-mono">{{ $row['model'] }}</td>
                                <td class="px-6 py-3 text-right">{{ number_format($row['request_count']) }}</td>
                                <td class="px-6 py-3 text-right text-red-600 dark:text-red-400">${{ number_format($row['cost_cents'] / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-zinc-400">No usage yet this month</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Profit by User --}}
        <div class="rounded-xl bg-white shadow-sm dark:bg-zinc-800">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h3 class="text-lg font-semibold">Profit by User (This Month)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="px-6 py-3">User</th>
                            <th class="px-6 py-3">Plan</th>
                            <th class="px-6 py-3 text-right">Requests</th>
                            <th class="px-6 py-3 text-right">Revenue</th>
                            <th class="px-6 py-3 text-right">Cost</th>
                            <th class="px-6 py-3 text-right">Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byUser as $row)
                            <tr class="border-b border-zinc-100 text-sm dark:border-zinc-700/50">
                                <td class="px-6 py-3">{{ $row['user_name'] }}</td>
                                <td class="px-6 py-3 capitalize">{{ $row['plan_code'] }}</td>
                                <td class="px-6 py-3 text-right">{{ number_format($row['request_count']) }}</td>
                                <td class="px-6 py-3 text-right text-green-600 dark:text-green-400">${{ number_format($row['revenue_cents'] / 100, 2) }}</td>
                                <td class="px-6 py-3 text-right text-red-600 dark:text-red-400">${{ number_format($row['cost_cents'] / 100, 2) }}</td>
                                <td class="px-6 py-3 text-right font-semibold {{ $row['profit_cents'] >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}">
                                    ${{ number_format($row['profit_cents'] / 100, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-zinc-400">No usage yet this month</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
