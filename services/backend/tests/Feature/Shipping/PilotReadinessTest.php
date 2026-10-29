<?php
namespace Tests\Feature\Shipping;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class PilotReadinessTest extends TestCase
{
    public function test_readiness_report_is_read_only_and_never_displays_bank_credentials(): void
    {
        Http::preventStrayRequests();
        config(['services.integration_1c.enabled' => false]);
        \App\Models\Payment\PaymentMethod::create(['name' => 'Card', 'code' => 'readiness-card', 'gateway' => 'raiffeisen_ecom', 'is_active' => true,
            'configuration' => ['public_id' => 'private-public-id', 'secret_key' => 'private-bank-secret']]);
        $this->assertSame(1, Artisan::call('pilot:readiness'));
        $text = Artisan::output();
        $this->assertStringContainsString('BLOCK', $text);
        $this->assertStringNotContainsString('private-public-id', $text);
        $this->assertStringNotContainsString('private-bank-secret', $text);
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }
}
