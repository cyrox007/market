<?php

namespace App\Filament\Resources\Newsletter\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NewsletterSubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Дата подписки')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Статус')
                    ->options([
                        1 => 'Активные',
                        0 => 'Неактивные',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkAction::make('sendPromotion')
                    ->label('Отправить акции и скидки')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false)
                    ->requiresConfirmation()
                    ->modalDescription('Письмо получат только подписанные получатели, которым не отправляли рекламу последние 7 дней. Отправка через очередь.')
                    ->schema([
                        \Filament\Forms\Components\Select::make('event_id')->label('Почтовое событие')
                            ->options(fn () => \App\Models\Mail\MailEvent::where('category', 'promotion')->where('is_active', true)
                                ->whereHas('templates', fn ($q) => $q->where('is_active', true))->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function ($records, array $data): void {
                        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);
                        $event = \App\Models\Mail\MailEvent::whereKey($data['event_id'])->where('category', 'promotion')->where('is_active', true)->firstOrFail();
                        foreach ($records as $subscriber) {
                            \App\Jobs\SendPromotionalMail::dispatch($event->id, $subscriber->id);
                        }
                        \Filament\Notifications\Notification::make()->title('Задания рассылки добавлены в очередь')->success()->send();
                    })->deselectRecordsAfterCompletion(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
