<?php

use App\Support\LucideIconRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert every persisted UI icon identifier to the canonical Lucide slug.
     *
     * Unknown legacy values are intentionally replaced by a valid safe fallback,
     * so API consumers never receive an unrenderable arbitrary string after this migration.
     */
    public function up(): void
    {
        $targets = [
            ['table' => 'taxons', 'column' => 'icon'],
            ['table' => 'payment_methods', 'column' => 'icon'],
            ['table' => 'additional_services', 'column' => 'icon'],
            ['table' => 'order_additional_services', 'column' => 'icon'],
            ['table' => 'product_feature_blocks', 'column' => 'icon'],
            ['table' => 'product_delivery_blocks', 'column' => 'icon'],
            ['table' => 'advantages', 'column' => 'icon'],
            ['table' => 'sliders', 'column' => 'badge_icon'],
        ];

        foreach ($targets as $target) {
            $table = $target['table'];
            $column = $target['column'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->select(['id', $column])
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $column): void {
                    foreach ($rows as $row) {
                        $current = (string) $row->{$column};
                        $normalized = LucideIconRegistry::normalizeLegacy($current);

                        if ($normalized !== $current) {
                            DB::table($table)
                                ->where('id', $row->id)
                                ->update([$column => $normalized]);
                        }
                    }
                });
        }
    }

    /**
     * This normalization is intentionally irreversible: several Remixicon names
     * can map to the same Lucide icon and unknown values become the fallback.
     */
    public function down(): void
    {
        //
    }
};
