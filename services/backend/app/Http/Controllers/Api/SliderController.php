<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SliderResource;
use App\Models\Page\Slider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

class SliderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $placement = (string) $request->query('placement', Slider::PLACEMENT_HOME_HERO);

        if (! array_key_exists($placement, Slider::placementLabels())) {
            return response()->json([
                'message' => 'Unknown slider placement.',
                'available_placements' => array_keys(Slider::placementLabels()),
            ], 422);
        }

        return SliderResource::collection($this->getPlacement($placement));
    }

    /**
     * Два управляемых слайдера главной одним запросом.
     *
     * @return JsonResponse
     */
    public function home(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'hero' => SliderResource::collection(
                    $this->getPlacement(Slider::PLACEMENT_HOME_HERO)
                )->resolve($request),
                'categories' => SliderResource::collection(
                    $this->getPlacement(Slider::PLACEMENT_HOME_CATEGORIES)
                )->resolve($request),
            ],
            'meta' => [
                'placements' => Slider::placementLabels(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $slider = Slider::query()
            ->with(['category.media', 'media'])
            ->where(function ($query) use ($slug): void {
                $query->where('slug', $slug);

                if (ctype_digit($slug)) {
                    $query->orWhere('id', (int) $slug);
                }
            })
            ->active()
            ->firstOrFail();

        return response()->json([
            'slider' => new SliderResource($slider),
        ]);
    }

    /**
     * @return Collection<int, Slider>
     */
    private function getPlacement(string $placement): Collection
    {
        // Слайдеров мало, поэтому здесь сознательно не используем долгий model-cache:
        // изменения текста/изображений из админки должны попадать на главную сразу,
        // включая изменения изображения связанной категории.
        return Slider::query()
            ->with(['category.media', 'media'])
            ->placement($placement)
            ->active()
            ->ordered()
            ->get();
    }
}
