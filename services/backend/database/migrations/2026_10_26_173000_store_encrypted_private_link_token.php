<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('private_price_links', fn (Blueprint $t) => $t->text('token_ciphertext')->nullable()); }
    public function down(): void { Schema::table('private_price_links', fn (Blueprint $t) => $t->dropColumn('token_ciphertext')); }
};
