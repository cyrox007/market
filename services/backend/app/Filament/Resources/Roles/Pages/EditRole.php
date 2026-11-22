<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Roles\RoleResource;
use App\Support\AdminAccessCatalog;
use Filament\Actions;
use Spatie\Permission\Models\Permission;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** @var array<int, int> */
    protected array $permissionIds = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load('permissions');
        $data['permission_groups'] = AdminAccessCatalog::selectedGroups($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissionIds = AdminAccessCatalog::selectedIds($data['permission_groups'] ?? []);
        unset($data['permission_groups']);

        return $data;
    }

    protected function afterSave(): void
    {
        // Keep every web permission exactly as selected, including custom keys.
        // An empty selection deliberately revokes all permissions.
        $this->record->syncPermissions(
            Permission::query()->where('guard_name', 'web')->whereIn('id', $this->permissionIds)->get()
        );
    }
}
