<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Users\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $rolesToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Сохраняем роли для последующей синхронизации
        $this->rolesToSync = $data['roles'] ?? [];
        if (config('activity_audit.enabled') && ! auth()->user()?->hasRole('super_admin') && $this->rolesToSync !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.roles' => 'Назначать роли сотрудников может только суперадминистратор.']);
        }
        // Удаляем роли из данных, чтобы не пытаться сохранить их напрямую
        unset($data['roles']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Назначаем роли после создания пользователя
        if (! empty($this->rolesToSync)) {
            // Получаем роли по ID
            $roles = \Spatie\Permission\Models\Role::whereIn('id', $this->rolesToSync)->pluck('name')->toArray();
            $this->record->syncRoles($roles);
            app(\App\Services\Audit\ActivityAudit::class)->record('security', 'user.roles_assigned', 'user', (int) $this->record->id, details: ['fields' => ['roles'], 'changed_count' => count($roles)]);
            // Очищаем кеш разрешений
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }
}
