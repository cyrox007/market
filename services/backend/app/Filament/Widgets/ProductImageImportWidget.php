<?php

namespace App\Filament\Widgets;

use App\Services\Catalog\Integrations\ProductImagesImporter;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;
use Livewire\WithFileUploads;

class ProductImageImportWidget extends Widget
{
    use WithFileUploads;

    protected static ?int $sort = 10;
    protected int|string|array $columnSpan = 'full';
    protected static bool $isDiscovered = false;
    protected string $view = 'filament.widgets.product-image-import-widget';

    public $file = null;
    public ?string $lastImport = null;

    public function mount(): void
    {
        $this->lastImport = session('last_product_image_import', null);
    }

    public function import(): void
    {
        if (!$this->file) {
            Notification::make()
                ->danger()
                ->title('Ошибка')
                ->body('Выберите файл для загрузки')
                ->send();
            return;
        }

        try {
            $path = $this->file->store('imports/product-images', 'local');
            
            $fullPath = storage_path('app/private/' . $path);
            Log::error($fullPath);
            // Проверяем, что файл сохранён
            if (!file_exists($fullPath)) {
                throw new \Exception('Файл не сохранился на сервере');
            }

            $importer = new ProductImagesImporter();
            $importer->import($fullPath);

            $this->lastImport = now()->toDateTimeString();
            session()->put('last_product_image_import', $this->lastImport);

            Notification::make()
                ->success()
                ->title('Импорт запущен')
                ->body('Задания на скачивание изображений поставлены в очередь')
                ->send();

            // Сбрасываем файл
            $this->reset('file');
        } catch (\Exception $e) {
            Log::error('Ошибка импорта изображений: ' . $e->getMessage());
            Notification::make()
                ->danger()
                ->title('Ошибка')
                ->body('Не удалось запустить импорт: ' . $e->getMessage())
                ->send();
        }
    }

    public function getViewData(): array
    {
        return [
            'lastImport' => $this->lastImport,
        ];
    }
}
