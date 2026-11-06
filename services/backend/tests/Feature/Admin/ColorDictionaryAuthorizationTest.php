<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Colors\Pages\ManageColorSolutions;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product\Color\ColorSolution;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ColorDictionaryAuthorizationTest extends TestCase
{
    public function test_viewer_of_dictionaries_cannot_change_them_or_create_from_product_form(): void
    {
        $role = Role::findOrCreate('manager', 'web');
        foreach (['viewAny attributes', 'view attributes', 'viewAny products', 'view products', 'update products'] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));

        $solution = ColorSolution::create(['name' => 'Белый']);
        Livewire::test(ManageColorSolutions::class)
            ->assertActionHidden('create')
            ->assertTableActionHidden('edit', $solution)
            ->assertTableActionHidden('delete', $solution);

        $product = Product::factory()->create(['is_variable' => false]);
        $product->colorRows()->create(['color_element_id' => null, 'color_solution_id' => $solution->id]);
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $field = fn (string $name) => Str::after(collect($page->instance()->getSchema('form')->getFlatComponents())
            ->first(fn ($component): bool => $component instanceof Select && $component->getName() === $name)
            ->getKey(), 'form.');
        $page->assertActionHidden(TestAction::make('createOption')->schemaComponent($field('color_solution_id'), 'form'))
            ->assertActionHidden(TestAction::make('createOption')->schemaComponent($field('color_element_id'), 'form'));
    }
}
