<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Stores\StoreResource;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            return app(\App\Services\Address\PhysicalSiteSetup::class)->store($data);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            \Filament\Notifications\Notification::make()->danger()->title('Магазин не сохранён')
                ->body(implode(' ', \Illuminate\Support\Arr::flatten($exception->errors())))->send();
            throw \Illuminate\Validation\ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['data.'.$field => $messages])->all(),
            );
        }
    }
}
