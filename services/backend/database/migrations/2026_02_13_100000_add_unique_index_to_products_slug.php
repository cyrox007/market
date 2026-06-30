<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Добавляет уникальный индекс на products.slug для обеспечения уникальности ЧПУ.
     * Перед добавлением индекса устраняются дубликаты (добавляется суффикс -N).
     */
    public function up(): void
    {
        if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'slug')) {
            return;
        }

        // Устраняем дубликаты slug перед добавлением уникального индекса
        $duplicates = DB::table('products')
            ->select('slug')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');

        foreach ($duplicates as $slug) {
            $products = DB::table('products')
                ->where('slug', $slug)
                ->orderBy('id')
                ->get();

            foreach ($products->skip(1) as $index => $product) {
                $suffix = 1;
                do {
                    $candidate = $slug . '-' . $suffix;
                    $exists = DB::table('products')
                        ->where('slug', $candidate)
                        ->where('id', '!=', $product->id)
                        ->exists();
                    $suffix++;
                } while ($exists);

                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['slug' => $candidate]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique(['slug']);
            });
        }
    }
};
