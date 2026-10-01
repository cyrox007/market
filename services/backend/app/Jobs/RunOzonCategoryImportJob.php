<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OzonCategoryImportRun;
use App\Models\Product\Category;
use App\Services\Catalog\Integrations\Ozon\OzonCategoryImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class RunOzonCategoryImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 2;
    public int $backoff = 120;

    public function __construct(public readonly int $runId)
    {
        $this->onQueue(config('catalog_import.queue', 'default'));
    }

    public function handle(OzonCategoryImportService $service): void
    {
        $run = OzonCategoryImportRun::query()->find($this->runId);
        if ($run === null) {
            Log::warning('Ozon category import: run not found', ['run_id' => $this->runId]);
            return;
        }

        $category = Category::query()->find($run->category_id);
        if ($category === null) {
            $exception = new RuntimeException('Категория для импорта Ozon больше не существует.');
            $run->markFailed($exception);
            throw $exception;
        }

        if (! Storage::disk('local')->exists($run->stored_path)) {
            $exception = new RuntimeException('Загруженный файл Ozon не найден в хранилище.');
            $run->markFailed($exception);
            throw $exception;
        }

        $run->markRunning();
        $path = Storage::disk('local')->path($run->stored_path);

        try {
            $result = $service->import($path, $category);
            $run->markSuccess($result);

            Log::info('Ozon category import: completed', [
                'run_id' => $run->id,
                'category_id' => $category->id,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            $run->markFailed($e);
            Log::error('Ozon category import: failed', [
                'run_id' => $run->id,
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
