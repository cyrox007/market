<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Product\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Комнаты — вторая таксономия каталога. Формат ответов совпадает с CategoryController,
 * чтобы фронт переиспользовал страницу категории без изменений.
 */
class RoomController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $rooms = Room::cached('rooms_index', function () {
            return Room::active()
                ->root()
                ->with(self::treeEager(1))
                ->orderBy('priority')
                ->orderBy('name')
                ->get();
        });

        return CategoryResource::collection($rooms);
    }

    public function tree(): JsonResponse
    {
        $tree = Room::cached('rooms_tree', function () {
            return Room::active()
                ->root()
                ->with(self::treeEager(3))
                ->orderBy('priority')
                ->orderBy('name')
                ->get();
        });

        return response()->json([
            'tree' => CategoryResource::collection($tree),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $room = Room::cached("rooms_show_{$slug}", function () use ($slug) {
            return Room::where('slug', $slug)
                ->orWhere('id', $slug)
                ->with(['children' => fn ($q) => $q->with(['media', 'seo', 'parent']), 'parent', 'media', 'seo'])
                ->firstOrFail();
        });

        // Ключ 'category' — тот же, что у CategoryController: фронт получает комнату «в старом формате».
        return response()->json([
            'category' => new CategoryResource($room),
        ]);
    }

    /**
     * Eager-load дерева комнат на нужную глубину детей.
     */
    private static function treeEager(int $depth): array
    {
        $with = ['media', 'seo', 'parent'];
        if ($depth > 0) {
            $with['children'] = fn ($q) => $q->with(self::treeEager($depth - 1))
                ->orderBy('priority')
                ->orderBy('name');
        }

        return $with;
    }
}
