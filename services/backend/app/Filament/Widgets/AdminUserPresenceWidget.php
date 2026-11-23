<?php

namespace App\Filament\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Short-lived presence, not an audit trail or a productivity score.
 * Only administrators can query it; resource details are additionally
 * checked against the viewer's permissions.
 */
class AdminUserPresenceWidget extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.admin-user-presence-widget';

    public bool $showAll = false;

    public static function canView(): bool
    {
        $user = auth('web')->user();

        return $user !== null && $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function toggleShowAll(): void
    {
        abort_unless(static::canView(), 403);
        $this->showAll = ! $this->showAll;
    }

    protected function getViewData(): array
    {
        abort_unless(static::canView(), 403);

        // Rows are only 90 seconds old at most; no durable navigation history.
        $query = DB::table('admin_panel_presence')->where('expires_at', '>', now());
        $totalUsers = (clone $query)->distinct()->count('user_id');
        $rows = (clone $query)->join('users as u', 'u.id', '=', 'admin_panel_presence.user_id')
            ->select([
                'admin_panel_presence.user_id', 'admin_panel_presence.window_id',
                'admin_panel_presence.page_kind', 'admin_panel_presence.resource_slug',
                'admin_panel_presence.page_action', 'admin_panel_presence.record_key',
                'admin_panel_presence.page_slug', 'admin_panel_presence.last_seen_at',
                'u.name',
            ])
            ->orderByDesc('admin_panel_presence.last_seen_at')
            ->orderByDesc('admin_panel_presence.id')
            ->limit(500)
            ->get();

        $panel = Filament::getPanel('admin_sv');
        $resources = [];
        foreach ($panel->getResources() as $class) {
            $resources[$class::getSlug()] = $class;
        }
        $pages = [];
        foreach ($panel->getPages() as $class) {
            $pages[$class::getSlug()] = $class;
        }

        $windowCounts = $rows->groupBy('user_id')->map(fn ($windows) => $windows->count());
        $activities = [];
        foreach ($rows->unique('user_id')->take($this->showAll ? 50 : 5) as $row) {
            $location = $this->location($row, $resources, $pages);
            $activities[] = [
                'name' => $row->name,
                'section' => $location['section'],
                'action' => $location['action'],
                'url' => $location['url'],
                'last_seen' => \Carbon\Carbon::parse($row->last_seen_at),
                'windows' => $windowCounts[$row->user_id],
            ];
        }

        return [
            'activities' => $activities,
            'totalUsers' => $totalUsers,
            'showAll' => $this->showAll,
            'visibleCount' => count($activities),
        ];
    }

    /** @return array{section:string,action:string,url:?string} */
    private function location(object $row, array $resources, array $pages): array
    {
        if ($row->page_kind === 'dashboard') {
            return ['section' => 'Главная панель', 'action' => 'Просматривает дашборд', 'url' => url('/admin_sv')];
        }

        if ($row->page_kind === 'page') {
            $class = $pages[$row->page_slug ?? ''] ?? null;
            if (! $class || ! $class::canAccess()) {
                return $this->restricted();
            }

            try {
                return [
                    'section' => (string) $class::getNavigationLabel(),
                    'action' => 'Работает с разделом',
                    'url' => $class::getUrl(),
                ];
            } catch (\Throwable) {
                return $this->restricted();
            }
        }

        if ($row->page_kind !== 'resource') {
            return ['section' => 'Другой раздел админки', 'action' => 'Работает в панели', 'url' => null];
        }

        $class = $resources[$row->resource_slug ?? ''] ?? null;
        if (! $class || ! $class::canViewAny()) {
            return $this->restricted();
        }

        $section = (string) $class::getNavigationLabel();
        $fallback = ['section' => $section, 'action' => 'Просматривает раздел', 'url' => $class::getUrl('index')];
        $action = $row->page_action;

        if ($action === 'create') {
            if ($class::canCreate() && isset($class::getPages()['create'])) {
                return ['section' => $section, 'action' => 'Создаёт запись', 'url' => $class::getUrl('create')];
            }

            return $fallback;
        }

        if (in_array($action, ['view', 'edit'], true) && filled($row->record_key)) {
            $record = $class::getEloquentQuery()->whereKey($row->record_key)->first();
            if (! $record || ! $class::canView($record)) {
                return $fallback;
            }

            $pagesForResource = $class::getPages();
            $route = isset($pagesForResource['view']) ? 'view'
                : (($class::canEdit($record) && isset($pagesForResource['edit'])) ? 'edit' : null);
            return [
                'section' => $section,
                'action' => ($action === 'edit' ? 'Редактирует запись ' : 'Просматривает запись ').'#'.$record->getKey(),
                'url' => $route ? $class::getUrl($route, ['record' => $record]) : $fallback['url'],
            ];
        }

        return $fallback;
    }

    /** @return array{section:string,action:string,url:null} */
    private function restricted(): array
    {
        return [
            'section' => 'Раздел с ограниченным доступом',
            'action' => 'Работает в панели',
            'url' => null,
        ];
    }
}
