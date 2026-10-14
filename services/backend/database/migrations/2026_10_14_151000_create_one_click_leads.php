<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_click_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('name');
            $table->string('phone', 32)->index();
            $table->text('comment')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->json('product_snapshot');
            $table->foreignId('shipping_location_id')->nullable()->constrained('shipping_locations')->nullOnDelete();
            $table->json('consents');
            $table->string('status')->default('new')->index();
            $table->char('fingerprint', 64)->index();
            $table->char('dedup_key', 64)->unique();
            $table->char('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_click_leads');
    }
};
