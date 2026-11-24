<?php

namespace App\Filament\Resources\ActivityAudit\Pages;

use App\Filament\Resources\ActivityAudit\ActivityAuditResource;
use App\Models\ActivityAuditEvent;
use App\Services\Audit\ActivityAudit;
use App\Services\Audit\ActivityAuditHistory;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

final class UserActivityTimeline extends Page
{
    use WithPagination;

    protected static string $resource = ActivityAuditResource::class;
    protected string $view = 'filament.pages.user-activity-timeline';
    protected static ?string $title = 'История действий пользователя';

    #[Locked]
    public int $userId;

    public function mount(int $userId): void
    {
        abort_unless(ActivityAuditResource::canViewAny() && $userId > 0, 403);
        $this->userId = $userId;
        $audit = app(ActivityAudit::class);
        $audit->record('security', 'audit.user_timeline_view', 'user', $userId);
    }

    public function hydrate(): void
    {
        abort_unless(ActivityAuditResource::canViewAny(), 403);
    }

    protected function getViewData(): array
    {
        abort_unless(ActivityAuditResource::canViewAny(), 403);
        return ['events' => ActivityAuditHistory::forUser(ActivityAuditEvent::query(), $this->userId)
            ->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(50)];
    }
}
