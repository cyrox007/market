<?php

namespace App\Services\Order;

use App\Models\Order\Order;

class GuestOrderAccess
{
    public function valid(Order $order, mixed $token): bool
    {
        if (! is_string($token) || ! preg_match('/^[a-f0-9]{64}$/D', $token) || ! $this->available($order)) {
            return false;
        }

        return hash_equals($order->guest_token_hash, hash('sha256', $token))
            || hash_equals($this->emailToken($order), $token);
    }

    public function emailUrl(Order $order): ?string
    {
        if (! $this->available($order)) {
            return null;
        }

        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        if (! in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \LogicException('Frontend URL is not configured');
        }

        // Fragments are not sent in HTTP requests or Referer headers.
        return $base.'/orders/'.$order->id.'#access='.$this->emailToken($order);
    }

    private function available(Order $order): bool
    {
        return is_string($order->guest_token_hash) && preg_match('/^[a-f0-9]{64}$/D', $order->guest_token_hash)
            && $order->created_at?->gt(now()->subDays(30));
    }

    private function emailToken(Order $order): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \LogicException('Application key is not configured');
        }

        // Derive an independent email capability. No plaintext secret in the DB,
        // queued OrderCreated event, or mail event variables/history.
        return hash_hmac('sha256', 'guest-order-email:v1:'.$order->id.':'.$order->guest_token_hash, $key);
    }
}
