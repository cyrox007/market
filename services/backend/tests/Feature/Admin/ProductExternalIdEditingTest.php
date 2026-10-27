<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductExternalIdEditingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    public function test_empty_code_is_editable_and_can_be_saved_once(): void
    {
        $product = Product::factory()->create(['external_id' => null, 'parent_product_id' => null, 'is_variable' => false]);
        Livewire::test(EditProduct::class, ['record' => $product->id])->assertFormFieldEnabled('external_id')
            ->fillForm(['external_id' => '  code-from-1c  '])->call('save', false)->assertHasNoFormErrors()
            ->assertFormFieldDisabled('external_id');
        $this->assertSame('code-from-1c', $product->fresh()->external_id);
        Livewire::test(EditProduct::class, ['record' => $product->id])->assertFormFieldDisabled('external_id');
    }

    public function test_filled_code_is_not_overwritten_by_the_form(): void
    {
        $product = Product::factory()->create(['external_id' => 'original-code', 'parent_product_id' => null, 'is_variable' => false]);
        Livewire::test(EditProduct::class, ['record' => $product->id])->assertFormFieldDisabled('external_id')
            ->set('data.external_id', 'other-code')->call('save')->assertHasNoFormErrors();
        $this->assertSame('original-code', $product->fresh()->external_id);
    }

    public function test_stale_empty_form_cannot_overwrite_a_code_saved_by_another_operator(): void
    {
        $product = Product::factory()->create(['external_id' => null, 'parent_product_id' => null, 'is_variable' => false]);
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $product->update(['external_id' => 'saved-by-another-operator']);
        $page->fillForm(['external_id' => 'stale-code'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('saved-by-another-operator', $product->fresh()->external_id);
    }
}
