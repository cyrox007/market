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
            is_a($model, \Vanilo\Payment\Models\PaymentProxy::modelClass()) => 'payment',
            default => strtolower(class_basename($model)),
        };
        $fields = array_keys($model->getChanges());
        if ($action === 'updated' && $subject === 'order' && ! in_array('status', $fields, true)
            && ! array_intersect($fields, ['total', 'contact_email', 'contact_phone', 'contact_name', 'payment_method', 'delivery_type'])) { return; }
        if ($action === 'updated' && ! array_diff($fields, ['updated_at', 'remember_token', 'last_seen_at', 'last_activity_at'])) { return; }
        $details = ['fields' => $fields];
        if ($action === 'updated') {
            foreach (['is_active','is_enabled','is_coming_soon','allow_preorder','total','price','sale_price','amount_paid'] as $field) {
                if (in_array($field, $fields, true)) { $details['changes'][$field] = [$model->getRawOriginal($field), $model->getAttribute($field)]; }
            }
        }
        if ($subject === 'payment') {
            if ($model->payable_type !== 'order' || ($action === 'updated' && ! array_intersect($fields, ['status','amount_paid']))) { return; }
            $details['order_id'] = (int) $model->payable_id;
            $details['payment_id'] = (int) $model->getKey();
            $details['new_payment_status'] = (string) $model->getRawOriginal('status');
            if ($action === 'updated' && in_array('status', $fields, true)) {
                $details['previous_payment_status'] = (string) $model->getRawOriginal('status');
                $details['new_payment_status'] = (string) ($model->getChanges()['status'] ?? '');
            }
        }
        if ($model instanceof Order && $action === 'updated' && in_array('status', $fields, true)) {
            $details += ['previous_status' => $model->getRawOriginal('status'), 'new_status' => $model->status];
        }
        $category = in_array($subject, ['order','payment'], true) ? 'order' : ($context['actor_type'] === 'admin' ? 'admin' : 'account');
        // Other models are audited only for administrators, not noisy bulk imports.
        if (! in_array($subject, ['order','user','payment'], true) && $context['actor_type'] !== 'admin') { return; }
        $audit->record($category, $subject.'.'.$action, $subject === 'payment' ? 'order' : $subject,
            $subject === 'payment' ? (int) $model->payable_id : (int) $model->getKey(), details: $details, context: $context);
    }
}
