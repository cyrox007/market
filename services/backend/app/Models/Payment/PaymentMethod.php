<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Vanilo\Payment\Contracts\PaymentGateway;
use Vanilo\Payment\Contracts\PaymentMethod as PaymentMethodContract;
use Vanilo\Payment\Gateways\NullGateway;
use Vanilo\Payment\PaymentGateways;
use Vanilo\Support\Traits\ConfigurableModel;
use Vanilo\Support\Traits\ConfigurationHasNoSchema;

/**
 * Метод оплаты заказа. Реализует Vanilo\Payment\Contracts\PaymentMethod.
 */
class PaymentMethod extends Model implements PaymentMethodContract
{
    use ConfigurableModel;
    use ConfigurationHasNoSchema;
    use HasFactory;

    protected $table = 'payment_methods';

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'is_active',
        'is_enabled',
        'sort_order',
        'gateway',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
            'configuration' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Один переключатель «Активен» в админке управляет обоими флагами.
        static::saving(function (self $method): void {
            if ($method->isDirty('is_active')) {
                $method->is_enabled = (bool) $method->is_active;
            }
        });
    }

    public function isGloballyActive(): bool
    {
        return (bool) $this->is_active && (bool) $this->is_enabled;
    }

    public function hasPayments(): bool
    {
        $paymentClass = \Vanilo\Payment\Models\PaymentProxy::modelClass();

        return $paymentClass::where('payment_method_id', $this->id)->exists();
    }

    public function getTimeout(): int
    {
        $config = $this->configuration() ?? [];
        return (int) ($config['timeout'] ?? PaymentMethodContract::DEFAULT_TIMEOUT);
    }

    public function getGatewayName(): string
    {
        $gateway = $this->getGateway();
        return $gateway::getName();
    }

    public function getGatewayIcon(): string
    {
        $gateway = $this->getGateway();
        return $gateway::svgIcon();
    }

    public function getGateway(): PaymentGateway
    {
        $gateway = $this->gateway;
        if (null === $gateway || $gateway === '') {
            return app()->make(NullGateway::class);
        }
        return PaymentGateways::make($gateway);
    }

    /** @deprecated use configuration() */
    public function getConfiguration(): array
    {
        return $this->configuration() ?? [];
    }

    public function isEnabled(): bool
    {
        return $this->isGloballyActive();
    }

    public function getName(): string
    {
        return (string) $this->name;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
