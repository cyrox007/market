<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SliderResource;
use App\Models\Page\Slider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SliderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $sliders = Slider::cached('index', function () {
            return Slider::active()
                ->ordered()
                ->get();
        });

        return SliderResource::collection($sliders);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $slider = Slider::cached("show_{$slug}", function () use ($slug) {
            return Slider::where('slug', $slug)
                ->orWhere('id', $slug)
                ->active()
                ->firstOrFail();
        });

        return response()->json([
            'slider' => new SliderResource($slider),
        ]);
    }
}


