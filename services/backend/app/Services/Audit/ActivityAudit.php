<?php

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ActivityAudit
{
    public function enabled(): bool { return (bool) config('activity_audit.enabled'); }

    public function context(?User $actor = null): array
    {
        if ($actor === null && AuditExecutionContext::current() !== null) { return AuditExecutionContext::current(); }
        $request = app()->bound('request') ? request() : null;
        $source = 'system';
        if ($request instanceof Request && $request->route()) {
            $source = match (true) {
                $request->is('api/v1/integration/*') => 'integration',
                $request->is('api/v1/payment/*/callback') => 'bank',
                $request->is('admin_sv', 'admin_sv/*', 'livewire/*') => 'admin',
                default => 'storefront',
            };
            $actor ??= $request->user() ?? $request->user('web');
            $requestId = $request->attributes->get('activity_audit_request_id');
            if (! $requestId) {
                $requestId = (string) Str::uuid();
                $request->attributes->set('activity_audit_request_id', $requestId);
            }
            $tag = null;
            if ($request->hasSession()) {
                $nonce = $request->session()->get('activity_audit_nonce');
                if (! is_string($nonce)) {
                    $nonce = bin2hex(random_bytes(32));
                    $request->session()->put('activity_audit_nonce', $nonce);
                }
                $tag = hash_hmac('sha256', $nonce, (string) config('app.key'));
            }
        } else {
            if ($actor && $actor->hasAnyRole(['super_admin','admin','manager','editor','viewer'])) { $source = 'admin'; }
            $requestId = null;
            $tag = null;
        }
        return [
            'actor_type' => $actor ? ($source === 'admin' && $actor->hasAnyRole(['super_admin','admin','manager','editor','viewer']) ? 'admin' : 'user') : (in_array($source, ['integration','bank','system'], true) ? $source : 'guest'),
            'source' => $source,
            'actor_id' => $actor?->id,
            'session_tag' => $tag,
            'request_id' => $requestId,
        ];
    }

    // No request bodies, arbitrary metadata, raw IPs, secrets or exception messages.
    // Only bounded, enumerated fields are accepted by this service.
    public function record(string $category, string $event, ?string $subject = null, ?int $id = null,
        string $result = 'success', array $details = [], ?array $context = null, ?string $eventId = null, ?string $occurredAt = null): void
    {
        if (! $this->enabled()) { return; }
        if (! array_key_exists($category, config('activity_audit.retention_days'))
            || ! preg_match('/^[a-z][a-z0-9_.]{0,99}$/D', $event)
            || ! in_array($result, ['success', 'denied', 'error'], true)) {
            throw new \InvalidArgumentException('Invalid audit event');
        }
        $safe = [];
        foreach ($details as $key => $value) {
            if (in_array($key, ['http_status', 'product_id', 'quantity', 'order_id', 'payment_id', 'changed_count', 'added_count', 'removed_count', 'selected_count', 'product_count', 'category_count'], true)
                && (is_int($value) || (is_string($value) && ctype_digit($value)))) {
                $safe[$key] = (int) $value;
            }
            if (in_array($key, ['product_ids','target_ids'], true) && is_array($value)) {
                $safe[$key] = array_values(array_slice(array_unique(array_filter($value, fn ($v) => is_int($v) && $v > 0)), 0, 100));
            }
            if ($key === 'fields' && is_array($value)) {
                $safe[$key] = array_values(array_slice(array_intersect($value, ['name','is_active','is_enabled','is_coming_soon','status','payment_method','delivery_type','total','price','sale_price','amount_paid','allow_preorder','email','phone','password','address_id','contact_email','contact_phone','contact_name','configuration','roles']), 0, 30));
            }
            if ($key === 'changes' && is_array($value)) {
                foreach (['is_active','is_enabled','is_coming_soon','allow_preorder','total','price','sale_price','amount_paid'] as $field) {
                    $pair = $value[$field] ?? null;
                    if (is_array($pair) && count($pair) === 2 && array_is_list($pair)
                        && collect($pair)->every(fn ($v) => $v === null || is_bool($v) || is_int($v) || is_float($v)
                            || (is_string($v) && strlen($v) <= 24 && is_numeric($v)))) {
                        $safe['changes'][$field] = $pair;
                    }
                }
            }
            if (in_array($key, ['previous_payment_status','new_payment_status'], true) && is_string($value)
                && in_array($value, ['pending','paid','declined','cancelled','refunded','partially_refunded','timeout','authorized','failed'], true)) { $safe[$key] = $value; }
            if (in_array($key, ['previous_status','new_status'], true) && is_string($value)
                && in_array($value, array_column(\App\Models\Order\OrderStatus::cases(), 'value'), true)) {
                $safe[$key] = $value;
            }
        }
        if ($event === 'audit.settings_changed') {
            foreach (config('activity_audit.retention_days') as $categoryKey => $defaultDays) {
                $days = $details['retention_days'][$categoryKey] ?? null;
                if (is_int($days) && $days >= 7 && $days <= 1095) { $safe['retention_days'][$categoryKey] = $days; }
            }
            if (is_bool($details['behavior_enabled'] ?? null)) { $safe['behavior_enabled'] = $details['behavior_enabled']; }
        }
        $eventId ??= (string) Str::uuid();
        try {
            DB::table('activity_audit_events')->insert(array_merge($context ?? $this->context(), [
            'event_id' => $eventId, 'category' => $category, 'event_type' => $event,
            'subject_type' => $subject, 'subject_id' => $id, 'result' => $result,
            'details' => json_encode($safe, JSON_THROW_ON_ERROR), 'created_at' => $occurredAt ?? now()->format('Y-m-d H:i:s.u'),
            ]));
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            if (! DB::table('activity_audit_events')->where('event_id', $eventId)->exists()) { throw $e; }
        }
    }

    public function bestEffort(string $category, string $event, ?string $subject = null, ?int $id = null,
        string $result = 'success', array $details = [], ?array $context = null): void
    {
        try { $this->record($category, $event, $subject, $id, $result, $details, $context); }
        catch (\Throwable $e) { Log::warning('activity_audit_write_failed', ['category' => $category, 'event' => $event, 'exception_type' => $e::class]); }
    }

    public static function canView(?User $user): bool
    {
        // No reliance on global Gate::before, nor on customer/admin session alone.
        return $user !== null && ($user->hasRole('super_admin')
            || ($user->hasRole('admin') && $user->checkPermissionTo('viewAny activity_audit', 'web')));
    }

    public static function canExport(?User $user): bool
    {
        return self::canView($user) && ($user->hasRole('super_admin') || $user->checkPermissionTo('export activity_audit', 'web'));
    }
}
