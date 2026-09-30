<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (Schema::hasColumn('products', 'external_id')) {
            // Пустая строка не является идентификатором 1С и мешает уникальному индексу.
            DB::table('products')->where('external_id', '')->update(['external_id' => null]);

            $duplicates = DB::table('products')
                ->select('external_id', DB::raw('COUNT(*) AS aggregate'))
                ->whereNotNull('external_id')
                ->groupBy('external_id')
                ->havingRaw('COUNT(*) > 1')
                ->limit(20)
                ->pluck('external_id')
                ->all();

            if ($duplicates !== []) {
                throw new RuntimeException(
                    'Нельзя включить защиту от дублей кодов 1С: в products уже есть повторяющиеся external_id: '
                    . implode(', ', $duplicates)
                );
            }
        }

        if (! Schema::hasColumn('products', 'ozon_group_key')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('ozon_group_key', 64)
                    ->nullable()
                    ->after('external_id')
                    ->comment('Технический ключ родительской карточки, созданной импортом Ozon');
                $table->unique('ozon_group_key', 'products_ozon_group_key_unique');
            });
        }

        if (Schema::hasColumn('products', 'external_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unique('external_id', 'products_external_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (Schema::hasColumn('products', 'external_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropUnique('products_external_id_unique');
            });
        }

        if (Schema::hasColumn('products', 'ozon_group_key')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropUnique('products_ozon_group_key_unique');
                $table->dropColumn('ozon_group_key');
            });
        }
    }
};
