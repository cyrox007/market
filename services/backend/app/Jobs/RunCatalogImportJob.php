<?php

namespace App\Jobs;

use App\Models\CatalogImportRun;
use App\Services\Catalog\Contracts\CatalogImportInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunCatalogImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 2;

    public int $backoff = 300;

    /**
     * @param  class-string<CatalogImportInterface>  $importerClass
     */
    public function __construct(
        protected string $importerClass
    ) {
        $this->queue = config('catalog_import.queue', 'default');
    }

    public function handle(): void
    {
        if (! is_subclass_of($this->importerClass, CatalogImportInterface::class)) {
            Log::warning('RunCatalogImportJob: invalid importer class', ['class' => $this->importerClass]);

            return;
        }

        /** @var CatalogImportInterface $importer */
        $importer = app($this->importerClass);

        Log::info('RunCatalogImportJob: started', [
            'importer' => $this->importerClass,
            'label' => $importer::getLabel(),
        ]);

        try {
            $result = $importer->import([]);
        } catch (\Throwable $e) {
            Log::error('RunCatalogImportJob: failed', [
                'importer' => $this->importerClass,
                'error' => $e->getMessage(),
            ]);
            CatalogImportRun::recordFailure($this->importerClass, $e->getMessage());

            throw $e;
        }

        CatalogImportRun::recordSuccess(
            $this->importerClass,
            $result->createdCategories,
            $result->updatedCategories,
            $result->createdProducts,
            $result->updatedProducts,
            $result->durationSeconds,
            $result->errors
        );

        Log::info('RunCatalogImportJob: completed', [
            'importer' => $this->importerClass,
            'categories' => $result->totalCategories(),
            'products' => $result->totalProducts(),
            'duration' => $result->durationSeconds,
        ]);
    }
}
