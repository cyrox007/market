<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_auth_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('registration_enabled')->default(true);
            $table->boolean('login_enabled')->default(true);
            $table->timestamps();
        });
        DB::table('public_auth_settings')->insert(['id' => 1, 'registration_enabled' => true, 'login_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('public_auth_settings');
    }
};
