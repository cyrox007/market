<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            $table->string('placement', 40)
                ->default('home_hero')
                ->after('id');
            $table->unsignedBigInteger('category_id')
                ->nullable()
                ->after('placement');

            $table->index(
                ['placement', 'is_active', 'priority'],
                'sliders_placement_active_priority_index'
            );
            $table->unique(
                ['placement', 'category_id'],
                'sliders_placement_category_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            $table->dropUnique('sliders_placement_category_unique');
            $table->dropIndex('sliders_placement_active_priority_index');
            $table->dropColumn(['placement', 'category_id']);
        });
    }
};
