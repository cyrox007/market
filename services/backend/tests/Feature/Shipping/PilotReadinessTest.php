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

    public function test_sber_readiness_checks_credentials_transport_and_does_not_expose_secrets(): void
    {
        Http::fake();
        \App\Models\Payment\PaymentMethod::create(['name' => 'Sber', 'code' => 'readiness-sber', 'gateway' => 'sberbank_acquiring', 'is_active' => true]);
        config(['payment.sberbank_acquiring.username' => '', 'payment.sberbank_acquiring.password' => '',
            'payment.sberbank_acquiring.base_url' => 'http://bank.test', 'payment.sberbank_acquiring.verify_ssl' => false]);
        $this->assertSame(1, Artisan::call('pilot:readiness'));
        $output = Artisan::output();
        $this->assertStringContainsString('readiness-sber', $output);
        $this->assertStringContainsString('логин или пароль Сбера', $output);
        $this->assertStringContainsString('HTTPS-адрес банка', $output);
        config(['payment.sberbank_acquiring.username' => 'private-sber-login',
            'payment.sberbank_acquiring.password' => 'private-sber-password',
            'payment.sberbank_acquiring.base_url' => 'https://bank.test', 'payment.sberbank_acquiring.verify_ssl' => true,
            'app.url' => 'https://backend.test', 'app.frontend_url' => 'https://shop.test',
            'local_sber.enabled' => false]);
        Artisan::call('pilot:readiness');
        $output = Artisan::output();
        $this->assertStringContainsString('readiness-sber', $output);
        $this->assertStringNotContainsString('логин или пароль Сбера', $output);
        $this->assertStringNotContainsString('private-sber-login', $output);
        $this->assertStringNotContainsString('private-sber-password', $output);
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

}
