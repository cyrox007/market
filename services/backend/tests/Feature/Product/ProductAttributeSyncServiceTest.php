<?php

namespace Tests\Feature\Product;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use App\Services\Product\ProductAttributeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductAttributeSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_predefined_multiple_and_custom_values_and_rejects_foreign_values(): void
    {
        $material = Attribute::create([
            'name' => 'Материал',
            'slug' => 'material',
            'type' => 'select',
            'is_multiple' => true,
            'allow_custom_value' => false,
        ]);
        $wood = AttributeValue::create(['attribute_id' => $material->id, 'value' => 'Дерево', 'slug' => 'derevo']);
        $metal = AttributeValue::create(['attribute_id' => $material->id, 'value' => 'Металл', 'slug' => 'metall']);

        $note = Attribute::create([
            'name' => 'Комментарий',
            'slug' => 'note',
            'type' => 'text',
            'allow_custom_value' => true,
        ]);

        $other = Attribute::create([
            'name' => 'Другое',
            'slug' => 'other',
            'type' => 'select',
        ]);
        $foreignValue = AttributeValue::create(['attribute_id' => $other->id, 'value' => 'Чужое', 'slug' => 'foreign']);

        $product = Product::factory()->create();

        app(ProductAttributeSyncService::class)->sync($product, [
            ['attribute_id' => $material->id, 'attribute_value_id' => [$wood->id, $metal->id, $foreignValue->id]],
            ['attribute_id' => $note->id, 'custom_value' => 'Ручное значение'],
        ]);

        $rows = DB::table('product_product_attributes')->where('product_id', $product->id)->get();

        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing([$wood->id, $metal->id], $rows->where('attribute_id', $material->id)->pluck('attribute_value_id')->all());
        $this->assertSame('Ручное значение', $rows->firstWhere('attribute_id', $note->id)->custom_value);
        $this->assertFalse($rows->contains(fn ($row) => (int) $row->attribute_value_id === $foreignValue->id));
    }

    public function test_sync_replaces_previous_rows_and_ignores_disallowed_custom_value(): void
    {
        $attribute = Attribute::create([
            'name' => 'Материал',
            'slug' => 'material',
            'type' => 'select',
            'allow_custom_value' => false,
        ]);
        $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Ткань', 'slug' => 'tkan']);
        $product = Product::factory()->create();

        $service = app(ProductAttributeSyncService::class);
        $service->sync($product, [['attribute_id' => $attribute->id, 'attribute_value_id' => $value->id]]);
        $this->assertDatabaseHas('product_product_attributes', [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'attribute_value_id' => $value->id,
        ]);

        $service->sync($product, [['attribute_id' => $attribute->id, 'custom_value' => 'Нельзя']]);

        $this->assertDatabaseMissing('product_product_attributes', [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
        ]);
    }
}
