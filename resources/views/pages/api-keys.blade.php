<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\ApiKeys\RotateApiKey;
use App\Actions\Audit\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API Keys')] class extends Component
{
    public string $newKeyName = '';

    public ?string $newKeyExpiresAt = null;

    public ?string $generatedPlainTextKey = null;

    public ?int $generatedKeyId = null;

    public ?string $rotatedPlainTextKey = null;

    public ?int $rotatedKeyId = null;

    #[Computed]
    public function canManage(): bool
    {
        return Auth::check();
    }

    #[Computed]
    public function apiKeys(): array
    {
        return Auth::user()
            ?->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'expires_at' => $token->expires_at?->toDateTimeString(),
                'is_active' => $token->expires_at === null || ! $token->expires_at->isPast(),
                'created_at' => $token->created_at->toDateTimeString(),
            ])
            ->toArray() ?? [];
    }

    public function createKey(): void
    {
        abort_unless($this->canManage, 403);

        $this->validate([
            'newKeyName' => ['required', 'string', 'max:255'],
            'newKeyExpiresAt' => ['nullable', 'date', 'after:now'],
        ]);

        $expiresAt = $this->newKeyExpiresAt
            ? \Illuminate\Support\Carbon::parse($this->newKeyExpiresAt)
            : null;

        $generated = app(GenerateApiKey::class)->handle(
            user: Auth::user(),
            name: $this->newKeyName,
            expiresAt: $expiresAt,
        );

        app(RecordAuditEvent::class)->handle(
            action: 'api_key.created',
            subject: $generated->token,
            properties: [
                'name' => $generated->token->name,
                'expires_at' => $generated->token->expires_at?->toDateTimeString(),
            ],
            actor: Auth::user(),
        );

        $this->generatedPlainTextKey = $generated->plainTextToken;
        $this->generatedKeyId = $generated->token->id;
        $this->newKeyName = '';
        $this->newKeyExpiresAt = null;

        $this->dispatch('api-key-created');
    }

    public function revokeKey(int $keyId): void
    {
        abort_unless($this->canManage, 403);

        $token = Auth::user()
            ?->tokens()
            ->where('id', $keyId)
            ->first();

        abort_unless($token, 404);

        $name = $token->name;

        $token->delete();

        app(RecordAuditEvent::class)->handle(
            action: 'api_key.revoked',
            subject: $token,
            properties: [
                'name' => $name,
            ],
            actor: Auth::user(),
        );

        $this->dispatch('api-key-revoked');

        \Flux\Flux::toast(variant: 'success', text: __('API key revoked.'));
    }

    public function rotateKey(int $keyId): void
    {
        abort_unless($this->canManage, 403);

        $token = Auth::user()
            ?->tokens()
            ->where('id', $keyId)
            ->first();

        abort_unless($token, 404);

        $generated = app(RotateApiKey::class)->handle($token);

        app(RecordAuditEvent::class)->handle(
            action: 'api_key.rotated',
            subject: $generated->token,
            properties: [
                'name' => $generated->token->name,
            ],
            actor: Auth::user(),
        );

        $this->rotatedPlainTextKey = $generated->plainTextToken;
        $this->rotatedKeyId = $generated->token->id;

        $this->dispatch('api-key-rotated');
    }

    public function dismissRotatedKey(): void
    {
        $this->rotatedPlainTextKey = null;
        $this->rotatedKeyId = null;
    }

    public function dismissGeneratedKey(): void
    {
        $this->generatedPlainTextKey = null;
        $this->generatedKeyId = null;
    }

    public function render()
    {
        return $this->view();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('API Keys') }}</flux:heading>
                <flux:subheading>{{ __('Manage API keys for accessing the LLM gateway') }}</flux:subheading>
            </div>

            @if ($this->canManage)
                <flux:modal.trigger name="create-api-key">
                    <flux:button variant="primary" icon="plus">
                        {{ __('Create API Key') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif
        </div>

        {{-- API Keys List --}}
        <div class="space-y-3">
            @forelse ($this->apiKeys as $key)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-4">
                        <div class="flex size-10 items-center justify-center rounded-full {{ $key['is_active'] ? 'bg-green-100 dark:bg-green-900/30' : 'bg-orange-100 dark:bg-orange-900/30' }}">
                            <flux:icon name="key" class="size-5 {{ $key['is_active'] ? 'text-green-600 dark:text-green-400' : 'text-orange-600 dark:text-orange-400' }}" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ $key['name'] }}</span>
                                @if (! $key['is_active'])
                                    <flux:badge color="orange" size="sm">{{ __('Expired') }}</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                @endif
                            </div>
                            <div class="mt-0.5 flex gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                                @if ($key['last_used_at'])
                                    <span>{{ __('Used :time', ['time' => $key['last_used_at']]) }}</span>
                                @else
                                    <span>{{ __('Never used') }}</span>
                                @endif
                                @if ($key['expires_at'])
                                    <span>{{ __('Expires :date', ['date' => $key['expires_at']]) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($this->canManage && $key['is_active'])
                        <div class="flex items-center gap-1">
                            <flux:modal.trigger name="rotate-key-{{ $key['id'] }}">
                                <flux:button variant="ghost" size="sm" icon="arrow-path" class="text-blue-500 hover:text-blue-700" />
                            </flux:modal.trigger>
                            <flux:modal.trigger name="revoke-key-{{ $key['id'] }}">
                                <flux:button variant="ghost" size="sm" icon="x-mark" class="text-red-500 hover:text-red-700" />
                            </flux:modal.trigger>
                        </div>
                    @endif
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-700">
                    <flux:icon name="key" class="size-12 text-zinc-400" />
                    <flux:heading level="3" class="mt-4">{{ __('No API keys yet') }}</flux:heading>
                    @if ($this->canManage)
                        <flux:subheading class="mt-1">{{ __('Create an API key to start using the LLM gateway.') }}</flux:subheading>
                    @endif
                </div>
            @endforelse
        </div>

        {{-- Create API Key Modal --}}
        @if ($this->canManage)
            <flux:modal name="create-api-key" class="md:w-96" :dismissible="false">
                @if ($generatedPlainTextKey)
                    {{-- Show generated key --}}
                    <div class="space-y-4">
                        <div>
                            <flux:heading level="2">{{ __('API Key Created') }}</flux:heading>
                            <flux:subheading>{{ __('Copy this key now. You won\'t be able to see it again.') }}</flux:subheading>
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-200/10 dark:bg-amber-900/20">
                            <flux:field>
                                <flux:label>{{ __('Your API Key') }}</flux:label>
                                <flux:input
                                    readonly
                                    value="{{ $generatedPlainTextKey }}"
                                    class="font-mono text-xs"
                                    x-data
                                    x-on:click="$el.select()"
                                />
                            </flux:field>
                        </div>

                        <div class="flex justify-end gap-2">
                            <flux:button
                                variant="primary"
                                wire:click="dismissGeneratedKey"
                                x-on:click="$flux.modal.close('create-api-key')"
                            >
                                {{ __('I\'ve copied it') }}
                            </flux:button>
                        </div>
                    </div>
                @else
                    {{-- Create form --}}
                    <form wire:submit="createKey" class="space-y-4">
                        <div>
                            <flux:heading level="2">{{ __('Create API Key') }}</flux:heading>
                            <flux:subheading>{{ __('Generate a new API key for your account.') }}</flux:subheading>
                        </div>

                        <flux:input
                            wire:model="newKeyName"
                            :label="__('Name')"
                            :placeholder="__('e.g. Production, Development')"
                            required
                        />

                        <flux:field>
                            <flux:label>{{ __('Expires at') }}</flux:label>
                            <flux:input
                                wire:model="newKeyExpiresAt"
                                type="datetime-local"
                            />
                            <flux:description>{{ __('Leave empty for no expiration.') }}</flux:description>
                        </flux:field>

                        <div class="flex justify-end gap-2">
                            <flux:button variant="ghost" x-on:click="$flux.modal.close('create-api-key')">
                                {{ __('Cancel') }}
                            </flux:button>
                            <flux:button variant="primary" type="submit">
                                {{ __('Create') }}
                            </flux:button>
                        </div>
                    </form>
                @endif
            </flux:modal>
        @endif

        {{-- Revoke Confirmation Modals --}}
        @if ($this->canManage)
            @foreach ($this->apiKeys as $key)
                @if ($key['is_active'])
                    <flux:modal name="revoke-key-{{ $key['id'] }}" class="md:w-96">
                        <div class="space-y-4">
                            <div>
                                <flux:heading level="2">{{ __('Revoke API Key') }}</flux:heading>
                                <flux:subheading>{{ __('Are you sure you want to revoke ":name"? Any application using this key will stop working immediately.', ['name' => $key['name']]) }}</flux:subheading>
                            </div>

                            <div class="flex justify-end gap-2">
                                <flux:button variant="ghost" x-on:click="$flux.modal.close('revoke-key-{{ $key['id'] }}')">
                                    {{ __('Cancel') }}
                                </flux:button>
                                <flux:button
                                    variant="danger"
                                    wire:click="revokeKey({{ $key['id'] }})"
                                    x-on:click="$flux.modal.close('revoke-key-{{ $key['id'] }}')"
                                >
                                    {{ __('Revoke') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:modal>

                    <flux:modal name="rotate-key-{{ $key['id'] }}" class="md:w-96">
                        @if ($rotatedPlainTextKey && $rotatedKeyId === $key['id'])
                            <div class="space-y-4">
                                <div>
                                    <flux:heading level="2">{{ __('Key Rotated') }}</flux:heading>
                                    <flux:subheading>{{ __('The old key has been replaced. Copy the new key now — you won\'t see it again.') }}</flux:subheading>
                                </div>

                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-200/10 dark:bg-amber-900/20">
                                    <flux:field>
                                        <flux:label>{{ __('New API Key') }}</flux:label>
                                        <flux:input
                                            readonly
                                            value="{{ $rotatedPlainTextKey }}"
                                            class="font-mono text-xs"
                                            x-data
                                            x-on:click="$el.select()"
                                        />
                                    </flux:field>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <flux:button
                                        variant="primary"
                                        wire:click="dismissRotatedKey"
                                        x-on:click="$flux.modal.close('rotate-key-{{ $key['id'] }}')"
                                    >
                                        {{ __('I\'ve copied it') }}
                                    </flux:button>
                                </div>
                            </div>
                        @else
                            <div class="space-y-4">
                                <div>
                                    <flux:heading level="2">{{ __('Rotate API Key') }}</flux:heading>
                                    <flux:subheading>{{ __('This will generate a new secret for ":name". The current key will stop working immediately. Use this if the key has been compromised.', ['name' => $key['name']]) }}</flux:subheading>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <flux:button variant="ghost" x-on:click="$flux.modal.close('rotate-key-{{ $key['id'] }}')">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                    <flux:button
                                        variant="primary"
                                        wire:click="rotateKey({{ $key['id'] }})"
                                    >
                                        {{ __('Rotate Key') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    </flux:modal>
                @endif
            @endforeach
        @endif
    </div>
</section>
