<?php

namespace App\Filament\Resources\ActivityAudit;

use App\Models\ActivityAuditEvent;
use App\Services\Audit\ActivityAudit;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

final class ActivityAuditResource extends Resource
{
    protected static ?string $model = ActivityAuditEvent::class;
    protected static ?string $navigationLabel = 'Журнал действий';
    protected static ?string $pluralModelLabel = 'Журнал действий';
    protected static string|\UnitEnum|null $navigationGroup = 'Аудит и безопасность';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    public static function canViewAny(): bool { return ActivityAudit::canView(auth()->user()); }
    public static function canView(Model $record): bool { return static::canViewAny(); }
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
    public static function getEloquentQuery(): Builder
    {
        abort_unless(static::canViewAny(), 403);
        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('Время')->dateTime('d.m.Y H:i:s')->sortable(),
            TextColumn::make('source')->label('Источник')->badge(),
            TextColumn::make('actor_type')->label('Инициатор')->badge(),
            TextColumn::make('actor_id')->label('ID пользователя')->searchable(),
            TextColumn::make('event_type')->label('Действие')->searchable()->wrap(),
            TextColumn::make('subject_type')->label('Объект')->searchable(),
            TextColumn::make('subject_id')->label('ID объекта')->searchable(),
            TextColumn::make('result')->label('Результат')->badge(),
            TextColumn::make('session_tag')->label('Сессия')->limit(12)->tooltip(fn ($record) => $record->session_tag)->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('request_id')->label('Запрос')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('details')->label('Детали')->getStateUsing(fn ($record) => json_encode($record->details, JSON_UNESCAPED_UNICODE))->wrap(),
        ])->filters([
            \Filament\Tables\Filters\Filter::make('actor')->label('Пользователь')->schema([
                \Filament\Forms\Components\TextInput::make('id')->numeric()->label('ID пользователя'),
            ])->query(fn (Builder $query, array $data) => $query->when($data['id'] ?? null, fn (Builder $q, $id) => \App\Services\Audit\ActivityAuditHistory::forUser($q, (int) $id))),
            \Filament\Tables\Filters\Filter::make('order')->label('Заказ')->schema([
                \Filament\Forms\Components\TextInput::make('id')->numeric()->label('ID заказа'),
            ])->query(fn (Builder $query, array $data) => $query->when($data['id'] ?? null, fn (Builder $q, $id) => $q->where('subject_type', 'order')->where('subject_id', (int) $id))),
            \Filament\Tables\Filters\Filter::make('period')->label('Период')->schema([
                \Filament\Forms\Components\DatePicker::make('from')->label('С'),
                \Filament\Forms\Components\DatePicker::make('to')->label('По'),
            ])->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '>=', $date.' 00:00:00'))
                ->when($data['to'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '<', \Illuminate\Support\Carbon::parse($date)->addDay()->startOfDay()))),
            \Filament\Tables\Filters\Filter::make('session')->label('Сессия')->schema([
                \Filament\Forms\Components\TextInput::make('tag')->label('Полный ID сессии')->maxLength(64),
            ])->query(fn (Builder $query, array $data) => $query->when($data['tag'] ?? null, fn (Builder $q, $tag) => $q->where('session_tag', $tag))),
            SelectFilter::make('category')->label('Категория')->options(['security'=>'Безопасность','admin'=>'Администрирование','order'=>'Заказы','account'=>'Аккаунт','shopping'=>'Корзина и оформление','behavior'=>'Каталог']),
            SelectFilter::make('actor_type')->label('Инициатор')->options(['admin'=>'Администратор','user'=>'Покупатель','guest'=>'Гость','system'=>'Система','integration'=>'Интеграция','bank'=>'Банк']),
            SelectFilter::make('source')->label('Источник')->options(['storefront'=>'Витрина','admin'=>'Админка','integration'=>'Интеграция','bank'=>'Банк','system'=>'Система']),
            SelectFilter::make('result')->label('Результат')->options(['success'=>'Успешно','denied'=>'Отказ','error'=>'Ошибка']),
        ])->defaultSort('created_at', 'desc')->paginated([25, 50, 100])->recordActions([])->toolbarActions([]);
    }

    public static function getPages(): array { return ['index' => Pages\ListActivityAudit::route('/'), 'user-timeline' => Pages\UserActivityTimeline::route('/user/{userId}')]; }
}
