<?php

namespace Tests\Feature\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyColorBackfillMigrationTest extends TestCase
{
    public function test_migration_backfills_variant_color_and_preserves_color_code_idempotently(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $variant = Product::factory()->create([
            'parent_product_id' => $parent->id,
            'color' => 'Молочный',
            'color_code' => '#fffaf0',
            'length' => 210,
            'width' => 160,
        ]);

        $migration = require database_path('migrations/2026_09_14_120000_backfill_legacy_product_colors_into_attributes.php');
        $migration->up();
        $migration->up();

        $colorAttribute = DB::table('product_attributes')->where('slug', Attribute::SLUG_COLOR)->first();
        $this->assertNotNull($colorAttribute);
        $this->assertTrue((bool) $colorAttribute->is_use_in_variations);

        $colorValue = DB::table('product_attribute_values')
            ->where('attribute_id', $colorAttribute->id)
            ->where('slug', 'molochnyi')
            ->first();

        $this->assertNotNull($colorValue);
        $this->assertSame('#fffaf0', $colorValue->color_code);
        $this->assertSame(1, DB::table('product_attribute_values')
            ->where('attribute_id', $colorAttribute->id)
            ->where('slug', 'molochnyi')
            ->count());

        $this->assertSame(1, DB::table('product_variant_attributes')
            ->where('product_id', $variant->id)
            ->where('attribute_id', $colorAttribute->id)
            ->where('attribute_value_id', $colorValue->id)
            ->count());

        $this->assertSame(1, DB::table('product_variation_attribute_selection')
            ->where('product_id', $parent->id)
            ->where('attribute_id', $colorAttribute->id)
            ->count());
    }

    public function test_migration_backfills_simple_product_color_as_regular_attribute(): void
    {
        $product = Product::factory()->create([
            'is_variable' => false,
            'color' => 'Графит',
            'color_code' => '#383838',
        ]);

        $migration = require database_path('migrations/2026_09_14_120000_backfill_legacy_product_colors_into_attributes.php');
        $migration->up();

        $colorAttributeId = DB::table('product_attributes')->where('slug', Attribute::SLUG_COLOR)->value('id');
        $valueId = DB::table('product_attribute_values')
            ->where('attribute_id', $colorAttributeId)
            ->where('slug', 'grafit')
            ->value('id');

        $this->assertDatabaseHas('product_product_attributes', [
            'product_id' => $product->id,
            'attribute_id' => $colorAttributeId,
            'attribute_value_id' => $valueId,
        ]);
    }

    public function test_migration_never_creates_commercial_size_from_dimensions(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        Product::factory()->create([
            'parent_product_id' => $parent->id,
            'length' => 280,
            'width' => 180,
            'height' => 100,
            'color' => 'Белый',
        ]);

        $migration = require database_path('migrations/2026_09_14_120000_backfill_legacy_product_colors_into_attributes.php');
        $migration->up();

        $this->assertDatabaseMissing('product_attributes', [
            'slug' => Attribute::SLUG_SIZE,
        ]);
    }
}
