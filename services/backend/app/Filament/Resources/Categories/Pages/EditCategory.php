<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Jobs\RunOzonCategoryImportJob;
use App\Models\OzonCategoryImportRun;
use App\Services\Catalog\Integrations\Ozon\OzonCategoryImportService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importOzon')
                ->label('Импорт из Ozon')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->modalHeading('Импорт товаров из Ozon')
                ->modalDescription('Файл будет импортирован именно в эту категорию. Артикул Ozon используется как код товара 1С.')
                ->modalSubmitActionLabel('Запустить импорт')
                ->form([
                    Placeholder::make('last_import')
                        ->label('Последний импорт')
                        ->content(fn () => $this->renderLastOzonImport()),

                    FileUpload::make('file')
                        ->label('Excel-файл Ozon')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(51200)
                        ->storeFiles(false)
                        ->required()
                        ->live()
                        ->helperText('Поддерживается шаблон Ozon .xlsx/.xls. Перед запуском ниже появится предварительная проверка.'),

                    Placeholder::make('preview')
                        ->label('Предварительная проверка')
                        ->content(fn ($get) => $this->renderOzonPreview($get('file'))),
                ])
                ->action(function (array $data): void {
                    $file = $this->extractTemporaryUpload($data['file'] ?? null);
                    if ($file === null) {
                        Notification::make()
                            ->title('Файл не выбран')
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        $preview = app(OzonCategoryImportService::class)->preview($file->getRealPath(), $this->record);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Не удалось прочитать файл Ozon')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

                    if ($preview['errors'] !== []) {
                        Notification::make()
                            ->title('Импорт остановлен проверкой')
                            ->body(implode(' ', array_slice($preview['errors'], 0, 3)))
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }

                    $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
                    $storedPath = $file->storeAs(
                        'imports/ozon/categories/' . $this->record->id,
                        now()->format('Ymd_His') . '_' . Str::uuid() . '.' . $extension,
                        'local',
                    );

                    if (! is_string($storedPath) || $storedPath === '') {
                        Notification::make()
                            ->title('Не удалось сохранить файл')
                            ->danger()
                            ->send();
                        return;
                    }

                    $run = OzonCategoryImportRun::query()->create([
                        'category_id' => $this->record->id,
                        'user_id' => auth()->id(),
                        'source_filename' => $file->getClientOriginalName(),
                        'stored_path' => $storedPath,
                        'status' => OzonCategoryImportRun::STATUS_PENDING,
                        'total_rows' => $preview['total_rows'],
                        'summary' => $preview,
                    ]);

                    RunOzonCategoryImportJob::dispatch($run->id);

                    Notification::make()
                        ->title('Импорт Ozon поставлен в очередь')
                        ->body(sprintf(
                            'Строк: %d. Новых товаров: %d. Обновлений: %d. Вариативных групп: %d.',
                            $preview['total_rows'],
                            $preview['new_products'],
                            $preview['existing_products'],
                            $preview['variable_groups'],
                        ))
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }

    private function renderLastOzonImport(): HtmlString|string
    {
        $run = OzonCategoryImportRun::query()
            ->where('category_id', $this->record->id)
            ->latest('id')
            ->first();

        if ($run === null) {
            return 'Импорты Ozon для этой категории ещё не запускались.';
        }

        $status = match ($run->status) {
            OzonCategoryImportRun::STATUS_PENDING => 'в очереди',
            OzonCategoryImportRun::STATUS_RUNNING => 'выполняется',
            OzonCategoryImportRun::STATUS_SUCCESS => 'завершён',
            OzonCategoryImportRun::STATUS_FAILED => 'ошибка',
            default => $run->status,
        };

        $parts = [
            '<strong>' . e($run->source_filename ?: 'Файл Ozon') . '</strong>',
            'Статус: ' . e($status),
        ];

        if ($run->status === OzonCategoryImportRun::STATUS_SUCCESS) {
            $parts[] = sprintf(
                'создано %d, обновлено %d, новых родительских карточек %d, фото поставлено в очередь %d',
                (int) $run->created_products,
                (int) $run->updated_products,
                (int) $run->created_parents,
                (int) $run->images_queued,
            );
        }

        if ($run->status === OzonCategoryImportRun::STATUS_FAILED && $run->error_message) {
            $parts[] = '<span class="text-danger-600 dark:text-danger-400">' . e($run->error_message) . '</span>';
        }

        $time = $run->finished_at ?? $run->started_at ?? $run->created_at;
        if ($time) {
            $parts[] = 'Запуск: ' . e($time->format('d.m.Y H:i'));
        }

        return new HtmlString('<div class="space-y-1 text-sm"><div>' . implode('</div><div>', $parts) . '</div></div>');
    }

    private function renderOzonPreview(mixed $state): HtmlString|string
    {
        $file = $this->extractTemporaryUpload($state);
        if ($file === null) {
            return 'Выберите файл — система проверит товары, дубли и группировку до запуска импорта.';
        }

        try {
            $preview = app(OzonCategoryImportService::class)->preview($file->getRealPath(), $this->record);
        } catch (\Throwable $e) {
            return new HtmlString(
                '<div class="text-sm text-danger-600 dark:text-danger-400">'
                . e($e->getMessage())
                . '</div>',
            );
        }

        $html = '<div class="space-y-2 text-sm">'
            . '<div><strong>Строк товаров:</strong> ' . (int) $preview['total_rows'] . '</div>'
            . '<div><strong>Будет создано:</strong> ' . (int) $preview['new_products'] . '</div>'
            . '<div><strong>Будет обновлено:</strong> ' . (int) $preview['existing_products'] . '</div>'
            . '<div><strong>Вариативных групп:</strong> ' . (int) $preview['variable_groups']
            . ' (' . (int) $preview['variable_offers'] . ' торговых предложений)</div>';

        if ($preview['errors'] !== []) {
            $html .= '<div class="mt-3 rounded-lg border border-danger-200 bg-danger-50 p-3 text-danger-700 dark:border-danger-800 dark:bg-danger-950/30 dark:text-danger-300">'
                . '<strong>Импорт нельзя запустить:</strong><ul class="mt-1 list-disc pl-5">';
            foreach (array_slice($preview['errors'], 0, 8) as $error) {
                $html .= '<li>' . e($error) . '</li>';
            }
            $html .= '</ul></div>';
        } else {
            $html .= '<div class="mt-3 text-success-600 dark:text-success-400">Проверка пройдена. Можно запускать импорт.</div>';
        }

        return new HtmlString($html . '</div>');
    }

    private function extractTemporaryUpload(mixed $state): ?TemporaryUploadedFile
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state;
        }

        if (is_array($state)) {
            foreach ($state as $item) {
                if ($item instanceof TemporaryUploadedFile) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_category.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_category.title');
    }
}
