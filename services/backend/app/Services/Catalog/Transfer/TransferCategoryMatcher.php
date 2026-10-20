<?php

namespace App\Services\Catalog\Transfer;

use Illuminate\Support\Facades\DB;

class TransferCategoryMatcher
{
    /** Exact normalized names AND full ancestry only. Never writes or guesses similar names. */
    public function candidates(array $trail): array
    {
        if ($trail === []) {
            return [];
        }
        $normalize = fn (string $name): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim(html_entity_decode($name))));
        $names = array_map(fn (array $item): string => $normalize($item['name']), $trail);
        $categories = DB::table('taxons')->join('taxonomies', 'taxons.taxonomy_id', '=', 'taxonomies.id')
            ->where('taxonomies.slug', 'product-categories')->select('taxons.id', 'taxons.parent_id', 'taxons.name')->get()->keyBy('id');
        $matches = [];
        foreach ($categories as $category) {
            $chain = [];
            $seen = [];
            $node = $category;
            while ($node && ! isset($seen[$node->id])) {
                $seen[$node->id] = true;
                array_unshift($chain, $normalize($node->name));
                $node = $node->parent_id ? $categories->get($node->parent_id) : null;
            }
            if ($chain === $names) {
                $matches[] = ['id' => $category->id, 'name' => $category->name, 'trail' => $chain];
            }
        }

        return $matches;
    }
}
