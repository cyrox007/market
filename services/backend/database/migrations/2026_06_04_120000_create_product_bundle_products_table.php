<?php

use Filament\Facades\Filament;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('product_bundle_products')) {
            Schema::create('product_bundle_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('bundle_product_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'bundle_product_id'], 'product_bundle_unique');
                $table->index('bundle_product_id', 'product_bundle_bundle_product_id_index');
            });
        }

        try {
            $panel = Filament::getPanel('admin_sv');
            if (method_exists($panel, 'clearCachedComponents')) {
                $panel->clearCachedComponents();
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_products');
    }
};
