<?php

namespace Tests\Feature\Api;

use App\Models\Order\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OneCOrderStatusMigrationTest extends TestCase
{
    public function test_repairs_partial_table_without_losing_receipts_and_can_run_twice(): void
    {
        $name = 'onec_order_status_update_events';
        Schema::drop($name);
        // What MySQL leaves after the original BIGINT foreign key fails:
        // all columns exist, subsequent keys and indexes have not been added.
        Schema::create($name, function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('token_id')->nullable();
            $table->char('payload_hash', 64);
            $table->string('previous_status', 40);
            $table->string('applied_status', 40);
            $table->string('source_ip', 45)->nullable();
            $table->timestamps();
        });
        $order = Order::factory()->create();
        $event = (string) Str::uuid();
        DB::table($name)->insert([
            'event_id' => $event, 'order_id' => $order->id,
            'payload_hash' => str_repeat('a', 64),
            'previous_status' => 'assembling', 'applied_status' => 'assembled',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_11_23_210000_create_onec_order_status_update_events.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas($name, ['event_id' => $event, 'order_id' => $order->id]);
        $keys = collect(Schema::getForeignKeys($name));
        $this->assertTrue($keys->contains(fn ($key) => $key['columns'] === ['order_id'] && $key['foreign_table'] === 'orders'));
        $this->assertTrue($keys->contains(fn ($key) => $key['columns'] === ['token_id'] && $key['on_delete'] === 'set null'));
        $this->assertTrue(collect(Schema::getIndexes($name))->contains(fn ($index) => $index['columns'] === ['event_id'] && $index['unique']));
        if (DB::getDriverName() === 'mysql') {
            $this->assertSame('int', Schema::getColumnType($name, 'order_id'));
        }

        // The restored foreign key must enforce the original cascade behavior.
        $order->delete();
        $this->assertDatabaseMissing($name, ['event_id' => $event]);
    }
}
