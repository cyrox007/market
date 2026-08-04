<?php

namespace App\Jobs;

use App\Models\Product\Product;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProductImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    protected string $externalId;
    protected ?string $mainPhotoUrl;
    protected array $additionalPhotoUrls;

    public function __construct(string $externalId, ?string $mainPhotoUrl, array $additionalPhotoUrls)
    {
        $this->externalId = $externalId;
        $this->mainPhotoUrl = $mainPhotoUrl;
        $this->additionalPhotoUrls = $additionalPhotoUrls;
    }

    public function handle(): void
    {
        $product = Product::where('external_id', $this->externalId)->first();
        if (!$product) {
            Log::warning("Товар с external_id {$this->externalId} не найден");
            return;
        }
        Log::info("Товар с external_id {$this->externalId} найден", [
            'main_photo' => $this->mainPhotoUrl,
            'additional_photos' => $this->additionalPhotoUrls,
        ]);

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true)) {
            Log::error("Не удалось создать временную папку: $tempDir");
            return;
        }

        $client = new Client([
            'timeout' => 30,
            'verify' => false,
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
        ]);

        if ($this->mainPhotoUrl) {
            Log::info("Загрузка главного фото: " . $this->mainPhotoUrl);
            $this->downloadAndAttach($product, $client, $this->mainPhotoUrl, 'images');
        } else {
            Log::warning("Главное фото отсутствует для товара {$this->externalId}");
        }

        foreach ($this->additionalPhotoUrls as $index => $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Log::warning("Доп. фото #$index невалидный URL: $url");
                continue;
            }
            Log::info("Загрузка доп. фото #$index: $url");
            $this->downloadAndAttach($product, $client, $url, 'gallery');
        }
    }

    private function downloadAndAttach($product, Client $client, string $url, string $collection): void
    {
        Log::info("Вход в downloadAndAttach для {$url} (коллекция: {$collection})");

        try {
            Log::info("Начинаем запрос GET: {$url}");
            $response = $client->get($url);
            Log::info("Получен ответ, статус: " . $response->getStatusCode());

            if ($response->getStatusCode() !== 200) {
                Log::warning("Не удалось скачать $url, статус {$response->getStatusCode()}");
                return;
            }

            $content = $response->getBody()->getContents();
            $extension = $this->getExtensionFromUrl($url) ?: 'jpg';
            $filename = uniqid() . '.' . $extension;
            $tempPath = storage_path("app/temp/$filename");

            Log::info("Сохраняем файл: {$tempPath}, размер: " . strlen($content) . " байт");
            file_put_contents($tempPath, $content);

            if (!file_exists($tempPath)) {
                Log::error("Файл не создан: {$tempPath}");
                return;
            }

            Log::info("Файл записан, вызываем addMedia()");
            $media = $product->addMedia($tempPath)
                ->setName($filename)
                ->setFileName($filename)
                ->toMediaCollection($collection);

            Log::info("Медиа успешно добавлено, ID: " . ($media ? $media->id : 'неизвестно'));

            @unlink($tempPath);
            Log::info("Временный файл удалён: {$tempPath}");
        } catch (\Exception $e) {
            Log::error("ОШИБКА СПАТИЕ для {$url}: " . get_class($e) . " - " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    private function getExtensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return pathinfo($path, PATHINFO_EXTENSION);
    }
}
