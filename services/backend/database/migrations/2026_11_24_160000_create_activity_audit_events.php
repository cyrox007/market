<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('category', 24);
            $table->string('event_type', 100);
            $table->string('actor_type', 24);
            $table->string('source', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->char('session_tag', 64)->nullable();
            $table->uuid('request_id')->nullable();
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('result', 24);
            $table->json('details')->nullable();
            $table->timestamp('created_at', 6);
            $table->index(['category', 'created_at', 'id']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index(['session_tag', 'created_at']);
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['request_id']);
            $table->index(['created_at', 'id']);
        });
    }
    public function down(): void { Schema::dropIfExists('activity_audit_events'); }
};
