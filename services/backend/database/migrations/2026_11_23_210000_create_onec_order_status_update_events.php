<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'onec_order_status_update_events';
        $exists = Schema::hasTable($tableName);
        if (! $exists) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_id');
                // Vanilo orders.id is INT UNSIGNED, not Laravel's BIGINT default.
                $table->unsignedInteger('order_id');
                $table->unsignedBigInteger('token_id')->nullable();
                $table->char('payload_hash', 64);
                $table->string('previous_status', 40);
                $table->string('applied_status', 40);
                $table->string('source_ip', 45)->nullable();
                $table->timestamps();
            });
        }

        // MySQL keeps CREATE TABLE after a failed ADD FOREIGN KEY. Repair the
        // original BIGINT column and all missing constraints without dropping rows.
        $foreignKeys = collect(Schema::getForeignKeys($tableName));
        if (! $foreignKeys->contains(fn (array $key): bool => $key['columns'] === ['order_id'])) {
            if ($exists) {
                Schema::table($tableName, fn (Blueprint $table) => $table->unsignedInteger('order_id')->change());
            }
            Schema::table($tableName, fn (Blueprint $table) =>
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete()
            );
        }
        if (! $foreignKeys->contains(fn (array $key): bool => $key['columns'] === ['token_id'])) {
            Schema::table($tableName, fn (Blueprint $table) =>
                $table->foreign('token_id')->references('id')->on('onec_orders_access_tokens')->nullOnDelete()
            );
        }

        $indexes = collect(Schema::getIndexes($tableName));
        if (! $indexes->contains(fn (array $index): bool => $index['columns'] === ['event_id'] && $index['unique'])) {
            Schema::table($tableName, fn (Blueprint $table) => $table->unique('event_id'));
        }
        if (! $indexes->contains(fn (array $index): bool => $index['columns'] === ['order_id', 'created_at'])) {
            Schema::table($tableName, fn (Blueprint $table) => $table->index(['order_id', 'created_at']));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onec_order_status_update_events');
    }
};
