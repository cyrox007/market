<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sliders') || ! Schema::hasColumn('sliders', 'slot')) {
            return;
        }

        $sideRows = DB::table('sliders')
            ->where('placement', 'top')
            ->where('slot', 'side')
            ->orderBy('priority')
            ->orderBy('id')
            ->get(['id']);

        if ($sideRows->isEmpty()) {
            return;
        }

        $first = $sideRows->shift();
        if ($first) {
            DB::table('sliders')->where('id', $first->id)->update(['slot' => 'right_top']);
        }

        $second = $sideRows->shift();
        if ($second) {
            DB::table('sliders')->where('id', $second->id)->update(['slot' => 'right_bottom']);
        }

        // Если в промежуточной версии успели создать больше двух side-карточек,
        // сохраняем их как выключенные материалы нижней правой позиции, чтобы
        // ничего не потерять и дать оператору решить судьбу записей вручную.
        foreach ($sideRows as $row) {
            DB::table('sliders')->where('id', $row->id)->update([
                'slot' => 'right_bottom',
                'is_active' => false,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sliders') || ! Schema::hasColumn('sliders', 'slot')) {
            return;
        }

        DB::table('sliders')
            ->where('placement', 'top')
            ->whereIn('slot', ['right_top', 'right_bottom'])
            ->update(['slot' => 'side']);
    }
};
