<?php

namespace App\Observers;

use App\Models\Order\Order;
use App\Models\User;
use App\Services\Audit\ActivityAudit;
use Illuminate\Database\Eloquent\Model;

final class ActivityAuditObserver
{
    public function created(Model $model): void { $this->write($model, 'created'); }
    public function updated(Model $model): void { $this->write($model, 'updated'); }
    public function deleted(Model $model): void { $this->write($model, 'deleted'); }

    private function write(Model $model, string $action): void
    {
        $audit = app(ActivityAudit::class);
        if (! $audit->enabled()) { return; }
        $context = $audit->context();
        $subject = match (true) {
            $model instanceof Order => 'order',
            $model instanceof User => 'user',
            default => strtolower(class_basename($model)),
        };
        $fields = array_keys($model->getChanges());
        if ($action === 'updated' && $subject === 'order' && ! in_array('status', $fields, true)
            && ! array_intersect($fields, ['total', 'contact_email', 'contact_phone', 'contact_name', 'payment_method', 'delivery_type'])) { return; }
        if ($action === 'updated' && ! array_diff($fields, ['updated_at', 'remember_token', 'last_seen_at', 'last_activity_at'])) { return; }
        $details = ['fields' => $fields];
        if ($model instanceof Order && $action === 'updated' && in_array('status', $fields, true)) {
            $details += ['previous_status' => $model->getRawOriginal('status'), 'new_status' => $model->status];
        }
        $category = $subject === 'order' ? 'order' : ($context['actor_type'] === 'admin' ? 'admin' : 'account');
        // Other models are audited only for administrators, not noisy bulk imports.
        if (! in_array($subject, ['order','user'], true) && $context['actor_type'] !== 'admin') { return; }
        $audit->record($category, $subject.'.'.$action, $subject, (int) $model->getKey(), details: $details, context: $context);
    }
}
