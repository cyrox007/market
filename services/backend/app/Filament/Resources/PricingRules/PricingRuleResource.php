<?php

namespace App\Filament\Resources\PricingRules;

use App\Models\Pricing\PricingRule;
use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class PricingRuleResource extends Resource
{
    protected static ?string $model = PricingRule::class;

    protected static ?string $policy = \App\Policies\PricingRulePolicy::class;

    protected static ?string $modelLabel = 'ценовое правило';

    protected static ?string $pluralModelLabel = 'Цены и акции';

    protected static ?string $navigationLabel = 'Цены и акции';

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function selector(string $field, string $label, string $model): Select
    {
        $labels = fn ($records) => $records->mapWithKeys(fn ($record) => [$record->id => $record->name.($record instanceof Product ? ' · '.$record->sku : ($record instanceof User ? ' · '.$record->email : ''))])->all();

        return Select::make($field)->label($label)->multiple()->searchable()->optionsLimit(30)
            ->options(fn () => $labels($model::query()->orderBy('name')->limit(30)->get()))
            ->getSearchResultsUsing(fn (string $search) => $labels($model::query()->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')
                ->when($model === User::class, fn ($q) => $q->orWhere('email', 'like', '%'.$search.'%'))
                ->when($model === Product::class, fn ($q) => $q->orWhere('sku', 'like', '%'.$search.'%')))->limit(30)->get()))
            ->getOptionLabelsUsing(fn (array $values) => $labels($model::whereIn('id', $values)->get()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Название и состояние')->description('Назовите правило так, чтобы коллеги сразу поняли его смысл. Новое правило по умолчанию выключено.')->schema([
                TextInput::make('name')->label('Название')->placeholder('Например: скидка 10% постоянным покупателям на диваны')->required()->maxLength(255)->columnSpanFull(),
                Toggle::make('is_active')->label('Правило включено')->default(false)->live(),
            ])->columnSpanFull(),
            Section::make('1. На какие товары?')->schema([
                Select::make('target_type')->label('Применять к')->options(PricingRule::TARGETS)->default('all')->required()->live(),
                self::selector('product_ids', 'Товары и варианты', Product::class)->visible(fn ($get) => $get('target_type') === 'products')->required(fn ($get) => $get('target_type') === 'products'),
                self::selector('category_ids', 'Категории (включая вложенные)', \App\Models\Product\Category::class)->visible(fn ($get) => $get('target_type') === 'categories')->required(fn ($get) => $get('target_type') === 'categories'),
                self::selector('room_ids', 'Комнаты', \App\Models\Product\Room::class)->visible(fn ($get) => $get('target_type') === 'rooms')->required(fn ($get) => $get('target_type') === 'rooms')
                    ->helperText('Учитываются категории и фильтры комнаты. Ценовые условия комнаты проверяются до применения акции.'),
                self::selector('product_group_ids', 'Подборки товаров', \App\Models\Pricing\PricingProductGroup::class)->visible(fn ($get) => $get('target_type') === 'groups')->required(fn ($get) => $get('target_type') === 'groups')
                    ->helperText('Подборки создаются в разделе «Подборки для ценовых правил».'),
                self::selector('excluded_product_ids', 'Кроме этих товаров и вариантов', Product::class)->helperText('Выбор товара исключает и все его варианты.'),
            ])->columns(2)->columnSpanFull(),
            Section::make('2. Для каких покупателей?')->schema([
                Select::make('audience_type')->label('Кому доступно')->options(PricingRule::AUDIENCES)->default('all')->required()->live(),
                self::selector('user_ids', 'Покупатели', User::class)->visible(fn ($get) => $get('audience_type') === 'users')->required(fn ($get) => $get('audience_type') === 'users'),
                self::selector('customer_group_ids', 'Группы покупателей', \App\Models\Pricing\PricingCustomerGroup::class)->visible(fn ($get) => $get('audience_type') === 'groups')->required(fn ($get) => $get('audience_type') === 'groups'),
                Section::make('Все заполненные условия должны выполняться')->visible(fn ($get) => $get('audience_type') === 'conditions')->schema([
                    TextInput::make('conditions.min_orders')->label('Доставленных заказов не меньше')->integer()->minValue(0),
                    TextInput::make('conditions.max_orders')->label('Доставленных заказов не больше')->integer()->minValue(0)->helperText('0 — ещё нет доставленных заказов.'),
                    TextInput::make('conditions.min_spend')->label('Сумма товаров в доставленных заказах от')->numeric()->minValue(0)->prefix('₽'),
                    TextInput::make('conditions.min_account_days')->label('Дней с регистрации не меньше')->integer()->minValue(0),
                ])->columns(2)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('3. Как изменить цену?')->description('Правило применяется к региональной цене за одну единицу товара. Скидки не складываются: при пересечении побеждает правило с большим приоритетом.')->schema([
                Select::make('adjustment_type')->label('Действие')->options(PricingRule::ACTIONS)->default('discount_percent')->required()->live(),
                TextInput::make('amount')->label(fn ($get) => $get('adjustment_type') === 'fixed_price' ? 'Новая цена' : 'Размер изменения')->numeric()->minValue(0)
                    ->maxValue(fn ($get) => $get('adjustment_type') === 'discount_percent' ? 100 : 999999999999)->required()->live(onBlur: true)
                    ->suffix(fn ($get) => str_ends_with($get('adjustment_type') ?? '', 'percent') ? '%' : '₽'),
            ])->columns(2)->columnSpanFull(),
            Section::make('4. Когда действует?')->description('Пустые даты — без ограничения. Часовой пояс — Москва. Выключить правило можно в любой момент.')->schema([
                DateTimePicker::make('starts_at')->label('Начало')->seconds(false)->timezone('Europe/Moscow'),
                DateTimePicker::make('ends_at')->label('Окончание')->seconds(false)->timezone('Europe/Moscow')->rules(fn ($get) => filled($get('starts_at')) ? ['after:starts_at'] : []),
            ])->columns(2)->columnSpanFull(),
            Section::make('Дополнительно')->collapsed()->schema([
                self::selector('location_ids', 'Только для выбранных регионов / городов', ShippingLocation::class)->helperText('Пусто — везде. Вложенные локации тоже включаются.'),
                TextInput::make('priority')->label('Приоритет')->integer()->minValue(0)->maxValue(10000)->default(0)->required()
                    ->helperText('Из подходящих правил применяется одно: с большим числом. При равенстве — созданное раньше. Скидки не суммируются.'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Проверить перед сохранением')->description('Это только расчёт: цена товара в базе не изменится. Проверяется текущая дата; выключенное или запланированное правило не применяется.')->schema([
                Select::make('preview_product')->label('Товар / конкретный вариант')->searchable()->dehydrated(false)
                    ->getSearchResultsUsing(fn ($search) => Product::active()->where('name', 'like', '%'.$search.'%')->limit(30)->get()->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->sku])->all())
                    ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name),
                Select::make('preview_user')->label('Покупатель (пусто — гость)')->searchable()->dehydrated(false)
                    ->getSearchResultsUsing(fn ($search) => User::where('name', 'like', '%'.$search.'%')->limit(30)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name.' · '.$u->email])->all())
                    ->getOptionLabelUsing(fn ($value) => User::find($value)?->name),
                Select::make('preview_location')->label('Регион / город (необязательно)')->searchable()->dehydrated(false)
                    ->getSearchResultsUsing(fn ($search) => ShippingLocation::where('is_active', true)->where('name', 'like', '%'.$search.'%')->limit(30)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn ($value) => ShippingLocation::find($value)?->name),
                \Filament\Schemas\Components\Actions::make([
                    Action::make('previewPrice')->label('Рассчитать цену')->icon('heroicon-o-calculator')->action(function ($livewire, $get, $record) {
                        $data = $livewire->form->getState();
                        $candidate = new PricingRule($data);
                        $candidate->id = $record?->id;
                        $candidate->validateConfiguration();
                        $sku = Product::active()->find($get('preview_product'));
                        if (! $sku || ($sku->isVariable() && ! $sku->isVariant() && $sku->variants()->exists())) {
                            Notification::make()->warning()->title('Выберите конкретный товар или вариант')->send();

                            return;
                        }
                        $result = app(PricingEngine::class)->explain($sku, ShippingLocation::find($get('preview_location')), User::find($get('preview_user')), true, $candidate);
                        Notification::make()->success()->title('Итог: '.number_format($result['price'], 2, ',', ' ').' ₽')
                            ->body('Базовая: '.$result['base_price'].' ₽. Региональная: '.$result['regional_price'].' ₽. Применено: '.($result['rule_name'] ?? 'без ценового правила').'. При пересечении действует наибольший приоритет.')->persistent()->send();
                    }),
                ])->key('pricing_preview')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Название')->searchable()->wrap(),
            TextColumn::make('summary')->label('Что делает')->wrap(),
            TextColumn::make('status_label')->label('Состояние')->badge(),
            TextColumn::make('starts_at')->label('Начало')->dateTime('d.m.Y H:i', timezone: 'Europe/Moscow'),
            TextColumn::make('ends_at')->label('Окончание')->dateTime('d.m.Y H:i', timezone: 'Europe/Moscow'),
            TextColumn::make('priority')->label('Приоритет')->sortable(),
            ToggleColumn::make('is_active')->label('Включено')->disabled(fn ($record) => ! static::canEdit($record)),
        ])->defaultSort('id', 'desc')->recordActions([
            EditAction::make(),
            Action::make('duplicate')->label('Создать копию')->icon('heroicon-o-document-duplicate')->authorize(fn () => static::canCreate())
                ->action(function (PricingRule $record) {
                    $copy = $record->replicate();
                    $copy->name = mb_substr($record->name, 0, 245).' (копия)';
                    $copy->is_active = false;
                    $copy->save();
                    Notification::make()->success()->title('Копия создана и выключена')->send();
                }),
        ])->emptyStateHeading('Ценовых правил пока нет')
            ->emptyStateDescription('Создайте правило: выберите товары, покупателей, изменение цены и срок.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPricingRules::route('/'), 'create' => Pages\CreatePricingRule::route('/create'), 'edit' => Pages\EditPricingRule::route('/{record}/edit')];
    }
}
