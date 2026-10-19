<?php

namespace App\Services\Catalog\Transfer;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TransferSources
{
    private static ?int $runId = null;
    private static ?string $code = null;

    public static function track(?int $runId, ?string $code): void
    {
        self::$runId = $runId;
        self::$code = $code;
    }

    public function image(string $url): array
    {
        if (app(OpenCartCardParser::class)->imageUrl($url) === null) {
            throw new \InvalidArgumentException('Изображение должно находиться на старом сайте.');
        }
        $bytes = $this->fetch($url);
        $info = @getimagesizefromstring($bytes);
        $extension = match ($info['mime'] ?? '') {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => null
        };
        if ($extension === null || $info[0] * $info[1] > 40000000) {
            throw new \RuntimeException('Неподдерживаемое или слишком большое изображение.');
        }

        return ['bytes' => $bytes, 'extension' => $extension];
    }

    public function card(int $id, ?string $categoryPath = null): array
    {
        if ($categoryPath !== null && ! preg_match('/^\d+(?:_\d+)*$/', $categoryPath)) {
            throw new \InvalidArgumentException('Недопустимый путь категории.');
        }

        return Cache::remember('catalog-transfer:card:'.$id.':'.($categoryPath ?? ''), 3600, function () use ($id, $categoryPath) {
            $html = $this->fetch('https://'.OpenCartCardParser::HOST.'/index.php', [
                'route' => 'product/product', 'product_id' => $id,
                ...($categoryPath === null ? [] : ['path' => $categoryPath]),
            ]);
            $card = app(OpenCartCardParser::class)->parse($html);
            if ($card['name'] === '') {
                throw new \RuntimeException('Старая карточка не найдена или её разметка не распознана.');
            }

            return $card;
        });
    }

    public function api(string $code): array
    {
        $config = app(TransferApiSettings::class)->config();
        $token = (string) ($config['api_key'] ?? '');
        $base = rtrim((string) ($config['base_url'] ?? ''), '/');
        if ($base === '') {
            return ['status' => 'not_configured'];
        }
        if (parse_url($base, PHP_URL_SCHEME) !== 'https') {
            return ['status' => 'https_required'];
        }
        $body = $this->fetch($base.'/api/v1/integration/1c/v2/cache/products/'.rawurlencode($code), [], $token, true);
        if ($body === null) {
            return ['status' => 'not_found'];
        }
        $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($data) || (string) ($data['externalId'] ?? '') !== $code) {
            throw new \RuntimeException('API не подтвердило код 1С товара.');
        }

        return ['status' => 'found', 'external_id' => $code, 'payload' => $data['payload'] ?? [], 'price' => $data['price'] ?? null];
    }

    public function enrichment(string $code): array
    {
        $config = app(TransferApiSettings::class)->config();
        $base = rtrim((string) ($config['base_url'] ?? ''), '/');
        $key = 'catalog-transfer:enrich:'.hash('sha256', $base.($config['api_key'] ?? '').$code);
        // A former missing-token result must not prevent a real request after access changes.
        if (\Illuminate\Support\Arr::get(Cache::get($key.':detail'), 'status') === 'not_configured') {
            Cache::forget($key.':detail');
        }
        $detail = Cache::remember($key.':detail', 300, fn () => $this->api($code));
        if ($detail['status'] !== 'found') {
            return $detail;
        }
        $result = $detail;
        foreach (['characteristics', 'stocks'] as $section) {
            $result[$section] = Cache::remember($key.':'.$section, 300, function () use ($base, $code, $config, $section): array {
                $body = $this->fetch($base.'/api/v1/integration/1c/v2/cache/products/'.rawurlencode($code).'/'.$section, [], (string) ($config['api_key'] ?? ''));
                $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
                foreach (['data', 'items', $section, 'results'] as $wrapper) {
                    if (is_array($data) && isset($data[$wrapper]) && is_array($data[$wrapper])) {
                        $data = $data[$wrapper];
                        break;
                    }
                }
                if (! is_array($data) || ! array_is_list($data)) {
                    throw new \RuntimeException('Некорректный снимок '.$section.' из API.');
                }
                foreach ($data as $item) {
                    if (! is_array($item)) {
                        throw new \RuntimeException('Некорректная строка '.$section.'.');
                    }
                    if ($section === 'stocks') {
                        $id = $item['stockId'] ?? $item['stock_id'] ?? $item['warehouseId'] ?? $item['warehouse_id'] ?? '';
                        $quantity = $item['count'] ?? $item['quantity'] ?? $item['stock'] ?? null;
                        if (trim((string) $id) === '' || ! is_numeric($quantity) || ! is_finite((float) $quantity) || (float) $quantity < 0) {
                            throw new \RuntimeException('Некорректный склад или количество; старые остатки сохранены.');
                        }
                    }
                }

                return $data;
            });
        }

        return $result;
    }

    private function fetch(string $url, array $query = [], string $token = '', bool $allowMissing = false): ?string
    {
        $key = 'catalog-transfer:rate:'.parse_url($url, PHP_URL_HOST);
        if (! Cache::add($key, true, 1)) {
            throw new TransferRetry(2, false);
        }
        $isApi = str_contains($url, '/integration/1c/');
        $record = function (string $message, string $level = 'info') use ($isApi): void {
            if ($isApi && self::$runId !== null) {
                app(CatalogTransfer::class)->log(self::$runId, $message, self::$code, $level);
            }
        };
        $record('API запрос: GET '.$url);
        try {
            $response = Http::connectTimeout(10)->timeout(25)
                ->withOptions(['allow_redirects' => false, 'on_headers' => function ($response): void {
                    if ((int) $response->getHeaderLine('Content-Length') > 8 * 1024 * 1024) {
                        throw new \RuntimeException('Ответ источника слишком большой.');
                    }
                }, 'progress' => function ($total, $downloaded): void {
                    if ($downloaded > 8 * 1024 * 1024) {
                        throw new \RuntimeException('Ответ источника слишком большой.');
                    }
                }])->when(parse_url($url, PHP_URL_HOST) === OpenCartCardParser::HOST,
                    fn ($request) => $request->withCookies(['beget' => 'begetok'], OpenCartCardParser::HOST))
                ->when($token !== '', fn ($request) => $request->withToken($token))->get($url, $query);
        } catch (ConnectionException) {
            $record('API ответ: нет соединения; повтор через 30 секунд.', 'warning');
            throw new TransferRetry(30);
        }
        $record('API ответ: HTTP '.$response->status().' · '.parse_url($url, PHP_URL_PATH), $response->successful() ? 'info' : 'warning');
        if ($response->status() === 429) {
            $after = $response->header('Retry-After');
            $delay = is_numeric($after) ? (int) $after : max(1, (strtotime($after ?: '') ?: time() + 60) - time());
            throw new TransferRetry(min(86400, max(1, $delay)) + 1);
        }
        if ($response->serverError()) {
            throw new TransferRetry(30);
        }
        if ($response->status() === 404 && $allowMissing) {
            return null;
        }
        if (! $response->successful()) {
            throw new \RuntimeException('Источник вернул HTTP '.$response->status().'.');
        }

        return $response->body();
    }
}
