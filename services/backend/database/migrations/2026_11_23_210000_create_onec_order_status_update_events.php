<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_order_status_update_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('token_id')->nullable()->constrained('onec_orders_access_tokens')->nullOnDelete();
            $table->char('payload_hash', 64);
            $table->string('previous_status', 40);
            $table->string('applied_status', 40);
            $table->string('source_ip', 45)->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onec_order_status_update_events');
    }
};
