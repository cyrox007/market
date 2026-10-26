<?php
namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

/** Old bookmarks send operators to the workspace; read-only permissions stay read-only. */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        if (OrderResource::canEdit($this->record)) {
            $this->redirect(OrderResource::getUrl('edit', ['record' => $this->record]));
        }
    }

    protected function getHeaderActions(): array { return []; }
    public function getTitle(): string { return 'Заказ '.$this->record->number; }
}