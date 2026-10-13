<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Address\AddressDirectoryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AddressDirectoryController extends Controller
{
    public function hierarchy(string $externalId, AddressDirectoryClient $directory): JsonResponse
    {
        abort_unless(preg_match('/^(?:\d{13,32}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $externalId), 422);
        try {
            return response()->json(['data' => $directory->hierarchy($externalId)]);
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
        } catch (Throwable $error) {
            report($error);
            return response()->json(['message' => 'Адресный справочник временно недоступен. Попробуйте ещё раз.'], 503);
        }
    }
}
