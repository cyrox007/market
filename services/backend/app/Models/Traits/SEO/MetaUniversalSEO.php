<?php

namespace App\Models\Traits\SEO;

use RalphJSmit\Laravel\SEO\Support\SEOData;

trait MetaUniversalSEO
{
    /**
     * Get site suffix for SEO titles.
     */
    protected function getSeoSiteSuffix(): string
    {
        return ' – ' . config('app.name', 'Laravel');
    }

    /**
     * Get site name.
     */
    protected function getSiteName(): string
    {
        return config('app.name', 'Laravel');
    }

    /**
     * Get default SEO image path.
     */
    protected function getDefaultSeoImage(): string
    {
        return '/images/logo.png'; // Можно изменить на реальный путь к логотипу
    }

    /**
     * Get site domain.
     */
    protected function getSiteDomain(): string
    {
        return env('SEO_SITE_DOMAIN', config('app.url', 'https://example.com'));
    }

    /**
     * Get dynamic SEO data for the model.
     */
    public function getDynamicSEOData(): SEOData
    {
        return new SEOData(
            title: $this->generateSeoTitle(),
            description: $this->generateSeoDescription(),
            image: $this->generateSeoImage(),
            author: $this->generateAuthor(),
            canonical_url: $this->generateSeoCanonical(),
            locale: 'ru_RU',
        );
    }

    /**
     * Generate SEO author.
     */
    protected function generateAuthor(): ?string
    {
        return $this->getSiteName();
    }

    /**
     * Generate SEO title.
     */
    protected function generateSeoTitle(): string
    {
        $titleParts = [];

        if (!empty($this->name)) {
            $titleParts[] = $this->name;
        }

        // Для категорий можно добавить родительскую категорию
        if (method_exists($this, 'parent') && $this->parent) {
            $titleParts[] = $this->parent->name;
        }

        // Если есть константа SEO_NAME в модели
        if (defined(static::class . '::SEO_NAME')) {
            $titleParts[] = static::SEO_NAME;
        }

        $title = implode(' – ', $titleParts);

        return $title . $this->getSeoSiteSuffix();
    }

    /**
     * Generate SEO description.
     */
    protected function generateSeoDescription(): ?string
    {
        // Пробуем получить описание из разных полей
        if (!empty($this->description)) {
            $text = is_string($this->description)
                ? strip_tags($this->description)
                : $this->description;
            return mb_substr($text, 0, 310);
        }

        if (!empty($this->excerpt)) {
            $text = is_string($this->excerpt)
                ? strip_tags($this->excerpt)
                : $this->excerpt;
            return mb_substr($text, 0, 310);
        }

        if (!empty($this->short_text)) {
            $text = is_string($this->short_text)
                ? strip_tags($this->short_text)
                : $this->short_text;
            return mb_substr($text, 0, 310);
        }

        if (!empty($this->text)) {
            $text = is_string($this->text)
                ? strip_tags($this->text)
                : $this->text;
            return mb_substr($text, 0, 310);
        }

        return null;
    }

    /**
     * Generate SEO image URL.
     */
    protected function generateSeoImage(): ?string
    {
        // Проверяем наличие метода getFirstMediaUrl из Spatie Media Library
        if (method_exists($this, 'getFirstMediaUrl')) {
            // Для Product: пробуем коллекции 'images' и 'gallery'
            if (method_exists($this, 'getMedia')) {
                $photo = $this->getFirstMediaUrl('images')
                    ?: $this->getFirstMediaUrl('gallery')
                    ?: $this->getFirstMediaUrl('image')
                    ?: $this->getFirstMediaUrl('photo')
                    ?: $this->getFirstMediaUrl('icon');

                if ($photo) {
                    // Если URL относительный, делаем его абсолютным
                    if (str_starts_with($photo, '/')) {
                        return rtrim($this->getSiteDomain(), '/') . $photo;
                    }
                    return $photo;
                }
            }
        }

        // Возвращаем дефолтное изображение
        $defaultImage = $this->getDefaultSeoImage();
        if (str_starts_with($defaultImage, '/')) {
            return rtrim($this->getSiteDomain(), '/') . $defaultImage;
        }

        return $defaultImage;
    }

    /**
     * Generate SEO canonical URL.
     */
    protected function generateSeoCanonical(): ?string
    {
        if (method_exists($this, 'getFullPathAttribute') || isset($this->full_path)) {
            $path = $this->full_path ?? $this->getFullPathAttribute();
            if ($path) {
                return rtrim($this->getSiteDomain(), '/') . $path;
            }
        }

        // Если есть slug, формируем URL из него
        if (!empty($this->slug)) {
            $basePath = method_exists($this, 'getRootPath')
                ? $this->getRootPath()
                : '';
            return rtrim($this->getSiteDomain(), '/') . $basePath . '/' . $this->slug;
        }

        return null;
    }
}

