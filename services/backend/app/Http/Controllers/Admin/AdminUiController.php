<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminUiController extends Controller
{
    public function index(): BinaryFileResponse
    {
        return response()->file(
            $this->distPath('index.html'),
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]
        );
    }

    public function asset(Request $request, string $path): BinaryFileResponse
    {
        $distRoot = $this->distRoot();
        $requestedPath = realpath(
            $distRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path)
        );

        abort_if(
            $requestedPath === false
            || ! str_starts_with($requestedPath, $distRoot)
            || ! is_file($requestedPath),
            404,
            'Файл сборки новой админ-панели не найден.'
        );

        return response()->file(
            $requestedPath,
            [
                'Content-Type' => $this->contentType($requestedPath),
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]
        );
    }

    private function distPath(string $file): string
    {
        $path = $this->distRoot() . DIRECTORY_SEPARATOR . $file;

        abort_unless(
            is_file($path),
            503,
            'Сборка новой админ-панели не найдена. Выполните npm run build в apps/admin.'
        );

        return $path;
    }

    private function distRoot(): string
    {
        $path = realpath(base_path('../../apps/admin/dist'));

        abort_if(
            $path === false,
            503,
            'Папка apps/admin/dist не найдена. Выполните npm run build в apps/admin.'
        );

        return $path;
    }

    private function contentType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=UTF-8',
            'js', 'mjs' => 'application/javascript; charset=UTF-8',
            'json', 'map' => 'application/json; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            default => 'application/octet-stream',
        };
    }
}
