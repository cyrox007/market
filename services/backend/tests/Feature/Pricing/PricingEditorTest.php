<?php

namespace Tests\Feature\Pricing;

use App\Filament\Resources\PricingRules\Pages\CreatePricingRule;
use App\Filament\Resources\PricingRules\Pages\EditPricingRule;
use App\Models\Pricing\PricingRule;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PricingEditorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('manager', 'web');
        $user = User::factory()->create();
        $user->assignRole('manager');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    public function test_manager_can_create_a_paused_rule_with_plain_language_editor(): void
    {
        Livewire::test(CreatePricingRule::class)->assertSee('На какие товары')->assertSee('Для каких покупателей')->assertSee('Проверить перед сохранением')
            ->fillForm(['name' => 'Скидка на всё', 'target_type' => 'all', 'audience_type' => 'all', 'adjustment_type' => 'discount_percent', 'amount' => 10, 'priority' => 0, 'is_active' => false])
            ->call('create')->assertHasNoFormErrors();
        $rule = PricingRule::firstOrFail();
        $this->assertFalse($rule->is_active);
        $this->assertSame('Выключено', $rule->status_label);
    }

    public function test_preview_uses_unsaved_rule_without_creating_it(): void
    {
        $sku = Product::factory()->create(['price' => 1000]);
        Livewire::test(CreatePricingRule::class)->fillForm(['name' => 'Предпросмотр', 'is_active' => true, 'target_type' => 'all', 'audience_type' => 'all',
            'adjustment_type' => 'discount_percent', 'amount' => 10, 'priority' => 0, 'preview_product' => $sku->id])
            ->callAction(TestAction::make('previewPrice')->schemaComponent('pricing_preview', 'form'))
            ->assertNotified('Итог: 900,00 ₽');
        $this->assertDatabaseCount('pricing_rules', 0);
    }

    public function test_edit_preserves_targets_and_can_pause_immediately(): void
    {
        $sku = Product::factory()->create();
        $rule = PricingRule::create(['name' => 'Товар', 'is_active' => true, 'target_type' => 'products', 'product_ids' => [$sku->id], 'audience_type' => 'all', 'adjustment_type' => 'discount_fixed', 'amount' => 100, 'priority' => 0]);
        Livewire::test(EditPricingRule::class, ['record' => $rule->id])->fillForm(['is_active' => false])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($rule->fresh()->is_active);
        $this->assertEquals([$sku->id], $rule->fresh()->product_ids);
        $this->assertDatabaseCount('pricing_rule_changes', 2);
    }

    public function test_viewer_cannot_access_the_editor_or_create_rules(): void
    {
        Role::findOrCreate('viewer', 'web');
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');
        $this->actingAs($viewer)->get(\App\Filament\Resources\PricingRules\PricingRuleResource::getUrl('create'))->assertForbidden();
        $this->assertDatabaseCount('pricing_rules', 0);
    }
}
