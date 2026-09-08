<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ApiPerformanceTest extends TestCase
{
    public function test_api_response_includes_x_response_time_header(): void
    {
        Config::set('api_metrics.enabled', true);
        Config::set('api_metrics.header_response_time', true);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200);
        $response->assertHeader('X-Response-Time');
        $this->assertMatchesRegularExpression('/^\d+ms$/', $response->headers->get('X-Response-Time'));
    }

    public function test_metrics_endpoint_returns_json_with_valid_token(): void
    {
        Config::set('api_metrics.metrics_route_enabled', true);
        Config::set('api_metrics.token', 'secret-token');

        $response = $this->getJson('/api-docs/metrics', ['X-Metrics-Token' => 'secret-token']);

        $response->assertStatus(200);
        $response->assertJsonStructure(['routes']);
        $this->assertIsArray($response->json('routes'));
    }

    public function test_metrics_endpoint_forbidden_without_token(): void
    {
        Config::set('api_metrics.metrics_route_enabled', true);
        Config::set('api_metrics.token', 'secret-token');

        $this->getJson('/api-docs/metrics')->assertStatus(403);
        $this->getJson('/api-docs/metrics', ['X-Metrics-Token' => 'wrong'])->assertStatus(403);
    }

    public function test_metrics_endpoint_closed_when_token_not_configured(): void
    {
        // Р-5: пустой конфиг-токен = fail-closed, даже с любым переданным токеном.
        Config::set('api_metrics.metrics_route_enabled', true);
        Config::set('api_metrics.token', '');

        $this->getJson('/api-docs/metrics', ['X-Metrics-Token' => 'anything'])->assertStatus(403);
    }

    public function test_api_works_when_metrics_store_disabled(): void
    {
        Config::set('api_metrics.enabled', true);
        Config::set('api_metrics.store_enabled', false);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200);
        $response->assertHeader('X-Response-Time');
    }
}
