<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // No default windows: an estimated delivery date is NOT a free timeslot.
        Schema::table('warehouse_delivery_profiles', function (Blueprint $table): void {
            $table->json('delivery_weekly_slots')->nullable();
        });
        Schema::create('delivery_slot_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('warehouse_delivery_profile_id')->constrained('warehouse_delivery_profiles')->restrictOnDelete();
            $table->date('delivery_date');
            $table->string('delivery_time', 13);
            $table->timestamps();
            $table->index(['warehouse_delivery_profile_id', 'delivery_date', 'delivery_time'], 'delivery_slot_capacity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_slot_reservations');
        Schema::table('warehouse_delivery_profiles', fn (Blueprint $table) => $table->dropColumn('delivery_weekly_slots'));
    }
};
