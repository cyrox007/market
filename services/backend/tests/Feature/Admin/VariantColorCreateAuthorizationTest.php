<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\ProductVariantsRelationManager;
use App\Models\Product\Color\BaseColor;
use App\Models\Product\Color\ColorSolution;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VariantColorCreateAuthorizationTest extends TestCase
{
    public static function creationPermissions(): array
    {
        return ['manager with permission' => [true], 'manager without permission' => [false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('creationPermissions')]
    public function test_nested_creation_respects_color_dictionary_permission(bool $allowed): void
    {
        Role::findOrCreate('manager', 'web');
        $u = User::factory()->create();
        $u->assignRole('manager');
        foreach (['viewAny products', 'view products', 'create products', 'update products', 'create attributes', 'view attributes', 'viewAny attributes', 'update attributes'] as $perm) {
            \Spatie\Permission\Models\Permission::findOrCreate($perm, 'web');
            $u->givePermissionTo($perm);
        }
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $root = Product::factory()->create(['is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $root->id]);

        $existing = ColorSolution::create(['name' => 'Existing']);
        $offer->colorRows()->create(['color_solution_id' => $existing->id]);
        $black = BaseColor::where('name', 'Чёрный')->firstOrFail();
        $page = Livewire::test(ProductVariantsRelationManager::class, ['ownerRecord' => $root, 'pageClass' => EditProduct::class])->mountTableAction('edit', $offer);
        $schema = $page->instance()->getMountedTableActionForm();
        $field = collect($schema->getFlatComponents())->first(fn ($c) => $c instanceof Select && $c->getName() === 'color_solution_id');
        $this->assertTrue($field->getCreateOptionAction()->isAuthorized());
        $action = TestAction::make('createOption')->schemaComponent(\Illuminate\Support\Str::after($field->getKey(), 'mountedActionSchema0.'));
        if (! $allowed) {
            $u->revokePermissionTo('create attributes');
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            $page->assertActionHidden($action);
            $this->assertDatabaseMissing('color_solutions', ['name' => 'New nested']);

            return;
        }
        $page->callAction($action, data: ['name' => 'New nested', 'baseColors' => [$black->id]])->assertHasNoActionErrors();
        $new = ColorSolution::where('name', 'New nested')->firstOrFail();
        $this->assertEquals([$black->id], $new->baseColors->pluck('id')->all());
        $page->fillForm(['variation_custom_'.\App\Models\Product\Attribute::ensureVariantAttribute()->id => 'Диагностический вариант'])->callMountedAction()->assertHasNoActionErrors();
        $this->assertDatabaseHas('product_color_rows', ['product_id' => $offer->id, 'color_solution_id' => $new->id]);
    }
}
