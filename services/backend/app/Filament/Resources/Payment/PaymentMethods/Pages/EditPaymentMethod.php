<?php

namespace App\Filament\Resources\Payment\PaymentMethods\Pages;

use App\Filament\Resources\Payment\PaymentMethods\PaymentMethodResource;
use App\Models\Payment\PaymentMethod;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPaymentMethod extends EditRecord
{
    protected static string $resource = PaymentMethodResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_enabled'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false)
                ->before(function (DeleteAction $action, PaymentMethod $record): void {
                    if ($record->hasPayments()) {
                        Notification::make()
                            ->title('Нельзя удалить метод оплаты')
                            ->body('По этому методу уже есть платежи в системе. Отключите «Активен на сайте» вместо удаления.')
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}
