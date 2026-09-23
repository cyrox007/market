<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminUiController extends Controller
{
    public function __invoke(Request $request, ?string $path = null): BinaryFileResponse
    {
        $distRoot = realpath(base_path('../../apps/admin/dist'));

        abort_if(
            $distRoot === false,
            503,
            'Сборка новой админ-панели не найдена. Выполните npm run build в apps/admin.'
        );

        if ($path !== null && $path !== '') {
            $requestedPath = realpath(
                $distRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path)
            );

            if ($requestedPath !== false
                && str_starts_with($requestedPath, $distRoot)
                && is_file($requestedPath)) {
                return response()->file($requestedPath);
            }
        }

        $indexPath = $distRoot . DIRECTORY_SEPARATOR . 'index.html';

        abort_unless(
            is_file($indexPath),
            503,
            'Файл apps/admin/dist/index.html не найден. Выполните npm run build в apps/admin.'
        );

        return response()->file($indexPath, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
