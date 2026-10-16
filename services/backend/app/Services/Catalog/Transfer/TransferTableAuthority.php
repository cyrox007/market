<?php

namespace App\Services\Catalog\Transfer;

use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Protect table-owned fields from the site's pre-existing 1C synchronization too. */
class TransferTableAuthority
{
    public function owns(Product $product, string $field): bool
    {
        if (! $product->exists || ! Schema::hasColumn('catalog_transfer_rows', 'applied_product_id')) {
            return false;
        }
        $rows = DB::table('catalog_transfer_rows')->where('applied_product_id', $product->id)->whereNotNull('table_applied_at')->get(['price', 'source']);
        foreach ($rows as $row) {
            if ($field === 'price' && $row->price !== null) {
                return true;
            }
            $value = json_decode($row->source ?? '{}', true)['product_data'][$field] ?? null;
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
