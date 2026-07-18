<?php

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Actions\ApiKeys\RotateApiKey;
use App\Filament\Resources\ApiKeys\ApiKeyResource;
use App\Models\ApiKey;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditApiKey extends EditRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        /** @var ApiKey $record */
        $record = $this->getRecord();

        return [
            Action::make('rotate')
                ->label('Rotate Key')
                ->requiresConfirmation()
                ->action(function () use ($record): void {
                    $result = app(RotateApiKey::class)->handle($record);

                    Notification::make()
                        ->title('API key rotated')
                        ->body("Copy now (shown once): {$result->plainTextKey}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
            Action::make('revoke')
                ->label('Revoke Key')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $record->revoked_at === null)
                ->action(function (): void {
                    $this->getRecord()->update(['revoked_at' => now()]);

                    Notification::make()
                        ->title('API key revoked')
                        ->warning()
                        ->send();
                }),
            Action::make('reactivate')
                ->label('Reactivate Key')
                ->visible(fn (): bool => $record->revoked_at !== null)
                ->action(function (): void {
                    $this->getRecord()->update(['revoked_at' => null]);

                    Notification::make()
                        ->title('API key reactivated')
                        ->success()
                        ->send();
                }),
        ];
    }
}
