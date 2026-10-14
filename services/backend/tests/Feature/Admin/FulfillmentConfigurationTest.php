<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\OneClickLeads\Pages\EditOneClickLead;
use App\Models\Order\OneClickLead;
use App\Models\Page\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FulfillmentConfigurationTest extends TestCase
{
    public function test_manager_inbox_edits_status_without_changing_the_buyers_snapshot(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $lead = OneClickLead::create(['number' => 'OC-TEST', 'name' => 'Покупатель', 'phone' => '+79990000000', 'quantity' => 2,
            'product_snapshot' => ['name' => 'Диван', 'sku' => 'SKU-1'], 'consents' => ['accepted_at' => now()->toISOString(), 'offer' => true, 'personal_data' => true],
            'fingerprint' => str_repeat('a', 64), 'dedup_key' => str_repeat('b', 64)]);
        Livewire::test(EditOneClickLead::class, ['record' => $lead->id])->fillForm(['status' => 'contacted'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('contacted', $lead->fresh()->status);
        $this->assertSame('SKU-1', $lead->fresh()->product_snapshot['sku']);
        $this->assertTrue($lead->fresh()->consents['offer']);
    }

    public function test_structured_hours_keep_legacy_text_and_unknown_days_are_not_invented(): void
    {
        $store = Store::create(['name' => 'Магазин', 'slug' => 'schedule-shop', 'city' => 'Липецк', 'address' => 'Адрес', 'phone' => '+79990000000', 'is_active' => true,
            'hours' => 'Пн 09:00–18:00', 'timezone' => 'Asia/Novosibirsk',
            'opening_hours' => [['day' => 1, 'is_closed' => false, 'intervals' => [['opens' => '09:00', 'closes' => '18:00']]]]]);
        $this->getJson('/api/v1/stores/'.$store->slug)->assertOk()->assertJsonPath('store.hours', 'Пн 09:00–18:00')
            ->assertJsonPath('store.timezone', 'Asia/Novosibirsk')->assertJsonCount(1, 'store.opening_hours');
    }

    public function test_overlapping_or_duplicate_weekdays_are_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Store::create(['name' => 'Магазин', 'slug' => 'invalid-shop', 'city' => 'Липецк', 'address' => 'Адрес', 'phone' => '+79990000000', 'is_active' => true,
            'opening_hours' => [['day' => 1, 'is_closed' => false,
                'intervals' => [['opens' => '09:00', 'closes' => '12:00'], ['opens' => '11:00', 'closes' => '18:00']]]]]);
    }
}
