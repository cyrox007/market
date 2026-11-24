<?php

namespace App\Filament\Resources\ActivityAudit\Pages;

use App\Filament\Resources\ActivityAudit\ActivityAuditResource;
use App\Services\Audit\ActivityAudit;
use Filament\Resources\Pages\ListRecords;

final class ListActivityAudit extends ListRecords
{
    protected static string $resource = ActivityAuditResource::class;

    protected function getHeaderWidgets(): array
    {
        return [\App\Filament\Resources\ActivityAudit\Widgets\ActivityAuditOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('exportAudit')->label('Выгрузить CSV')
                ->visible(fn () => ActivityAudit::canExport(auth()->user()) && config('activity_audit.enabled'))
                ->requiresConfirmation()->modalDescription('Выгрузка текущей выборки до 5000 событий будет зарегистрирована в журнале.')
                ->action(fn () => app(\App\Services\Audit\ActivityAuditExport::class)->download(auth()->user(), $this->getFilteredTableQuery())),
        ];
    }

    public function hydrate(): void
    {
        abort_unless(ActivityAuditResource::canViewAny(), 403);
    }

    public function mount(): void
    {
        parent::mount();
        abort_unless(ActivityAuditResource::canViewAny(), 403);
        $audit = app(ActivityAudit::class);
        if ($audit->enabled()) { $audit->record('security', 'audit.view', context: $audit->context(auth()->user())); }
    }
}
