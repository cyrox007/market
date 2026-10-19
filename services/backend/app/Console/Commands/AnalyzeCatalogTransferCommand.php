<?php

namespace App\Console\Commands;

use App\Services\Catalog\Transfer\CatalogTransfer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AnalyzeCatalogTransferCommand extends Command
{
    protected $signature = 'catalog-transfer:analyze {file : Local XLS/XLSX file} {--sheet=Products} {--header=1} {--code=D} {--old-id=A} {--price=P} {--image=N} {--categories=C} {--apply : Создать/обновить товары локально, затем дополнить из API}';

    protected $description = 'Предварительный анализ таблицы OpenCart без изменения товаров';

    public function handle(CatalogTransfer $transfer): int
    {
        if ($this->option('apply') && ! app()->environment(['local', 'testing'])) {
            $this->error('Применение разрешено только локально.');

            return self::FAILURE;
        }
        $path = realpath((string) $this->argument('file'));
        if (! $path || ! is_file($path) || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['xls', 'xlsx']) || filesize($path) > 12 * 1024 * 1024) {
            $this->error('Нужен доступный локальный XLS/XLSX до 12 МиБ.');

            return self::FAILURE;
        }
        $mapping = ['sheet' => $this->option('sheet'), 'header' => $this->option('header'), 'code' => $this->option('code'),
            'old_id' => $this->option('old-id'), 'price' => $this->option('price'), 'image' => $this->option('image'), 'categories' => $this->option('categories')];
        Validator::make($mapping, ['sheet' => 'nullable|string|max:100', 'header' => 'integer|min:1|max:100', 'code' => 'required|regex:/^[A-Z]{1,3}$/',
            'old_id' => 'nullable|regex:/^[A-Z]{1,3}$/', 'price' => 'nullable|regex:/^[A-Z]{1,3}$/', 'image' => 'nullable|regex:/^[A-Z]{1,3}$/', 'categories' => 'nullable|regex:/^[A-Z]{1,3}$/'])->validate();
        $id = DB::transaction(function () use ($path, $mapping): int {
            $id = DB::table('catalog_transfer_runs')->insertGetId(['apply_enabled' => (bool) $this->option('apply'), 'status' => 'running', 'message' => 'Обработка: '.basename($path), 'created_at' => now(), 'updated_at' => now()]);
            $stored = 'catalog-transfer/'.$id.'/source.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $stream = fopen($path, 'rb');
            try {
                Storage::disk('local')->put($stored, $stream);
            } finally {
                fclose($stream);
            }
            DB::table('catalog_transfer_files')->insert(['run_id' => $id, 'name' => basename($path), 'path' => $stored,
                'mapping' => json_encode($mapping, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });
        $transfer->log($id, $this->option('apply') ? 'Локальное применение таблицы и дополнение из API.' : 'Файл сохранён. Начат предварительный анализ; применение отключено.');
        $transfer->enqueue($id);
        $this->info('Обработка №'.$id.' поставлена в очередь catalog-transfer. '.($this->option('apply') ? 'Локальный каталог будет обновлён из таблицы.' : 'Каталог не изменяется.'));

        return self::SUCCESS;
    }
}
