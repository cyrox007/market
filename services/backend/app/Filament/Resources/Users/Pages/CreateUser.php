<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $rolesToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Сохраняем роли для последующей синхронизации
        $this->rolesToSync = $data['roles'] ?? [];
        // Удаляем роли из данных, чтобы не пытаться сохранить их напрямую
        unset($data['roles']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Назначаем роли после создания пользователя
        if (!empty($this->rolesToSync)) {
            // Получаем роли по ID
            $roles = \Spatie\Permission\Models\Role::whereIn('id', $this->rolesToSync)->pluck('name')->toArray();
            $this->record->syncRoles($roles);
            // Очищаем кеш разрешений
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }
}
