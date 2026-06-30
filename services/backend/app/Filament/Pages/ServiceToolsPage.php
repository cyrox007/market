<?php

namespace App\Filament\Pages;

use App\Models\Settings\ProductStockSettings;
use App\Services\Catalog\ClearAttributesService;
use App\Services\Catalog\ClearCatalogService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class ServiceToolsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Сервис';

    protected static ?string $title = 'Сервисные операции';

    protected static string|UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 100;

    protected static ?string $slug = 'service-tools';

    public const CONFIRMATION_WORD = 'УДАЛИТЬ';

    /** Показать форму подтверждения удаления товаров */
    public bool $showClearProductsForm = false;

    /** Введённое слово для подтверждения удаления товаров */
    public string $clearProductsConfirm = '';

    /** Показать форму подтверждения удаления категорий */
    public bool $showClearCategoriesForm = false;

    /** Введённое слово для подтверждения удаления категорий */
    public string $clearCategoriesConfirm = '';

    /** Показать форму подтверждения удаления характеристик */
    public bool $showClearAttributesForm = false;

    /** Введённое слово для подтверждения удаления характеристик */
    public string $clearAttributesConfirm = '';

    public bool $warehouseAccountingEnabled = false;

    public bool $fallbackToFirstWarehouse = true;

    public function mount(): void
    {
        $settings = ProductStockSettings::getInstance();
        $this->warehouseAccountingEnabled = (bool) $settings->warehouse_accounting_enabled;
        $this->fallbackToFirstWarehouse = (bool) $settings->fallback_to_first_warehouse;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->can('viewAny service_tools') || $user->can('delete_all_catalog'));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function openClearProductsForm(): void
    {
        $this->showClearProductsForm = true;
        $this->clearProductsConfirm = '';
    }

    public function cancelClearProductsForm(): void
    {
        $this->showClearProductsForm = false;
        $this->clearProductsConfirm = '';
    }

    public function clearProducts(): void
    {
        if (!$this->clearProductsConfirm || trim($this->clearProductsConfirm) !== self::CONFIRMATION_WORD) {
            Notification::make()
                ->title('Ошибка')
                ->body('Введите точно: ' . self::CONFIRMATION_WORD)
                ->danger()
                ->send();

            return;
        }

        $service = app(ClearCatalogService::class);
        $count = $service->clearProductsOnly();

        $this->showClearProductsForm = false;
        $this->clearProductsConfirm = '';

        Notification::make()
            ->title('Товары удалены')
            ->body('Удалено товаров: ' . $count . '.')
            ->success()
            ->send();
    }

    public function openClearCategoriesForm(): void
    {
        $this->showClearCategoriesForm = true;
        $this->clearCategoriesConfirm = '';
    }

    public function cancelClearCategoriesForm(): void
    {
        $this->showClearCategoriesForm = false;
        $this->clearCategoriesConfirm = '';
    }

    public function clearCategories(): void
    {
        if (!$this->clearCategoriesConfirm || trim($this->clearCategoriesConfirm) !== self::CONFIRMATION_WORD) {
            Notification::make()
                ->title('Ошибка')
                ->body('Введите точно: ' . self::CONFIRMATION_WORD)
                ->danger()
                ->send();

            return;
        }

        $service = app(ClearCatalogService::class);
        $count = $service->clearCategoriesOnly();

        $this->showClearCategoriesForm = false;
        $this->clearCategoriesConfirm = '';

        Notification::make()
            ->title('Категории удалены')
            ->body('Удалено категорий: ' . $count . '.')
            ->success()
            ->send();
    }

    public function openClearAttributesForm(): void
    {
        $this->showClearAttributesForm = true;
        $this->clearAttributesConfirm = '';
    }

    public function cancelClearAttributesForm(): void
    {
        $this->showClearAttributesForm = false;
        $this->clearAttributesConfirm = '';
    }

    public function clearAllAttributes(): void
    {
        if (!$this->clearAttributesConfirm || trim($this->clearAttributesConfirm) !== self::CONFIRMATION_WORD) {
            Notification::make()
                ->title('Ошибка')
                ->body('Введите точно: ' . self::CONFIRMATION_WORD)
                ->danger()
                ->send();

            return;
        }

        try {
            $service = app(ClearAttributesService::class);
            $count = $service->clearAll();

            $this->showClearAttributesForm = false;
            $this->clearAttributesConfirm = '';

            Notification::make()
                ->title('Характеристики удалены')
                ->body('Удалено характеристик: ' . $count . '.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->notifyError('очистка характеристик', $e);
        }
    }

    public function queueRestart(): void
    {
        try {
            Artisan::call('queue:restart');
            Notification::make()
                ->title('Очереди')
                ->body('Команда перезапуска воркеров отправлена. Воркеры завершат текущие задачи и перезапустятся.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->notifyError('queue:restart', $e);
        }
    }

    public function queueFlush(): void
    {
        try {
            Artisan::call('queue:flush');
            Notification::make()
                ->title('Очереди')
                ->body('Список проваленных заданий очищен.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->notifyError('queue:flush', $e);
        }
    }

    public function queueClear(): void
    {
        try {
            Artisan::call('queue:clear', ['--force' => true]);
            Notification::make()
                ->title('Очереди')
                ->body('Очередь заданий очищена.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->notifyError('queue:clear', $e);
        }
    }

    public function cacheClear(): void
    {
        try {
            Artisan::call('cache:clear');
            Notification::make()
                ->title('Кэш')
                ->body('Кэш приложения очищен.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->notifyError('cache:clear', $e);
        }
    }

    public function configClear(): void
    {
        try {
            Artisan::call('config:clear');
            Notification::make()
                ->title('Кэш')
                ->body('Кэш конфигурации очищен.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->notifyError('config:clear', $e);
        }
    }

    public function viewClear(): void
    {
        try {
            Artisan::call('view:clear');
            Notification::make()
                ->title('Кэш')
                ->body('Скомпилированные представления удалены.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->notifyError('view:clear', $e);
        }
    }

    public function routeClear(): void
    {
        try {
            Artisan::call('route:clear');
            Notification::make()
                ->title('Кэш')
                ->body('Кэш маршрутов очищен.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->notifyError('route:clear', $e);
        }
    }

    public function saveWarehouseAccountingSettings(): void
    {
        $settings = ProductStockSettings::getInstance();
        $settings->warehouse_accounting_enabled = $this->warehouseAccountingEnabled;
        $settings->fallback_to_first_warehouse = $this->fallbackToFirstWarehouse;
        $settings->save();

        Notification::make()
            ->title('Настройки складского учета сохранены')
            ->success()
            ->send();
    }

    public function clearAllCaches(): void
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            Notification::make()
                ->title('Кэш')
                ->body('Весь кэш очищен (cache, config, view, route).')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->notifyError('очистка кэша', $e);
        }
    }

    private function notifyError(string $operation, \Throwable $e): void
    {
        Notification::make()
            ->title('Ошибка: ' . $operation)
            ->body($e->getMessage())
            ->danger()
            ->send();
    }

    public function getView(): string
    {
        return 'filament.pages.service-tools-page';
    }
}
