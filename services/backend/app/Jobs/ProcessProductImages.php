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
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;

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

        // Создаём временную папку, если её нет
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $client = new Client([
            'timeout' => 30,
            'verify' => false,
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
        ]);

        if ($this->mainPhotoUrl) {
            $this->downloadAndAttach($product, $client, $this->mainPhotoUrl, 'main');
        }

        foreach ($this->additionalPhotoUrls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $this->downloadAndAttach($product, $client, $url, 'additional');
            }
        }
    }

    private function downloadAndAttach($product, Client $client, string $url, string $collection): void
    {
        try {
            $response = $client->get($url);
            if ($response->getStatusCode() !== 200) {
                Log::warning("Не удалось скачать $url, статус {$response->getStatusCode()}");
                return;
            }

            $content = $response->getBody()->getContents();
            $extension = $this->getExtensionFromUrl($url) ?: 'jpg';
            $filename = uniqid() . '.' . $extension;

            $tempPath = storage_path("app/temp/$filename");
            file_put_contents($tempPath, $content);

            $product->addMedia($tempPath)
                ->setName($filename)
                ->setFileName($filename)
                ->toMediaCollection($collection);

            @unlink($tempPath);
        } catch (\Exception $e) {
            Log::error("Ошибка при скачивании $url: " . $e->getMessage());
        }
    }

    private function getExtensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return pathinfo($path, PATHINFO_EXTENSION);
    }
}