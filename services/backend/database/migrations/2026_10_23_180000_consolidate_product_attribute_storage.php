<?php

use App\Services\Product\ConsolidateProductAttributes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_variant_attributes') && Schema::hasTable('product_product_attributes')) {
            app(ConsolidateProductAttributes::class)->execute();
        }
    }

    public function down(): void
    {
        // Intentionally additive. The source pivot remains an untouched archive.
        // Removing copied rows here would destroy edits made after migration.
    }
};
