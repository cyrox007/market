<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_orders_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->char('token_hash', 64)->unique();
            $table->string('token_hint', 16);
            $table->json('abilities');
            $table->json('allowed_ips')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['revoked_at', 'expires_at']);
        });

        Schema::create('onec_orders_access_token_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('token_id')->constrained('onec_orders_access_tokens')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 32);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['token_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onec_orders_access_token_events');
        Schema::dropIfExists('onec_orders_access_tokens');
    }
};
