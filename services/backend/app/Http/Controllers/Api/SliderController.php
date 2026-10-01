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
        $placement = (string) $request->query('placement', Slider::PLACEMENT_TOP);
        $slot = $request->query('slot');

        if (! array_key_exists($placement, Slider::placementLabels())) {
            return response()->json([
                'message' => 'Unknown slider placement.',
                'available_placements' => array_keys(Slider::placementLabels()),
            ], 422);
        }

        if ($slot !== null && ! array_key_exists((string) $slot, Slider::slotLabels())) {
            return response()->json([
                'message' => 'Unknown slider slot.',
                'available_slots' => array_keys(Slider::slotLabels()),
            ], 422);
        }

        return SliderResource::collection(
            $this->getPlacement($placement, $slot ? (string) $slot : null)
        );
    }

    /**
     * Готовая структура для главной страницы:
     * - top.main: большая верхняя карусель, работает и на mobile;
     * - top.right_top: фиксированная верхняя карточка справа (desktop);
     * - top.right_bottom: фиксированная нижняя карточка справа (desktop);
     * - bottom: нижний широкий слайдер.
     */
    public function home(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'top' => [
                    'main' => SliderResource::collection(
                        $this->getPlacement(Slider::PLACEMENT_TOP, Slider::SLOT_MAIN)
                    )->resolve($request),
                    'right_top' => $this->resolveSinglePosition(
                        $request,
                        Slider::SLOT_RIGHT_TOP
                    ),
                    'right_bottom' => $this->resolveSinglePosition(
                        $request,
                        Slider::SLOT_RIGHT_BOTTOM
                    ),
                ],
                'bottom' => SliderResource::collection(
                    $this->getPlacement(Slider::PLACEMENT_BOTTOM)
                )->resolve($request),
            ],
            'meta' => [
                'placements' => Slider::placementLabels(),
                'slots' => Slider::slotLabels(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $slider = Slider::query()
            ->with('media')
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
     * Фиксированные правые позиции возвращаются как один объект или null,
     * чтобы frontend не определял «верх/низ» по порядку массива.
     *
     * @return array<string,mixed>|null
     */
    private function resolveSinglePosition(Request $request, string $slot): ?array
    {
        $slider = $this->getPlacement(Slider::PLACEMENT_TOP, $slot)->first();

        return $slider
            ? (new SliderResource($slider))->resolve($request)
            : null;
    }

    /**
     * @return Collection<int, Slider>
     */
    private function getPlacement(string $placement, ?string $slot = null): Collection
    {
        $query = Slider::query()
            ->with('media')
            ->placement($placement)
            ->active()
            ->ordered();

        if ($slot !== null) {
            $query->slot($slot);
        }

        return $query->get();
    }
}
