<?php

namespace Tests\Feature;

use App\Actions\Product\ConvertProductWithOneCCode;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductOneCConversionTest extends TestCase
{
    public function test_transfer_modal_is_bound_to_new_offer_and_prefills_parent_code_and_stocks(): void
    {
        $parent = $this->makeProductWithStock();
        $page = new EditProduct;
        $page->record = $parent;
        $action = EditProduct::createOfferWithOneCCodeAction()->livewire($page);

        // Filament 4.3 may pass the existing record to actions embedded in an edit form.
        $schema = $action->getSchema(Schema::make($page)->model($parent));

        $this->assertNull($schema->getRecord());
        $this->assertSame(Product::class, $schema->getModel());

        $stockSection = collect($schema->getComponents())
            ->first(fn ($section) => $section->getHeading() === 'Склад (1С)');
        $fields = collect($stockSection->getChildComponents())->keyBy(fn ($field) => $field->getName());

        $this->assertSame($parent->external_id, $fields['external_id']->getDefaultState());
        $this->assertEquals(
            $parent->warehouseStocks()->get(['warehouse_id', 'quantity'])->toArray(),
            $fields['warehouseStocks']->getDefaultState(),
        );
        $this->assertSame('onec-transfer-code', $parent->fresh()->external_id);
        $this->assertFalse($parent->fresh()->isVariable());
        $this->assertSame(7.0, (float) $parent->warehouseStocks()->sum('quantity'));
    }

    public function test_creating_offer_transfers_code_and_stocks_without_clearing_first(): void
    {
        $parent = $this->makeProductWithStock();
        $code = $parent->external_id;
        $sku = $parent->sku;

        $offer = app(ConvertProductWithOneCCode::class)->execute(
            $parent,
            $code,
            function (Product $lockedParent) use ($code, $sku): Product {
                $offer = Product::factory()->create([
                    'parent_product_id' => $lockedParent->id,
                    'is_variable' => false,
                    'external_id' => $code,
                    'sku' => $sku,
                    'stock' => 7,
                ]);
                $offer->warehouseStocks()->createMany(
                    $lockedParent->warehouseStocks()->get(['warehouse_id', 'quantity'])->toArray(),
                );

                return $offer;
            },
        );

        $parent->refresh();
        $this->assertTrue($parent->isVariable());
        $this->assertNull($parent->external_id);
        $this->assertNull($parent->sku);
        $this->assertSame(0, $parent->warehouseStocks()->count());
        $this->assertSame($parent->id, $offer->parent_product_id);
        $this->assertSame($code, $offer->external_id);
        $this->assertSame($sku, $offer->sku);
        $this->assertSame(7.0, (float) $offer->warehouseStocks()->sum('quantity'));
    }

    public function test_failed_offer_creation_leaves_original_code_and_stocks_in_place(): void
    {
        $parent = $this->makeProductWithStock();

        try {
            app(ConvertProductWithOneCCode::class)->execute(
                $parent,
                $parent->external_id,
                fn () => throw ValidationException::withMessages(['name' => 'Ошибка создания ТП']),
            );
            $this->fail('Ожидалась ошибка создания ТП.');
        } catch (ValidationException) {
            $parent->refresh();
            $this->assertSame('onec-transfer-code', $parent->external_id);
            $this->assertFalse($parent->isVariable());
            $this->assertSame(7.0, (float) $parent->warehouseStocks()->sum('quantity'));
            $this->assertSame(0, $parent->variants()->count());
        }
    }

    private function makeProductWithStock(): Product
    {
        $parent = Product::factory()->create([
            'external_id' => 'onec-transfer-code',
            'is_variable' => false,
            'stock' => 7,
        ]);
        $warehouse = Warehouse::create(['name' => 'Тестовый склад', 'is_active' => true]);
        $parent->warehouseStocks()->create(['warehouse_id' => $warehouse->id, 'quantity' => 7]);

        return $parent;
    }
}
