<?php

namespace App\Filament\Resources\Payment\PaymentMethods\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Payment\PaymentMethods\PaymentMethodResource;

class CreatePaymentMethod extends CreateRecord
{
    protected static string $resource = PaymentMethodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_enabled'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
