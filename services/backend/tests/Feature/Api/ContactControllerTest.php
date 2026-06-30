<?php

namespace Tests\Feature\Api;

use App\Models\Settings\ContactSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_contact_settings(): void
    {
        ContactSettings::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address',
            'phones' => ['+7 (999) 123-45-67'],
            'emails' => ['test@example.com'],
        ]);

        $response = $this->getJson('/api/v1/contact');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'contact' => [
                    'company_name',
                    'address',
                    'phones',
                    'emails',
                    'social_networks',
                    'map_embed',
                    'working_hours',
                ],
            ])
            ->assertJson([
                'contact' => [
                    'company_name' => 'Test Company',
                    'address' => 'Test Address',
                ],
            ]);
    }
}


