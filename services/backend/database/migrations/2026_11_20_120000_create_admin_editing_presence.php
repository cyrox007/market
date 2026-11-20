<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('admin_editing_presence', function(Blueprint $t): void {
  $t->id(); $t->unsignedBigInteger('user_id'); $t->uuid('window_id'); $t->string('resource'); $t->string('record_key'); $t->timestamp('expires_at')->index();
  $t->unique(['window_id','resource','record_key'],'admin_presence_window_record');
 }); }
 public function down(): void { Schema::dropIfExists('admin_editing_presence'); }
};
