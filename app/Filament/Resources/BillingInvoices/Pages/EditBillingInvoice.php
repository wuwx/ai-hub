<?php

namespace App\Filament\Resources\BillingInvoices\Pages;

use App\Actions\Billing\CreateStripeCheckoutSession;
use App\Filament\Resources\BillingInvoices\BillingInvoiceResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use RuntimeException;

class EditBillingInvoice extends EditRecord
{
    protected static string $resource = BillingInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createStripeCheckout')
                ->label('Create Stripe Checkout')
                ->visible(fn (): bool => ! $this->getRecord()->isFinalized())
                ->action(function (): void {
                    try {
                        $invoice = app(CreateStripeCheckoutSession::class)->handle($this->getRecord());

                        Notification::make()
                            ->title('Stripe checkout created')
                            ->body((string) $invoice->payment_url)
                            ->success()
                            ->persistent()
                            ->send();

                        $this->refreshFormData(array_keys($this->getRecord()->getAttributes()));
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title('Unable to create checkout session')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
