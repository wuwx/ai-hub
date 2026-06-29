<?php

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Actions\ApiKeys\RotateApiKey;
use App\Filament\Resources\ApiKeys\ApiKeyResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditApiKey extends EditRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rotate')
                ->label('Rotate Key')
                ->requiresConfirmation()
                ->action(function (): void {
                    $result = app(RotateApiKey::class)->handle($this->getRecord());

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
                ->visible(fn (): bool => $this->getRecord()->revoked_at === null)
                ->action(function (): void {
                    $this->getRecord()->update(['revoked_at' => now()]);

                    Notification::make()
                        ->title('API key revoked')
                        ->warning()
                        ->send();
                }),
            Action::make('reactivate')
                ->label('Reactivate Key')
                ->visible(fn (): bool => $this->getRecord()->revoked_at !== null)
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
