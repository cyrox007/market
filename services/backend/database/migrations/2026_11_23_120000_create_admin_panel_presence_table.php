<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_panel_presence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('window_id');
            $table->string('page_kind', 16);
            $table->string('resource_slug', 150)->nullable();
            $table->string('page_action', 16)->nullable();
            $table->string('record_key', 150)->nullable();
            $table->string('page_slug', 150)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at')->index();

            // A single browser tab can contribute only one active page.
            $table->unique(['user_id', 'window_id'], 'admin_panel_presence_user_window_unique');
            $table->index(['user_id', 'last_seen_at'], 'admin_panel_presence_user_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_panel_presence');
    }
};
