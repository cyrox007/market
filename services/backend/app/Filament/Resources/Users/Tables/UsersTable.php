<?php

namespace App\Filament\Resources\Users\Tables;

use App\Support\AdminAccessCatalog;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament/admin_sv/user_resource.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('filament/admin_sv/user_resource.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('filament/admin_sv/user_resource.phone'))
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Роли')
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? implode(', ', array_map(fn ($name): string => AdminAccessCatalog::roleTitle((string) $name), $state))
                        : AdminAccessCatalog::roleTitle((string) $state))
                    ->badge()
                    ->separator(',')
                    ->color('primary'),
                TextColumn::make('orders_count')
                    ->label(__('filament/admin_sv/user_resource.orders_count'))
                    ->counts('orders')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament/admin_sv/user_resource.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
