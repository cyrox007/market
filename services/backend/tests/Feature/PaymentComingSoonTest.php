<?php

namespace Tests\Feature;

use App\Models\Payment\PaymentMethod;
use Tests\TestCase;

class PaymentComingSoonTest extends TestCase
{
    public function test_list_shows_coming_soon_but_hides_disabled_methods(): void
    {
        $active = PaymentMethod::factory()->create(['is_active' => true, 'is_enabled' => true]);
        $hidden = PaymentMethod::factory()->create(['is_active' => false, 'is_enabled' => false]);

        $response = $this->getJson('/api/v1/payment-methods')->assertOk();
        $rows = collect($response->json('data'))->keyBy('code');
        $this->assertTrue($rows[$active->code]['selectable']);
        $this->assertFalse($rows->has($hidden->code));
        $this->assertFalse($rows['digital_ruble']['selectable']);
        $this->assertFalse($rows['digital_ruble']['is_active']);
        $this->assertSame('Скоро', $rows['digital_ruble']['availability_label']);
    }

    public function test_coming_soon_cannot_be_enabled_even_with_inconsistent_database_flags(): void
    {
        $method = PaymentMethod::where('code', 'digital_ruble')->firstOrFail();
        $method->update(['is_active' => true, 'is_enabled' => true]);
        $this->assertFalse($method->fresh()->isGloballyActive());
        $this->assertFalse($method->fresh()->is_active);

        PaymentMethod::whereKey($method->id)->update(['is_active' => true, 'is_enabled' => true]);
        $this->assertFalse($method->fresh()->isEnabled());
        $this->assertFalse(PaymentMethod::active()->whereKey($method->id)->exists());
        $this->postJson('/api/v1/orders', ['payment_method' => 'digital_ruble', 'items' => [['product_id' => 1, 'quantity' => 1]], 'accept_offer' => true, 'accept_personal_data' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
    }

    public function test_operator_can_launch_or_hide_a_coming_soon_method(): void
    {
        $method = PaymentMethod::where('code', 'digital_ruble')->firstOrFail();
        $method->update(['is_coming_soon' => false]);
        $this->assertFalse($method->fresh()->isGloballyActive());
        $method->update(['is_active' => true]);
        $this->assertTrue($method->fresh()->isGloballyActive());
        $method->update(['is_active' => false]);
        $this->assertFalse($method->fresh()->isEnabled());
    }
}
