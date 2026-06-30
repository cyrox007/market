<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $hotTtl = (int) config('cache_segments.hot_ttl', 86400);
        $categories = Category::cached('index', function () {
            $categories = Category::active()
                ->root()
                ->with([
                    'media',
                    'seo',
                    'children' => fn ($q) => $q->with(['media', 'seo', 'parent'])->orderBy('priority')->orderBy('name'),
                ])
                ->orderBy('priority')
                ->orderBy('name')
                ->get();

            // Карта потомков из уже загруженного дерева (без доп. запросов)
            $descendantIdsMap = $this->buildDescendantIdsMapFromCollection($categories);
            $categoryIds = array_keys($descendantIdsMap);
            $counts = $this->getProductsCountsForCategories($categoryIds, $descendantIdsMap);

            $categories->each(function ($category) use ($counts) {
                $attrs = $category->getAttributes();
                $attrs['products_count'] = $counts[$category->id] ?? 0;
                $category->setRawAttributes($attrs, true);
                foreach ($category->children ?? [] as $child) {
                    $childAttrs = $child->getAttributes();
                    $childAttrs['products_count'] = $counts[$child->id] ?? 0;
                    $child->setRawAttributes($childAttrs, true);
                }
            });

            return $categories;
        }, $hotTtl);

        return CategoryResource::collection($categories);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $category = Category::cached("show_{$slug}", function () use ($slug) {
            $category = Category::where('slug', $slug)
                ->orWhere('id', $slug)
                ->with(['children' => fn ($q) => $q->with(['media', 'seo', 'parent']), 'parent', 'media', 'seo'])
                ->firstOrFail();

            $categoryIds = collect([$category->id])->merge($category->children->pluck('id'))->values()->all();
            $descendantMap = [$category->id => $categoryIds];
            foreach ($category->children as $child) {
                $descendantMap[$child->id] = [$child->id];
            }

            // Если все категории уже помечены как пустые в кэше — не выполняем тяжёлый запрос подсчёта
            $allCachedEmpty = true;
            foreach ($categoryIds as $cid) {
                if (!Cache::get('empty_category:' . $cid)) {
                    $allCachedEmpty = false;
                    break;
                }
            }
            $counts = $allCachedEmpty
                ? array_fill_keys($categoryIds, 0)
                : $this->getProductsCountsForCategories($categoryIds, $descendantMap);

            foreach ($counts as $cid => $cnt) {
                if ((int) $cnt === 0) {
                    Cache::put('empty_category:' . $cid, true, 300);
                }
            }

            $attrs = $category->getAttributes();
            $attrs['products_count'] = $counts[$category->id] ?? 0;
            $category->setRawAttributes($attrs, true);

            foreach ($category->children as $child) {
                $childAttrs = $child->getAttributes();
                $childAttrs['products_count'] = $counts[$child->id] ?? 0;
                $child->setRawAttributes($childAttrs, true);
            }

            return $category;
        });

        return response()->json([
            'category' => new CategoryResource($category),
        ]);
    }

    /**
     * Построить карту category_id => [id, ...потомки] из уже загруженной коллекции (без доп. запросов).
     *
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     * @return array<int, array<int>>
     */
    private function buildDescendantIdsMapFromCollection($categories): array
    {
        $map = [];
        $collectIds = function ($category) use (&$collectIds, &$map) {
            $ids = [$category->id];
            if ($category->relationLoaded('children') && $category->children->isNotEmpty()) {
                foreach ($category->children as $child) {
                    $childIds = $collectIds($child);
                    $ids = array_merge($ids, $childIds);
                    $map[$child->id] = $childIds;
                }
            }
            return $ids;
        };
        foreach ($categories as $category) {
            $map[$category->id] = $collectIds($category);
        }
        return $map;
    }

    /**
     * Один агрегирующий запрос для подсчёта товаров по всем категориям (вместо N подзапросов).
     *
     * @param  array<int>  $categoryIds
     * @param  array<int, array<int>>  $descendantIdsMap  category_id => [self_id, ...descendant_ids]. Если пусто, вызывается getAllDescendantIdsFor для каждой категории.
     * @return array<int, int>
     */
    private function getProductsCountsForCategories(array $categoryIds, array $descendantIdsMap = []): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $activeField = Product::getActiveFieldName();
        $activeConstant = Product::getActiveConstant();

        $unions = [];
        foreach ($categoryIds as $categoryId) {
            $descendantIds = $descendantIdsMap[$categoryId] ?? Category::getAllDescendantIdsFor($categoryId);
            foreach ($descendantIds as $taxonId) {
                $unions[] = 'SELECT ' . (int) $categoryId . ' AS category_id, ' . (int) $taxonId . ' AS taxon_id';
            }
        }

        if (empty($unions)) {
            return array_fill_keys($categoryIds, 0);
        }

        $derivedTable = implode(' UNION ALL ', $unions);
        $sql = "SELECT ct.category_id, COUNT(DISTINCT p.id) AS products_count
                FROM ({$derivedTable}) ct
                INNER JOIN model_taxons mt ON mt.taxon_id = ct.taxon_id AND mt.model_type = ?
                INNER JOIN products p ON p.id = mt.model_id AND p.parent_product_id IS NULL AND p.{$activeField} = ?
                GROUP BY ct.category_id";

        $rows = DB::select($sql, [Product::class, $activeConstant]);

        $result = array_fill_keys($categoryIds, 0);
        foreach ($rows as $row) {
            $result[(int) $row->category_id] = (int) $row->products_count;
        }

        return $result;
    }

    public function tree(Request $request): JsonResponse
    {
        $hotTtl = (int) config('cache_segments.hot_ttl', 86400);
        $tree = Category::cached('tree', function () {
            $categories = Category::with([
                'media',
                'seo',
                'children' => function ($query) {
                    $query->with(['media', 'seo', 'parent', 'children' => fn ($q) => $q->with(['media', 'seo', 'parent'])->orderBy('priority')->orderBy('name')])
                        ->orderBy('priority')->orderBy('name');
                },
            ])
                ->active()
                ->root()
                ->orderBy('priority')
                ->orderBy('name')
                ->get();

            $descendantIdsMap = $this->buildDescendantIdsMapFromCollection($categories);
            $allCategoryIds = array_keys($descendantIdsMap);
            $counts = $this->getProductsCountsForCategories($allCategoryIds, $descendantIdsMap);

            foreach ($categories as $category) {
                $attrs = $category->getAttributes();
                $attrs['products_count'] = $counts[$category->id] ?? 0;
                $category->setRawAttributes($attrs, true);
                foreach ($category->children as $child) {
                    $childAttrs = $child->getAttributes();
                    $childAttrs['products_count'] = $counts[$child->id] ?? 0;
                    $child->setRawAttributes($childAttrs, true);
                    foreach ($child->children ?? [] as $grandchild) {
                        $grandchildAttrs = $grandchild->getAttributes();
                        $grandchildAttrs['products_count'] = $counts[$grandchild->id] ?? 0;
                        $grandchild->setRawAttributes($grandchildAttrs, true);
                    }
                }
            }

            return $categories;
        }, $hotTtl);

        return response()->json([
            'tree' => CategoryResource::collection($tree),
        ]);
    }
}
