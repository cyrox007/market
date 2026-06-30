<?php

namespace App\Filament\Pages;

use App\Models\Settings\ProductStockSettings;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ProductStockSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Настройки остатков товаров';

    protected static ?string $title = 'Настройки остатков товаров';

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    protected static ?int $navigationSort = 100;

    public ?array $data = [];

    public function mount(): void
    {
        $settings = ProductStockSettings::getInstance();

        $this->data = [
            'stock_low_max' => $settings->stock_low_max ?? 1,
            'stock_medium_max' => $settings->stock_medium_max ?? 5,
            'stock_high_max' => $settings->stock_high_max ?? 10,
            'show_exact_above' => $settings->show_exact_above ?? 10,
            'warehouse_accounting_enabled' => (bool) ($settings->warehouse_accounting_enabled ?? false),
            'fallback_to_first_warehouse' => (bool) ($settings->fallback_to_first_warehouse ?? true),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Категории остатков')
                    ->description('Настройте границы для категорий отображения остатков товаров на сайте')
                    ->schema([
                        TextInput::make('stock_low_max')
                            ->label('Максимум для "мало"')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(1)
                            ->helperText('Остаток меньше этого значения будет отображаться как "мало". Например, если установлено 2, то остатки 0-1 будут "мало".'),

                        TextInput::make('stock_medium_max')
                            ->label('Максимум для "средне"')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(5)
                            ->helperText('Остаток от значения "мало" до этого значения будет отображаться как "средне". Например, если "мало" = 2 и "средне" = 5, то остатки 2-5 будут "средне".'),

                        TextInput::make('stock_high_max')
                            ->label('Максимум для "много"')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(10)
                            ->helperText('Остаток от значения "средне" до этого значения будет отображаться как "много". Например, если "средне" = 5 и "много" = 10, то остатки 6-10 будут "много".'),

                        TextInput::make('show_exact_above')
                            ->label('Показывать точное число если больше')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(10)
                            ->helperText('Если остаток больше этого значения, будет показываться точное число вместо категории. Установите 0, чтобы всегда показывать категорию.'),

                        Toggle::make('warehouse_accounting_enabled')
                            ->label('Складской учет активирован')
                            ->helperText('Остатки на сайте и в заказах берутся из таблицы «остатки по складам», а не из одного поля stock. Данные подтягиваются из 1С (API кэша Светофор) или задаются вручную в карточке товара/вариации.'),

                        Toggle::make('fallback_to_first_warehouse')
                            ->label('Fallback на первый доступный склад')
                            ->helperText('Если у региона доставки нет привязанного склада, для расчёта наличия используется первый склад, где есть остаток по товару.'),
                    ])
                    ->columns(2),

                Section::make('Интеграция с 1С')
                    ->description('Склады и остатки сопоставляются по внешним ID из кэша 1С')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('integration_help')
                            ->label('')
                            ->content(
                                "1. Включите «Складской учёт».\n"
                                . "2. Справочник складов: Доставка → Склады (поле «Внешний ID склада» = stockId из 1С).\n"
                                . "3. У товара и каждой вариации — «Внешний ID 1С» (external_id).\n"
                                . "4. Остатки подтягиваются по cron через очередь integration-1c (по external_id товара и вариаций).\n"
                                . "5. Привяжите склады к регионам доставки — иначе сработает fallback."
                            )
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            DB::transaction(function () use ($data) {
                $settings = ProductStockSettings::getInstance();
                $settings->update($data);
            });

            \Filament\Notifications\Notification::make()
                ->title('Настройки сохранены')
                ->success()
                ->send();

            $this->mount();
        } catch (\Throwable $e) {
            \Filament\Notifications\Notification::make()
                ->title('Ошибка сохранения')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->submit('save'),
        ];
    }

    public function getCachedFormActions(): array
    {
        return $this->getFormActions();
    }

    public function getView(): string
    {
        return 'filament.pages.product-stock-settings-page';
    }
}
