<?php

namespace App\Filament\Resources\ActivityAudit\Pages;

use App\Filament\Resources\ActivityAudit\ActivityAuditResource;
use App\Services\Audit\ActivityAudit;
use Filament\Resources\Pages\ListRecords;

final class ListActivityAudit extends ListRecords
{
    protected static string $resource = ActivityAuditResource::class;

    public function mount(): void
    {
        parent::mount();
        abort_unless(ActivityAuditResource::canViewAny(), 403);
        $audit = app(ActivityAudit::class);
        if ($audit->enabled()) { $audit->record('security', 'audit.view', context: $audit->context(auth()->user())); }
    }
}
