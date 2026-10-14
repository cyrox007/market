<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_addresses', function (Blueprint $table): void {
            $table->string('directory_external_id', 128)->nullable()->index();
            $table->uuid('gar_guid')->nullable();
            $table->string('kladr_code', 128)->nullable();
            $table->json('address_snapshot')->nullable();
            $table->string('room', 64)->nullable();
            $table->string('floor', 64)->nullable();
            $table->string('intercom', 64)->nullable();
            $table->text('comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_addresses', fn (Blueprint $table) => $table->dropColumn([
            'directory_external_id', 'gar_guid', 'kladr_code', 'address_snapshot', 'room', 'floor', 'intercom', 'comment',
        ]));
    }
};
