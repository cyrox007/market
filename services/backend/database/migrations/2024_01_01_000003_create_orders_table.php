<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('number')->unique()->comment('Номер заказа');
                $table->string('status')->default('new');
                $table->decimal('total', 10, 2)->default(0);
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('delivery_cost', 10, 2)->default(0);
                $table->decimal('assembly_cost', 10, 2)->default(0);
                $table->string('payment_method')->nullable();
                $table->string('delivery_type')->nullable();
                $table->date('delivery_date')->nullable();
                $table->string('delivery_time')->nullable();
                $table->text('comment')->nullable();
                $table->string('contact_name');
                $table->string('contact_phone');
                $table->string('contact_email');
                $table->foreignId('address_id')->nullable()->constrained('user_addresses')->onDelete('set null');
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index('number');
                $table->index('created_at');
            });
        } else {
            // Если таблица существует, проверяем и добавляем отсутствующие колонки
            // В SQLite нельзя использовать after(), колонки добавляются в конец
            Schema::table('orders', function (Blueprint $table) {
                // Финансовые поля
                if (!Schema::hasColumn('orders', 'total')) {
                    $table->decimal('total', 10, 2)->default(0);
                }
                if (!Schema::hasColumn('orders', 'subtotal')) {
                    $table->decimal('subtotal', 10, 2)->default(0);
                }
                if (!Schema::hasColumn('orders', 'delivery_cost')) {
                    $table->decimal('delivery_cost', 10, 2)->default(0);
                }
                if (!Schema::hasColumn('orders', 'assembly_cost')) {
                    $table->decimal('assembly_cost', 10, 2)->default(0);
                }

                // Поля оплаты и доставки
                if (!Schema::hasColumn('orders', 'payment_method')) {
                    $table->string('payment_method')->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_type')) {
                    $table->string('delivery_type')->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_date')) {
                    $table->date('delivery_date')->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_time')) {
                    $table->string('delivery_time')->nullable();
                }

                // Контактная информация
                if (!Schema::hasColumn('orders', 'contact_name')) {
                    $table->string('contact_name');
                }
                if (!Schema::hasColumn('orders', 'contact_phone')) {
                    $table->string('contact_phone');
                }
                if (!Schema::hasColumn('orders', 'contact_email')) {
                    $table->string('contact_email');
                }

                // Дополнительные поля
                if (!Schema::hasColumn('orders', 'comment')) {
                    $table->text('comment')->nullable();
                }
                if (!Schema::hasColumn('orders', 'address_id')) {
                    $table->foreignId('address_id')->nullable()->constrained('user_addresses')->onDelete('set null');
                }

                // Номер заказа (если нет, но обычно должен быть)
                if (!Schema::hasColumn('orders', 'number')) {
                    $table->string('number')->unique()->comment('Номер заказа');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
