<?php

namespace App\Filament\Widgets;

use App\Jobs\RunCatalogImportJob;
use App\Models\CatalogImportRun;
use App\Services\Catalog\Contracts\CatalogImportInterface;
use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;

class CatalogImportersWidget extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.catalog-importers-widget';

    protected ?string $heading = 'Классы импорта каталога';

    protected ?string $description = 'Выберите провайдер и запустите импорт категорий и товаров.';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && $user->can('viewAny catalog_sync');
    }

    /**
     * @return array<int, array{class: string, label: string}>
     */
    public function getImporters(): array
    {
        $classes = Config::get('catalog_import.importers');

        if (! is_array($classes) || empty($classes)) {
            $classes = [Svetofor1CCatalogImport::class];
        }

        $list = [];

        foreach ($classes as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }
            if (! is_subclass_of($class, CatalogImportInterface::class)) {
                continue;
            }
            $list[] = [
                'class' => $class,
                'label' => $class::getLabel(),
            ];
        }

        return $list;
    }

    public function runImport(int $index): void
    {
        $importers = $this->getImporters();
        $class = $importers[$index]['class'] ?? null;

        if ($class === null || ! is_string($class) || ! is_subclass_of($class, CatalogImportInterface::class)) {
            Notification::make()
                ->title('Ошибка')
                ->body('Выбранный класс импорта недоступен.')
                ->danger()
                ->send();

            return;
        }

        RunCatalogImportJob::dispatch($class);

        Notification::make()
            ->title('Импорт поставлен в очередь')
            ->body('Обработка выполняется в фоне. Результат будет сохранён; при необходимости проверьте последний запуск ниже или логи.')
            ->success()
            ->send();
    }

    /**
     * Последний запуск по классу импортёра (для отображения в виджете).
     */
    public function getLastRunFor(string $importerClass): ?CatalogImportRun
    {
        return CatalogImportRun::lastRunFor($importerClass);
    }

    public function getViewData(): array
    {
        $importers = $this->getImporters();

        foreach ($importers as $i => $importer) {
            $importers[$i]['last_run'] = $this->getLastRunFor($importer['class']);
        }

        return [
            'importers' => $importers,
            'heading' => $this->heading,
            'description' => $this->description,
        ];
    }
}
