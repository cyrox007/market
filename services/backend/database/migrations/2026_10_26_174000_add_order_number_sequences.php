<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('order_number_sequences', function (Blueprint $t): void { $t->string('day', 8)->primary(); $t->unsignedBigInteger('last_number')->default(0); }); }
    public function down(): void { Schema::dropIfExists('order_number_sequences'); }
};
