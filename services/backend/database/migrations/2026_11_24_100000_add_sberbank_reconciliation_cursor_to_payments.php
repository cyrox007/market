<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('sberbank_reconcile_at')->nullable()->index();
            $table->string('sberbank_bank_id', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['sberbank_reconcile_at']);
            $table->dropUnique(['sberbank_bank_id']);
            $table->dropColumn(['sberbank_reconcile_at', 'sberbank_bank_id']);
        });
    }
};
