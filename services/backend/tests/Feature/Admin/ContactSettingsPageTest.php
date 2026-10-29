<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ContactSettingsPage;
use App\Models\Settings\ContactSettings;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactSettingsPageTest extends TestCase
{
    public function test_repeated_saves_and_reload_preserve_all_contact_lists(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $data = [
            'company_name' => 'Test contacts',
            'address' => 'Test address',
            'phones' => [['phone' => '8 (800) 222-85-86']],
            'emails' => [['email' => 'shop@example.test']],
            'social_networks' => [['name' => 'VK', 'url' => 'https://vk.com/example']],
            'working_hours' => [['day' => 'Ежедневно', 'hours' => '9:00–21:00']],
        ];
        $formData = $data;
        foreach (['phones', 'emails', 'social_networks', 'working_hours'] as $field) {
            $formData[$field] = [(string) \Illuminate\Support\Str::uuid() => $data[$field][0]];
        }
        $page = Livewire::test(ContactSettingsPage::class)
            ->fillForm($formData)->call('save')->assertHasNoFormErrors()->assertSuccessful();
        $this->assertSame($data['phones'], ContactSettings::getInstance()->phones);
        $page->set('data.phones', [
            (string) \Illuminate\Support\Str::uuid() => ['phone' => '+79001234567'],
            (string) \Illuminate\Support\Str::uuid() => ['phone' => '+79007654321'],
        ])
            ->call('save')->assertHasNoFormErrors()->assertSuccessful();
        $this->assertCount(2, ContactSettings::getInstance()->phones);
        Livewire::test(ContactSettingsPage::class)->assertSuccessful()->assertSee('+79001234567');
        $this->getJson('/api/v1/contact')->assertOk()->assertJsonPath('contact.phones.0.phone', '+79001234567');
    }
}
