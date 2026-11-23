<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminEditingPresenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $panel = Filament::getPanel('admin_sv');
        $user = $request->user('web');
        abort_unless($user && $user->canAccessPanel($panel), 403);
        Filament::setCurrentPanel($panel);

        $data = $request->validate([
            'actor_id' => 'required|string|max:100',
            'window_id' => 'required|uuid',
            'targets' => 'present|array|max:30',
            'targets.*.resource' => 'required|string|max:150',
            'targets.*.record' => 'required|string|max:150',
            'editing' => 'nullable|array|max:5',
            'editing.*.resource' => 'required|string|max:150',
            'editing.*.record' => 'required|string|max:150',
            'page' => 'sometimes|nullable|array',
            'page.kind' => 'required_with:page|in:dashboard,resource,page,other',
            'page.resource' => 'nullable|string|max:150',
            'page.action' => 'nullable|in:index,create,view,edit',
            'page.record' => 'nullable|string|max:150',
            'page.slug' => 'nullable|string|max:150',
        ]);
        abort_unless((string) $user->id === $data['actor_id'], 409);

        $resources = [];
        foreach ($panel->getResources() as $class) {
            $resources[$class::getSlug()] = $class;
        }

        $visible = [];
        $editing = [];
        foreach (['targets', 'editing'] as $kind) {
            foreach ($data[$kind] ?? [] as $target) {
                $class = $resources[$target['resource']] ?? null;
                abort_unless($class, 422);
                $record = $class::getEloquentQuery()->whereKey($target['record'])->first();
                abort_unless($record && ($kind === 'editing'
                    ? $class::canEdit($record)
                    : $class::canView($record)), 403);

                $key = $target['resource'].':'.$target['record'];
                $visible[$key] = $target;
                if ($kind === 'editing') {
                    $editing[$key] = $target;
                }
            }
        }

        // Optional for compatibility with existing installations of the edit warning.
        // Never trust arbitrary URLs or resource names supplied by the browser.
        $page = $this->authorizedPage($data['page'] ?? null, $panel, $resources);

        DB::transaction(function () use ($data, $editing, $page, $user): void {
            $expires = now()->addSeconds(90);
            DB::table('admin_editing_presence')->where('expires_at', '<=', now())->delete();
            DB::table('admin_panel_presence')->where('expires_at', '<=', now())->delete();

            DB::table('admin_editing_presence')
                ->where('user_id', $user->id)
                ->where('window_id', $data['window_id'])->delete();

            foreach ($editing as $target) {
                DB::table('admin_editing_presence')->insert([
                    'user_id' => $user->id,
                    'window_id' => $data['window_id'],
                    'resource' => $target['resource'],
                    'record_key' => $target['record'],
                    'expires_at' => $expires,
                ]);
            }

            DB::table('admin_panel_presence')
                ->where('user_id', $user->id)
                ->where('window_id', $data['window_id'])->delete();

            if ($page !== null) {
                DB::table('admin_panel_presence')->insert([
                    'user_id' => $user->id,
                    'window_id' => $data['window_id'],
                    ...$page,
                    'last_seen_at' => now(),
                    'expires_at' => $expires,
                ]);
            }
        });

        $result = [];
        foreach ($visible as $key => $target) {
            $result[$key] = DB::table('admin_editing_presence as p')
                ->join('users as u', 'u.id', '=', 'p.user_id')
                ->where('p.resource', $target['resource'])
                ->where('p.record_key', $target['record'])
                ->where('p.expires_at', '>', now())
                ->where('p.window_id', '!=', $data['window_id'])
                ->select('u.name')->distinct()->pluck('name')->all();
        }

        return response()->json(['records' => $result, 'ttl' => 90])
            ->header('Cache-Control', 'no-store, private');
    }

    /** @return array<string, string|null>|null */
    private function authorizedPage(?array $page, $panel, array $resources): ?array
    {
        if ($page === null) {
            return null;
        }

        $kind = $page['kind'] ?? null;
        $details = [
            'page_kind' => $kind,
            'resource_slug' => null,
            'page_action' => null,
            'record_key' => null,
            'page_slug' => null,
        ];

        if ($kind === 'dashboard' || $kind === 'other') {
            return $details;
        }

        if ($kind === 'page') {
            $pages = [];
            foreach ($panel->getPages() as $class) {
                $pages[$class::getSlug()] = $class;
            }
            $class = $pages[$page['slug'] ?? ''] ?? null;
            abort_unless($class, 422);
            abort_unless($class::canAccess(), 403);

            return [...$details, 'page_slug' => $page['slug']];
        }

        abort_unless($kind === 'resource', 422);
        $class = $resources[$page['resource'] ?? ''] ?? null;
        $action = $page['action'] ?? null;
        abort_unless($class && in_array($action, ['index', 'create', 'view', 'edit'], true), 422);
        abort_unless(array_key_exists($action, $class::getPages()), 422);

        $recordKey = null;
        if (in_array($action, ['edit', 'view'], true)) {
            $recordKey = $page['record'] ?? null;
            abort_unless(filled($recordKey), 422);
            $record = $class::getEloquentQuery()->whereKey($recordKey)->first();
            abort_unless($record && ($action === 'edit'
                ? $class::canEdit($record)
                : $class::canView($record)), 403);
        } else {
            abort_unless(! filled($page['record'] ?? null), 422);
            abort_unless($action === 'create' ? $class::canCreate() : $class::canViewAny(), 403);
        }

        return [
            ...$details,
            'resource_slug' => $page['resource'],
            'page_action' => $action,
            'record_key' => $recordKey,
        ];
    }

    public function release(Request $request): JsonResponse
    {
        abort_unless($request->user('web')?->canAccessPanel(Filament::getPanel('admin_sv')), 403);
        $data = $request->validate([
            'actor_id' => 'required|string|max:100',
            'window_id' => 'required|uuid',
        ]);
        abort_unless((string) $request->user('web')->id === $data['actor_id'], 409);

        DB::table('admin_editing_presence')
            ->where('user_id', $request->user('web')->id)
            ->where('window_id', $data['window_id'])->delete();
        DB::table('admin_panel_presence')
            ->where('user_id', $request->user('web')->id)
            ->where('window_id', $data['window_id'])->delete();

        return response()->json(['released' => true])
            ->header('Cache-Control', 'no-store, private');
    }
}
