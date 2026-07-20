<?php

use App\Models\LlmModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Playground')] class extends Component
{
    public string $selectedModel = '';

    public string $systemPrompt = '';

    public string $userMessage = '';

    public string $apiKeyInput = '';

    public float $temperature = 0.7;

    public int $maxTokens = 1024;

    public bool $streaming = false;

    public ?string $response = null;

    public ?string $error = null;

    public ?int $statusCode = null;

    public ?int $latencyMs = null;

    public ?array $usage = null;

    #[Computed]
    public function availableModels(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        return LlmModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->filter(fn (LlmModel $model) => $model->provider
                && $user->hasFeature('model:'.$model->external_model_id)
                && $user->hasFeature('provider:'.$model->provider->slug))
            ->map(fn (LlmModel $model) => [
                'value' => $model->external_model_id,
                'label' => $model->name,
            ])
            ->values()
            ->toArray();
    }

    public function send(): void
    {
        $this->validate([
            'selectedModel' => ['required', 'string'],
            'apiKeyInput' => ['required', 'string'],
            'userMessage' => ['required', 'string'],
            'systemPrompt' => ['nullable', 'string'],
            'temperature' => ['numeric', 'min:0', 'max:2'],
            'maxTokens' => ['integer', 'min:1', 'max:32768'],
        ]);

        $this->response = null;
        $this->error = null;
        $this->statusCode = null;
        $this->latencyMs = null;
        $this->usage = null;

        $messages = [];

        if ($this->systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $this->systemPrompt];
        }

        $messages[] = ['role' => 'user', 'content' => $this->userMessage];

        $payload = [
            'model' => $this->selectedModel,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'stream' => false,
        ];

        $startedAt = microtime(true);

        try {
            $response = Http::withToken($this->apiKeyInput)
                ->timeout(120)
                ->post(url('/api/v1/chat/completions'), $payload);

            $this->statusCode = $response->status();
            $this->latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $body = $response->json();

            if ($response->successful()) {
                $this->response = data_get($body, 'choices.0.message.content', '');
                $this->usage = data_get($body, 'usage');
            } else {
                $this->error = data_get($body, 'error.message', 'Request failed with status '.$response->status());
            }
        } catch (\Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function render()
    {
        return $this->view();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Playground') }}</flux:heading>
            <flux:subheading>{{ __('Test API calls directly from the browser') }}</flux:subheading>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Input Panel --}}
            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Model') }}</flux:label>
                    <flux:select wire:model="selectedModel" data-test="playground-model-select">
                        <flux:select.option value="">{{ __('Select a model...') }}</flux:select.option>
                        @foreach ($this->availableModels as $model)
                            <flux:select.option value="{{ $model['value'] }}">{{ $model['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('System Prompt') }}</flux:label>
                    <flux:textarea wire:model="systemPrompt" rows="3" placeholder="You are a helpful assistant..." data-test="playground-system-prompt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Message') }}</flux:label>
                    <flux:textarea wire:model="userMessage" rows="6" placeholder="Type your message here..." required data-test="playground-message" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('API Key') }}</flux:label>
                    <flux:input wire:model="apiKeyInput" type="password" placeholder="Paste one of your API keys" data-test="playground-api-key" />
                    <flux:description>{{ __('Used only for this request and never stored.') }}</flux:description>
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Temperature') }}: <span x-text="{{ $temperature }}"></span></flux:label>
                        <input type="range" wire:model="temperature" min="0" max="2" step="0.1" class="w-full" data-test="playground-temperature" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Max Tokens') }}</flux:label>
                        <flux:input type="number" wire:model="maxTokens" min="1" max="32768" data-test="playground-max-tokens" />
                    </flux:field>
                </div>

                <flux:button variant="primary" wire:click="send" wire:loading.attr="disabled" data-test="playground-send">
                    <span wire:loading.remove wire:target="send">{{ __('Send') }}</span>
                    <span wire:loading wire:target="send">{{ __('Sending...') }}</span>
                </flux:button>
            </div>

            {{-- Output Panel --}}
            <div class="space-y-4">
                @if ($error)
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-900/20">
                        <div class="flex items-center gap-2">
                            <flux:icon name="exclamation-triangle" class="size-5 text-red-500" />
                            <span class="font-medium text-red-700 dark:text-red-300">{{ __('Error') }}</span>
                            @if ($statusCode)
                                <flux:badge color="red" size="sm">HTTP {{ $statusCode }}</flux:badge>
                            @endif
                        </div>
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                    </div>
                @endif

                @if ($response)
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-3 flex items-center justify-between">
                            <flux:heading level="3">{{ __('Response') }}</flux:heading>
                            @if ($latencyMs)
                                <flux:badge color="zinc" size="sm">{{ $latencyMs }}ms</flux:badge>
                            @endif
                        </div>
                        <div class="whitespace-pre-wrap break-words text-sm text-zinc-700 dark:text-zinc-300" data-test="playground-response">{{ $response }}</div>

                        @if ($usage)
                            <div class="mt-4 grid grid-cols-3 gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                <div class="text-center">
                                    <div class="text-xs text-zinc-500">{{ __('Input') }}</div>
                                    <div class="font-mono text-sm">{{ $usage['prompt_tokens'] ?? 0 }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs text-zinc-500">{{ __('Output') }}</div>
                                    <div class="font-mono text-sm">{{ $usage['completion_tokens'] ?? 0 }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs text-zinc-500">{{ __('Total') }}</div>
                                    <div class="font-mono text-sm">{{ $usage['total_tokens'] ?? 0 }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if (! $response && ! $error)
                    <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700">
                        <flux:icon name="chat-bubble-left-right" class="size-12 text-zinc-400" />
                        <flux:heading level="3" class="mt-4">{{ __('No response yet') }}</flux:heading>
                        <flux:subheading class="mt-1">{{ __('Send a message to see the response here.') }}</flux:subheading>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
