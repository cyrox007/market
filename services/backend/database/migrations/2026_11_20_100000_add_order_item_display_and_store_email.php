<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('order_items', fn (Blueprint $table) => $table->json('display_snapshot')->nullable());
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('email')->nullable();
            $table->string('phone')->nullable()->change();
        });
    }
    public function down(): void {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('display_snapshot'));
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('email'));
        // Do not make phone NOT NULL: operators may have intentionally selected the global fallback.
    }
};
