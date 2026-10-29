<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_collections', function (Blueprint $table): void {
            $table->boolean('recommend_collection_members')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('product_collections', function (Blueprint $table): void {
            $table->dropColumn('recommend_collection_members');
        });
    }
};
