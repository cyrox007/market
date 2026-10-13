<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Address\LocalityGeoDirectory;
use App\Services\Shipping\CustomerDestinationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerLocalityController extends Controller
{
    private function respond(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (\RuntimeException $error) {
            report($error);
            return response()->json(['message' => 'Адресный справочник временно недоступен.'], 503);
        }
    }

    public function detect(Request $request, LocalityGeoDirectory $directory): JsonResponse
    {
        $input = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        return $this->respond(fn () => response()->json($directory->nearby((float) $input['latitude'], (float) $input['longitude'])));
    }

    public function regions(LocalityGeoDirectory $directory): JsonResponse
    {
        return $this->respond(fn () => response()->json(['data' => $directory->regions()]));
    }

    public function index(Request $request, LocalityGeoDirectory $directory): JsonResponse
    {
        $input = $request->validate(['region' => 'nullable|string|max:36', 'q' => 'nullable|string|max:100']);

        return $this->respond(fn () => response()->json(['data' => $directory->search($input['region'] ?? null, $input['q'] ?? '')]));
    }

    public function show(string $id, LocalityGeoDirectory $directory, CustomerDestinationResolver $resolver): JsonResponse
    {
        abort_unless(preg_match('/^(?:\d{13}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $id), 422);
        return $this->respond(function () use ($id, $directory, $resolver): JsonResponse {
            $locality = $directory->find($id);
            abort_unless($locality, 404, 'Населённый пункт не найден');
            return response()->json(['data' => $locality, 'shippingLocation' => $resolver->resolve($locality)]);
        });
    }
}
