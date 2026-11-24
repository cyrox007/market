<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $rolesToSync = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('activity_audit')->label('История действий')
                ->visible(fn () => \App\Services\Audit\ActivityAudit::canView(auth()->user()))
                ->url(fn () => \App\Filament\Resources\ActivityAudit\ActivityAuditResource::getUrl('user-timeline', ['userId' => $this->record->id])),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Загружаем роли пользователя для отображения в форме
        $this->record->load('roles');
        $data['roles'] = $this->record->roles->pluck('id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Сохраняем роли для последующей синхронизации
        $this->rolesToSync = $data['roles'] ?? [];
        // Удаляем роли из данных, чтобы не пытаться сохранить их напрямую
        unset($data['roles']);

        return $data;
    }

    protected function afterSave(): void
    {
        $before = $this->record->roles()->pluck('roles.id')->sort()->values()->all();
        // Синхронизируем роли после сохранения пользователя
        if (isset($this->rolesToSync) && ! empty($this->rolesToSync)) {
            // Получаем роли по ID
            $roles = \Spatie\Permission\Models\Role::whereIn('id', $this->rolesToSync)->pluck('name')->toArray();
            $this->record->syncRoles($roles);
            // Очищаем кеш разрешений
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } elseif (isset($this->rolesToSync) && empty($this->rolesToSync)) {
            // Если роли не выбраны, удаляем все роли
            $this->record->syncRoles([]);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
        $after = $this->record->roles()->pluck('roles.id')->sort()->values()->all();
        if ($before !== $after) {
            app(\App\Services\Audit\ActivityAudit::class)->record('security', 'user.roles_changed', 'user', (int) $this->record->id, details: ['fields' => ['roles'], 'changed_count' => count(array_diff($before, $after)) + count(array_diff($after, $before))]);
        }
    }

    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_user.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_user.title');
    }
}
