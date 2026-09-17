<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Пустые строки — к NULL: иначе несколько '' конфликтуют в уникальном индексе (NULL же не конфликтуют).
        DB::table('users')->where('phone', '')->update(['phone' => null]);

        // Приводим существующие телефоны к каноничному виду до навешивания уникальности.
        DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $normalized = PhoneNumber::normalize($row->phone);
                    if ($normalized !== $row->phone) {
                        DB::table('users')->where('id', $row->id)->update(['phone' => $normalized]);
                    }
                }
            });

        // Уникальный индекс не переживёт дублей — сообщаем явно, ничего не удаляя (данные не трогаем).
        $duplicates = DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Нельзя добавить уникальный индекс на users.phone: дублирующиеся номера — '
                . $duplicates->implode(', ')
                . '. Разберите дубли вручную и повторите миграцию.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            // Колонка остаётся nullable: несколько NULL уникальному индексу MySQL не мешают.
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
    }
};
