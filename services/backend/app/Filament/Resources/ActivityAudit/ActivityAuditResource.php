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
            TextColumn::make('actor_type')->label('Инициатор')->badge(),
            TextColumn::make('actor_id')->label('ID пользователя')->searchable(),
            TextColumn::make('event_type')->label('Действие')->searchable()->wrap(),
            TextColumn::make('subject_type')->label('Объект')->searchable(),
            TextColumn::make('subject_id')->label('ID объекта')->searchable(),
            TextColumn::make('result')->label('Результат')->badge(),
            TextColumn::make('session_tag')->label('Сессия')->limit(12)->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('request_id')->label('Запрос')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('details')->label('Детали')->formatStateUsing(fn ($state, $record) => json_encode($record->details, JSON_UNESCAPED_UNICODE))->wrap(),
        ])->filters([
            SelectFilter::make('category')->label('Категория')->options(['security'=>'Безопасность','admin'=>'Администрирование','order'=>'Заказы','account'=>'Аккаунт','shopping'=>'Корзина и оформление','behavior'=>'Каталог']),
            SelectFilter::make('actor_type')->label('Инициатор')->options(['admin'=>'Администратор','user'=>'Покупатель','guest'=>'Гость','system'=>'Система']),
            SelectFilter::make('result')->label('Результат')->options(['success'=>'Успешно','denied'=>'Отказ','error'=>'Ошибка']),
        ])->defaultSort('id', 'desc')->paginated([25, 50, 100])->recordActions([])->toolbarActions([]);
    }

    public static function getPages(): array { return ['index' => Pages\ListActivityAudit::route('/')]; }
}
