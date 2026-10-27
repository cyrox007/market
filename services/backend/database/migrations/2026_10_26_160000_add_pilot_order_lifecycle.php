<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('product_warehouse_stocks', 'reserved_quantity')) {
            Schema::table('product_warehouse_stocks', fn (Blueprint $t) => $t->decimal('reserved_quantity', 14, 3)->default(0));
        }
        Schema::table('orders', function (Blueprint $t): void {
            if (! Schema::hasColumn('orders', 'cooling_until')) { $t->timestamp('cooling_until')->nullable()->index(); }
            if (! Schema::hasColumn('orders', 'integration_sent_at')) { $t->timestamp('integration_sent_at')->nullable(); }
            if (! Schema::hasColumn('orders', 'integration_state')) { $t->string('integration_state')->nullable(); }
            if (! Schema::hasColumn('orders', 'guest_token_hash')) { $t->string('guest_token_hash', 64)->nullable()->unique(); }
        });
        if (! Schema::hasTable('checkout_attempts')) {
        Schema::create('checkout_attempts', function (Blueprint $t): void {
            $t->id(); $t->string('key_hash', 64)->unique(); $t->string('owner_hash', 64);
            $t->string('fingerprint', 64); $t->intOrBigIntBasedOnRelated('order_id', Schema::connection(null), 'orders.id')->nullable(); $t->longText('response')->nullable(); $t->timestamps();
        });
        }
        if (! Schema::hasTable('order_cancellation_requests')) {
        Schema::create('order_cancellation_requests', function (Blueprint $t): void {
            $t->id(); $t->intOrBigIntBasedOnRelated('order_id', Schema::connection(null), 'orders.id')->unique();
            $t->text('reason'); $t->string('status')->default('new'); $t->text('resolution')->nullable(); $t->timestamps();
        });
        }
        if (! Schema::hasTable('order_refund_tasks')) {
        Schema::create('order_refund_tasks', function (Blueprint $t): void {
            $t->id(); $t->intOrBigIntBasedOnRelated('order_id', Schema::connection(null), 'orders.id')->unique();
            $t->decimal('amount', 14, 2); $t->string('status')->default('manual_review'); $t->timestamps();
        });
        }
        if (! Schema::hasTable('cart_stock_reservations')) {
        Schema::create('cart_stock_reservations', function (Blueprint $t): void {
            $t->id(); $t->string('owner_hash', 64)->index();
            $t->intOrBigIntBasedOnRelated('warehouse_id', Schema::connection(null), 'warehouses.id');
            $t->intOrBigIntBasedOnRelated('product_id', Schema::connection(null), 'products.id');
            $t->unsignedInteger('quantity'); $t->timestamp('expires_at')->index(); $t->timestamps();
            $t->unique(['owner_hash', 'product_id']); $t->index(['warehouse_id', 'product_id', 'expires_at'], 'cart_reservation_available');
        });
        }
        if (! Schema::hasTable('private_price_links')) {
        Schema::create('private_price_links', function (Blueprint $t): void {
            $t->id(); $t->string('name'); $t->intOrBigIntBasedOnRelated('product_id', Schema::connection(null), 'products.id');
            $t->decimal('price', 14, 2); $t->string('token_hash', 64)->unique();
            $t->boolean('is_active')->default(true); $t->timestamp('starts_at')->nullable();
            $t->dateTime('expires_at'); $t->timestamps();
        });
        }

        // MySQL DDL commits each step: repair tables left by an interrupted run.
        foreach ([['checkout_attempts', 'order_id', 'orders', false],
            ['order_cancellation_requests', 'order_id', 'orders', true],
            ['order_refund_tasks', 'order_id', 'orders', true],
            ['cart_stock_reservations', 'warehouse_id', 'warehouses', true],
            ['cart_stock_reservations', 'product_id', 'products', true],
            ['private_price_links', 'product_id', 'products', true]] as [$table, $column, $related, $foreign]) {
            if (Schema::getColumnType($table, $column, true) !== Schema::getColumnType($related, 'id', true)) {
                Schema::table($table, function (Blueprint $t) use ($column, $related, $foreign): void {
                    $t->intOrBigIntBasedOnRelated($column, Schema::connection(null), $related.'.id')->nullable(! $foreign)->change();
                });
            }
            $exists = collect(Schema::getForeignKeys($table))->contains(fn ($key) => $key['columns'] === [$column]);
            if ($foreign && ! $exists) {
                Schema::table($table, fn (Blueprint $t) => $t->foreign($column)->references('id')->on($related)->cascadeOnDelete());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('private_price_links'); Schema::dropIfExists('cart_stock_reservations');
        Schema::dropIfExists('order_refund_tasks');
        Schema::dropIfExists('order_cancellation_requests'); Schema::dropIfExists('checkout_attempts');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['cooling_until', 'integration_sent_at', 'integration_state', 'guest_token_hash']));
        Schema::table('product_warehouse_stocks', fn (Blueprint $t) => $t->dropColumn('reserved_quantity'));
    }
};
