<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            $table->string('badge_text')->nullable()->after('slug');
            $table->string('badge_link')->nullable()->after('badge_text');
            $table->string('badge_icon')->nullable()->after('badge_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            $table->dropColumn(['badge_text', 'badge_link', 'badge_icon']);
        });
    }
};
