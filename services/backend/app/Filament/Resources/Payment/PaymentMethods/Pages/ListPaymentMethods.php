<?php

namespace App\Filament\Resources\Payment\PaymentMethods\Pages;

use App\Filament\Resources\Payment\PaymentMethods\PaymentMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentMethods extends ListRecords
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin_sv/payment_method_resource.navigation_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/payment_method_resource.navigation_label');
    }
}
