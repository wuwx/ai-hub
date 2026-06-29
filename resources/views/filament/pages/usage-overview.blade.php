<x-filament-panels::page>
    <x-filament::section heading="Top Models This Month">
        <div class="space-y-3">
            @forelse ($topModels as $topModel)
                <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                    <span class="text-sm font-medium text-gray-900">{{ $topModel['name'] }}</span>
                    <span class="text-sm text-gray-600">{{ number_format($topModel['tokens']) }} tokens</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No usage recorded yet for this month.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
