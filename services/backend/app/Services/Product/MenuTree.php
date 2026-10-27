<?php

namespace App\Services\Product;

use App\Models\Product\Category;

class MenuTree
{
    /** @param class-string<Category> $model */
    public function build(string $model): array
    {
        return $model::cached('menu_tree_v1', function () use ($model): array {
            // One taxonomy query and one media query; no parent recursion, SEO,
            // descriptions, product counters or regional delivery calculations.
            $nodes = $model::active()->with('media')->orderBy('priority')->orderBy('name')->get()->groupBy('parent_id');
            $walk = function ($parent, string $prefix, array $seen = []) use (&$walk, $nodes): array {
                $result = [];
                foreach ($nodes->get($parent, collect()) as $node) {
                    if (isset($seen[$node->id])) { continue; }
                    $path = $prefix.'/'.($node->slug ?: \Illuminate\Support\Str::slug($node->name));
                    $media = $node->getFirstMedia('image') ?: $node->getFirstMedia('images');
                    $result[] = ['id' => $node->id, 'name' => $node->name, 'slug' => $node->slug,
                        'parent_id' => $node->parent_id, 'icon' => $node->icon, 'full_path' => $path,
                        'image' => $media?->getUrl(), 'image_thumb' => $media?->getUrl('thumb'),
                        'children' => $walk($node->id, $path, $seen + [$node->id => true])];
                }
                return $result;
            };
            return $walk('', $model::ROOT_PATH);
        }, 300);
    }
}
