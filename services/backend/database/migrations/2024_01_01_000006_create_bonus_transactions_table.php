<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('bonus_transactions')) {
            return;
        }

        if (!Schema::hasTable('orders')) {
            throw new \RuntimeException('Table "orders" must exist before creating "bonus_transactions" table');
        }

        Schema::create('bonus_transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2)->comment('Сумма транзакции');
            $table->string('type')->comment('Тип: earned (начислено) или spent (потрачено)');
            $table->text('description')->nullable()->comment('Описание транзакции');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('created_at');
        });

        try {
            // Проверяем, что foreign key еще не существует
            $foreignKeyExists = DB::selectOne("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'bonus_transactions'
                AND COLUMN_NAME = 'order_id'
                AND REFERENCED_TABLE_NAME = 'orders'
            ");

            if (!$foreignKeyExists) {
                DB::statement('ALTER TABLE bonus_transactions ADD CONSTRAINT bonus_transactions_order_id_foreign FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL');
            }
        } catch (\Exception $e) {

            Log::warning('Could not create foreign key constraint for bonus_transactions.order_id: ' . $e->getMessage());

        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        try {
            DB::statement('ALTER TABLE bonus_transactions DROP FOREIGN KEY IF EXISTS bonus_transactions_order_id_foreign');
        } catch (\Exception $e) {

        }

        Schema::dropIfExists('bonus_transactions');
    }
};
