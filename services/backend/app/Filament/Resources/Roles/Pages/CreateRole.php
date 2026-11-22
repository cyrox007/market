<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Roles\RoleResource;
use App\Support\AdminAccessCatalog;
use Spatie\Permission\Models\Permission;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /** @var array<int, int> */
    protected array $permissionIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->permissionIds = AdminAccessCatalog::selectedIds($data['permission_groups'] ?? []);
        unset($data['permission_groups']);

        // All roles managed in this panel belong to the web guard.
        $data['guard_name'] = 'web';

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncPermissions(
            Permission::query()->where('guard_name', 'web')->whereIn('id', $this->permissionIds)->get()
        );
    }
}
