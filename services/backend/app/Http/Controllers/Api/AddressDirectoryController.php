<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Address\AddressDirectoryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Validation\ValidationException;

class AddressDirectoryController extends Controller
{
    public function detect(Request $request, AddressDirectoryClient $directory): JsonResponse
    {
        $input = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        try {
            // Candidates only. Browser coordinates are not persisted or used to
            // silently select a settlement on behalf of the customer.
            return response()->json($directory->nearby((float) $input['latitude'], (float) $input['longitude']));
        } catch (\RuntimeException $error) {
            report($error);
            return response()->json(['message' => 'Адресный справочник временно недоступен.'], 503);
        }
    }

    public function status(AddressDirectoryClient $directory): JsonResponse
    {
        try {
            return response()->json(['data' => $directory->status()]);
        } catch (Throwable $error) {
            report($error);
            return response()->json(['message' => 'Адресный справочник временно недоступен.'], 503);
        }
    }

    public function hierarchy(string $externalId, AddressDirectoryClient $directory): JsonResponse
    {
        abort_unless(preg_match('/^(?:\d{13,32}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $externalId), 422);
        try {
            return response()->json(['data' => $directory->hierarchy($externalId)]);
        } catch (ValidationException $error) {
            throw $error;
        } catch (Throwable $error) {
            report($error);
            return response()->json(['message' => 'Не удалось получить адрес из справочника.'], 503);
        }
    }

    public function index(Request $request, string $level, AddressDirectoryClient $directory): JsonResponse
    {
        $query = $request->validate([
            'parentExternalId' => [in_array($level, ['streets', 'buildings'], true) ? 'required' : 'nullable', 'string', 'max:128'],
            'q' => ['nullable', 'string', 'max:128'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        if ($level === 'localities' && empty($query['parentExternalId']) && mb_strlen(trim($query['q'] ?? '')) < 2) {
            return response()->json(['message' => 'Выберите регион или введите название населённого пункта.'], 422);
        }
        try {
            return response()->json(['data' => $directory->options($level, array_filter($query, fn ($v) => $v !== null && $v !== ''))]);
        } catch (ValidationException $error) {
            throw $error;
        } catch (Throwable $error) {
            report($error);
            return response()->json(['message' => 'Адресный справочник временно недоступен. Попробуйте ещё раз.'], 503);
        }
    }
}
