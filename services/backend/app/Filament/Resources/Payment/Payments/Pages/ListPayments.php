<?php

namespace App\Filament\Resources\Payment\Payments\Pages;

use App\Filament\Resources\Payment\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        return 'Платежи';
    }
}
