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
        $request = app()->bound('request') ? request() : null;
        if ($request instanceof Request && $request->route()) {
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
            $requestId = null;
            $tag = null;
        }
        return [
            'actor_type' => $actor ? ($actor->hasRole('super_admin') || $actor->hasRole('admin') ? 'admin' : 'user') : ($requestId ? 'guest' : 'system'),
            'actor_id' => $actor?->id,
            'session_tag' => $tag,
            'request_id' => $requestId,
        ];
    }

    // No request bodies, arbitrary metadata, raw IPs, secrets or exception messages.
    // Only bounded, enumerated fields are accepted by this service.
    public function record(string $category, string $event, ?string $subject = null, ?int $id = null,
        string $result = 'success', array $details = [], ?array $context = null): void
    {
        if (! $this->enabled()) { return; }
        if (! array_key_exists($category, config('activity_audit.retention_days'))
            || ! preg_match('/^[a-z][a-z0-9_.]{0,99}$/D', $event)
            || ! in_array($result, ['success', 'denied', 'error'], true)) {
            throw new \InvalidArgumentException('Invalid audit event');
        }
        $safe = [];
        foreach ($details as $key => $value) {
            if (in_array($key, ['http_status', 'product_id', 'quantity', 'order_id', 'changed_count'], true)
                && (is_int($value) || (is_string($value) && ctype_digit($value)))) {
                $safe[$key] = (int) $value;
            }
            if ($key === 'fields' && is_array($value)) {
                $safe[$key] = array_values(array_slice(array_intersect($value, ['name','is_active','is_enabled','is_coming_soon','status','payment_method','delivery_type','total','price','sale_price','allow_preorder','email','phone','password','address_id','contact_email','contact_phone','contact_name','configuration','roles']), 0, 30));
            }
            if (in_array($key, ['previous_status','new_status'], true) && is_string($value)
                && in_array($value, array_column(\App\Models\Order\OrderStatus::cases(), 'value'), true)) {
                $safe[$key] = $value;
            }
        }
        DB::table('activity_audit_events')->insert(array_merge($context ?? $this->context(), [
            'event_id' => (string) Str::uuid(), 'category' => $category, 'event_type' => $event,
            'subject_type' => $subject, 'subject_id' => $id, 'result' => $result,
            'details' => json_encode($safe, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]));
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
        return $user !== null && $user->hasRole('super_admin');
    }
}
