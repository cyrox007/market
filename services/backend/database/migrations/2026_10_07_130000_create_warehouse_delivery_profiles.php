<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addWarehouseColumn('source_type', fn (Blueprint $table) => $table->string('source_type', 32)->default('physical')->after('name')->index());
        $this->addWarehouseColumn('manufacturer_id', fn (Blueprint $table) => $table->foreignId('manufacturer_id')->nullable()->after('source_type')->constrained('manufacturers')->nullOnDelete());
        $this->addWarehouseColumn('stock_mode', fn (Blueprint $table) => $table->string('stock_mode', 32)->default('quantity')->after('manufacturer_id'));
        $this->addWarehouseColumn('address', fn (Blueprint $table) => $table->string('address')->nullable()->after('stock_mode'));
        $this->addWarehouseColumn('latitude', fn (Blueprint $table) => $table->decimal('latitude', 10, 7)->nullable()->after('address'));
        $this->addWarehouseColumn('longitude', fn (Blueprint $table) => $table->decimal('longitude', 10, 7)->nullable()->after('latitude'));
        $this->addWarehouseColumn('processing_days_min', fn (Blueprint $table) => $table->unsignedSmallInteger('processing_days_min')->default(0)->after('longitude'));
        $this->addWarehouseColumn('processing_days_max', fn (Blueprint $table) => $table->unsignedSmallInteger('processing_days_max')->default(0)->after('processing_days_min'));

        Schema::create('warehouse_delivery_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('name');
            $table->string('coverage_type', 32)->default('locations');
            $table->decimal('radius_km', 10, 2)->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->decimal('price_per_km', 12, 4)->default(0);
            $table->decimal('free_delivery_threshold', 12, 2)->nullable();
            $table->unsignedSmallInteger('delivery_days_min')->default(0);
            $table->unsignedSmallInteger('delivery_days_max')->default(0);
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->json('constraints')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'is_active', 'priority'], 'warehouse_profiles_active_idx');
        });

        Schema::create('warehouse_delivery_profile_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_delivery_profile_id');
            $table->unsignedBigInteger('shipping_location_id');
            $table->timestamps();
            $table->foreign('warehouse_delivery_profile_id', 'wdp_location_profile_fk')
                ->references('id')->on('warehouse_delivery_profiles')->cascadeOnDelete();
            $table->foreign('shipping_location_id', 'wdp_location_shipping_location_fk')
                ->references('id')->on('shipping_locations')->cascadeOnDelete();
            $table->unique(['warehouse_delivery_profile_id', 'shipping_location_id'], 'profile_location_unique');
        });

        Schema::create('source_product_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->unsignedInteger('product_id');
            $table->boolean('available_to_order')->default(true);
            $table->unsignedSmallInteger('processing_days_min')->nullable();
            $table->unsignedSmallInteger('processing_days_max')->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('external_reference')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['warehouse_id', 'product_id'], 'source_product_availability_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_product_availabilities');
        Schema::dropIfExists('warehouse_delivery_profile_locations');
        Schema::dropIfExists('warehouse_delivery_profiles');

        if (Schema::hasColumn('warehouses', 'manufacturer_id')) {
            Schema::table('warehouses', fn (Blueprint $table) => $table->dropConstrainedForeignId('manufacturer_id'));
        }

        $columns = collect([
            'source_type', 'stock_mode', 'address', 'latitude', 'longitude',
            'processing_days_min', 'processing_days_max',
        ])->filter(fn (string $column) => Schema::hasColumn('warehouses', $column))->all();

        if ($columns !== []) {
            Schema::table('warehouses', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    private function addWarehouseColumn(string $column, callable $definition): void
    {
        if (! Schema::hasColumn('warehouses', $column)) {
            Schema::table('warehouses', $definition);
        }
    }
};
