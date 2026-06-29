<?php

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Actions\ApiKeys\GenerateApiKey;
use App\Data\GeneratedApiKey;
use App\Filament\Resources\ApiKeys\ApiKeyResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected ?string $generatedApiKey = null;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $team = $user?->currentTeam;

        if (! $team || ! $user) {
            throw ValidationException::withMessages([
                'name' => __('A current team is required before creating API keys.'),
            ]);
        }

        /** @var GeneratedApiKey $result */
        $result = app(GenerateApiKey::class)->handle(
            team: $team,
            name: (string) $data['name'],
            expiresAt: $data['expires_at'] ?? null,
            createdBy: $user->id,
        );

        $this->generatedApiKey = $result->plainTextKey;

        return $result->apiKey;
    }

    protected function afterCreate(): void
    {
        if (! $this->generatedApiKey) {
            return;
        }

        Notification::make()
            ->title('API key generated')
            ->body("Copy now (shown once): {$this->generatedApiKey}")
            ->success()
            ->persistent()
            ->send();
    }
}
