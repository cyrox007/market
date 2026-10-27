<?php
namespace App\Filament\Resources\PrivatePriceLinks\Pages;
class EditPrivatePriceLink extends \App\Filament\Resources\Pages\EditRecord
{
    protected static string $resource = \App\Filament\Resources\PrivatePriceLinks\PrivatePriceLinkResource::class;
    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\Action::make('rotate_link')->label('Выпустить новую ссылку')->requiresConfirmation()
            ->modalDescription('Прежняя ссылка перестанет действовать. Новая останется доступна на этой странице.')
            ->action(function (): void {
                $this->record->rotateToken();
                $this->record->refresh();
                $this->fillForm();
                \Filament\Notifications\Notification::make()->title('Новая ссылка готова')->success()->send();
            })];
    }
}
