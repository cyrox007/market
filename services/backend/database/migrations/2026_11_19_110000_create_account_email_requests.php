<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->unsignedInteger('auth_email_version')->default(0));
        Schema::create('account_email_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 16);
            $table->string('original_email');
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->string('password_fingerprint', 64);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_email_requests');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('auth_email_version'));
    }
};
