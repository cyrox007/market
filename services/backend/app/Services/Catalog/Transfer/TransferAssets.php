<?php

namespace App\Services\Catalog\Transfer;

use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;

/** One image per checkpoint; old media and category memberships are never deleted. */
class TransferAssets
{
    public function step(int $runId): int
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \DomainException('Перенос разрешён только локально.');
        }
        $row = DB::table('catalog_transfer_rows')->where('run_id', $runId)->whereNotNull('table_applied_at')->where('assets_done', false)->orderBy('id')->first();
        if (! $row) {
            return -1;
        }
        $state = json_decode($row->assets_result ?? '{}', true);
        $product = Product::find($row->applied_product_id);
        if (! $product) {
            $this->save($row, ['warnings' => ['Товар удалён после применения таблицы.']], true);

            return 0;
        }
        $source = json_decode($row->source ?? '{}', true);
        $client = app(TransferSources::class);
        try {
            if (! ($state['initialized'] ?? false)) {
                $state['images'] = array_values(array_unique($source['images'] ?? []));
                $state['completed'] = [];
                $state['warnings'] = [];
                $state['category_index'] = 0;
                if ($row->old_id) {
                    try {
                        $card = $client->card((int) $row->old_id);
                        $state['navigation'] = $card['category_navigation'] ?? [];
                        $state['images'] = array_values(array_unique(array_merge($state['images'], $card['images'])));
                    } catch (TransferRetry $exception) {
                        throw $exception;
                    } catch (\Throwable) {
                        $state['warnings'][] = 'Не удалось прочитать старую карточку; используются изображения из таблицы.';
                    }
                }
                $state['initialized'] = true;
                $this->save($row, $state);

                return 1;
            }
            $paths = $source['category_paths'] ?? [];
            $index = $state['category_index'] ?? 0;
            if ($row->old_id && isset($paths[$index])) {
                $path = $paths[$index];
                $options = array_values(array_unique($state['navigation'][$path] ?? [$path]));
                usort($options, fn ($a, $b) => count(explode('_', $b)) <=> count(explode('_', $a)));
                $path = $options[0];
                try {
                    $card = $client->card((int) $row->old_id, $path);
                    $matches = app(TransferCategoryMatcher::class)->candidates($card['category_trail']);
                    $assigned = false;
                    if (count($matches) === 1 && count($card['category_trail']) === count(explode('_', $path))) {
                        $product->taxons()->syncWithoutDetaching([$matches[0]['id']]);
                        $state['categories'][] = $matches[0]['id'];
                        $assigned = true;
                    } else {
                        $state['warnings'][] = 'Категория '.$path.': требуется ручное сопоставление.';
                    }
                    $state['category_report'][] = ['old_path' => $path, 'old_trail' => $card['category_trail'], 'matches' => $matches, 'assigned' => $assigned];
                } catch (TransferRetry $exception) {
                    throw $exception;
                } catch (\Throwable) {
                    $state['warnings'][] = 'Категория '.$path.': ошибка чтения, требуется проверка.';
                    $state['category_report'][] = ['old_path' => $path, 'old_trail' => [], 'matches' => [], 'assigned' => false, 'error' => true];
                }
                $state['category_index'] = $index + 1;
                $this->save($row, $state);

                return 1;
            }
            foreach ($state['images'] as $url) {
                if (in_array($url, $state['completed'], true)) {
                    continue;
                }
                try {
                    if (! $product->media()->where('custom_properties->catalog_transfer_source', $url)->exists()) {
                        $image = $client->image($url);
                        $contentHash = hash('sha256', $image['bytes']);
                        if (! $product->media()->where('custom_properties->catalog_transfer_hash', $contentHash)->exists()) {
                            $collection = $product->getFirstMedia('images') === null ? 'images' : 'gallery';
                            $product->addMediaFromString($image['bytes'])->usingFileName(hash('sha256', $url).'.'.$image['extension'])
                                ->withCustomProperties(['catalog_transfer_source' => $url, 'catalog_transfer_hash' => $contentHash])->toMediaCollection($collection);
                        }
                    }
                } catch (TransferRetry $exception) {
                    throw $exception;
                } catch (\Throwable $exception) {
                    report($exception);
                    $state['warnings'][] = 'Не удалось загрузить изображение: '.$url;
                    $state['failed_images'][] = $url;
                }
                $state['completed'][] = $url;
                $this->save($row, $state);

                return 1;
            }
            $product->flushCache();
            $this->save($row, $state, true);
            app(CatalogTransfer::class)->log($runId, 'Изображения и категории проверены. Предупреждений: '.count($state['warnings']).'.', $row->code,
                $state['warnings'] === [] ? 'info' : 'warning');

            return 0;
        } catch (TransferRetry $exception) {
            if (! $exception->countAttempt) {
                return $exception->delay;
            }
            $state['retries'] = ($state['retries'] ?? 0) + 1;
            if ($state['retries'] >= 5) {
                $state['warnings'][] = 'Источник не ответил после 5 попыток; перенос медиа требует проверки.';
                $this->save($row, $state, true);

                return 0;
            }
            $this->save($row, $state);

            return $exception->delay;
        }
    }

    private function save(object $row, array $state, bool $done = false): void
    {
        DB::table('catalog_transfer_rows')->where('id', $row->id)->update([
            'assets_result' => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'assets_done' => $done, 'updated_at' => now(),
        ]);
    }
}
