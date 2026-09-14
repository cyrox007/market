<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Integration1CDoctorCommandTest extends TestCase
{
    public function test_doctor_reports_safe_configuration_without_exposing_secret(): void
    {
        config()->set('services.integration_1c.enabled', true);
        config()->set('services.integration_1c.base_url', 'https://integration.example.test');
        config()->set('services.integration_1c.api_key', 'super-secret-value');
        config()->set('services.integration_1c.orders_path', '/api/v1/integration/1c/orders');
        config()->set('services.integration_1c.orders_queue', 'integration-1c');
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.driver', 'database');
        config()->set('queue.failed.driver', 'database-uuids');

        $exitCode = Artisan::call('integration:1c:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('https://integration.example.test/api/v1/integration/1c/orders', $output);
        $this->assertStringContainsString('configured', $output);
        $this->assertStringContainsString('integration-1c', $output);
        $this->assertStringNotContainsString('super-secret-value', $output);
    }

    public function test_doctor_fails_when_required_integration_config_is_missing(): void
    {
        config()->set('services.integration_1c.enabled', true);
        config()->set('services.integration_1c.base_url', '');
        config()->set('services.integration_1c.api_key', '');
        config()->set('services.integration_1c.orders_path', '/api/v1/integration/1c/orders');
        config()->set('services.integration_1c.orders_queue', 'integration-1c');

        $exitCode = Artisan::call('integration:1c:doctor');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('ONEC_API_BASE_URL', $output);
        $this->assertStringContainsString('ONEC_API_KEY', $output);
    }
}
