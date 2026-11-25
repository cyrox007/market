<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_audit_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('behavior');
            $table->json('retention_days');
            $table->timestamps();
        });
        foreach (['viewAny activity_audit', 'export activity_audit'] as $name) {
            DB::table(config('permission.table_names.permissions', 'permissions'))->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions', 'permissions'))
            ->where('guard_name', 'web')->whereIn('name', ['viewAny activity_audit', 'export activity_audit'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::dropIfExists('activity_audit_settings');
    }
};
