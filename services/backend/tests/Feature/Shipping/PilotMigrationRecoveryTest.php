<?php

namespace Tests\Feature\Shipping;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PilotMigrationRecoveryTest extends TestCase
{
    public function test_migration_can_resume_without_losing_existing_records(): void
    {
        $order = \App\Models\Order\Order::factory()->create();
        DB::table('order_cancellation_requests')->insert(['order_id' => $order->id, 'reason' => 'Keep this request', 'status' => 'new']);
        // MySQL can leave the table behind before its foreign key has been added.
        Schema::table('order_cancellation_requests', fn ($table) => $table->dropForeign(['order_id']));
        $migration = require database_path('migrations/2026_10_26_160000_add_pilot_order_lifecycle.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('order_cancellation_requests', ['order_id' => $order->id, 'reason' => 'Keep this request']);
        $this->assertCount(1, Schema::getForeignKeys('order_cancellation_requests'));
        $this->assertSame(Schema::getColumnType('orders', 'id'), Schema::getColumnType('order_cancellation_requests', 'order_id'));
        $this->assertSame(Schema::getColumnType('products', 'id'), Schema::getColumnType('private_price_links', 'product_id'));
    }
}
