<?php

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ActivityAuditExport
{
    public const LIMIT = 5000;

    public function download(User $actor, Builder $query): StreamedResponse
    {
        abort_unless(ActivityAudit::canExport($actor) && config('activity_audit.enabled'), 403);
        // Materialize a bounded snapshot so the recorded count matches the file.
        $rows = (clone $query)->reorder('id', 'desc')->limit(self::LIMIT + 1)->get();
        abort_if($rows->count() > self::LIMIT, 422, 'Уточните фильтры: не более 5000 событий за одну выгрузку.');
        $audit = app(ActivityAudit::class);
        $audit->record('security', 'audit.export', details: ['changed_count' => $rows->count()], context: $audit->context($actor));

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['event_id','created_at','category','event_type','source','actor_type','actor_id','subject_type','subject_id','result','session_tag','request_id','details'], ',', '"', '');
            foreach ($rows as $row) {
                $cells = [$row->event_id, $row->created_at?->toISOString(), $row->category, $row->event_type,
                    $row->source, $row->actor_type, $row->actor_id, $row->subject_type, $row->subject_id,
                    $row->result, $row->session_tag, $row->request_id, json_encode($row->details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
                fputcsv($stream, array_map(static fn ($v) => is_string($v) && preg_match('/^[\s]*[=+\-@]/u', $v) ? "'".$v : $v, $cells), ',', '"', '');
            }
            fclose($stream);
        }, 'activity-audit-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
