<?php

namespace App\Services\Seo;

use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * Преобразует модель с HasSEO в единый массив SEO для API.
 * Приоритет: поля из админки («SEO настройки»), если заполнены; иначе — динамические из getDynamicSEOData().
 */
class SeoApiTransformer
{
    /**
     * Возвращает массив SEO для API из модели с трейтом HasSEO.
     * Сначала используются поля из связи seo (заполненные в админке), затем подставляются динамические.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model  Модель с HasSEO (Product, Category, Store и т.д.)
     * @return array{title: string|null, description: string|null, image: string|null, canonical_url: string|null, robots: string|null, open_graph_title: string|null, locale: string|null}
     */
    public static function forModel($model): array
    {
        $seoData = self::getMergedSeoData($model);
        if (!$seoData) {
            return self::emptySeoArray();
        }

        $image = $seoData->image;
        if ($image && filter_var($image, FILTER_VALIDATE_URL) === false) {
            $image = secure_url($image);
        }

        return [
            'title' => $seoData->title,
            'description' => $seoData->description,
            'image' => $image,
            'canonical_url' => $seoData->canonical_url ?? $seoData->url,
            'robots' => $seoData->robots,
            'open_graph_title' => $seoData->openGraphTitle ?? $seoData->title,
            'locale' => $seoData->locale,
        ];
    }

    /**
     * Получить SEOData с приоритетом: поля из админки (seo), если заполнены; иначе — из getDynamicSEOData().
     * Для вариации товара берём SEO у родительского товара.
     */
    protected static function getMergedSeoData($model): ?SEOData
    {
        if (!$model) {
            return null;
        }

        // Вариация товара: SEO задаётся на родительском товаре
        if (method_exists($model, 'isVariant') && $model->isVariant() && method_exists($model, 'parentProduct')) {
            $parent = $model->relationLoaded('parentProduct') ? $model->parentProduct : $model->parentProduct()->first();
            if ($parent) {
                return self::getMergedSeoData($parent);
            }
        }

        $dynamic = method_exists($model, 'getDynamicSEOData') ? $model->getDynamicSEOData() : null;
        if (!$dynamic) {
            return null;
        }

        if (!method_exists($model, 'seo')) {
            return $dynamic;
        }

        if ($model->relationLoaded('seo') === false) {
            $model->loadMissing('seo');
        }

        $seo = $model->seo;
        // Реальная запись из БД (заполнена в админке «SEO настройки»)
        if (!$seo || !$seo->exists) {
            return $dynamic;
        }

        $attrs = $seo->getAttributes();
        $title = self::filled($attrs['title'] ?? null) ? $attrs['title'] : $dynamic->title;
        $description = self::filled($attrs['description'] ?? null) ? $attrs['description'] : $dynamic->description;
        $image = self::filled($attrs['image'] ?? null) ? $attrs['image'] : $dynamic->image;
        $canonical_url = self::filled($attrs['canonical_url'] ?? null) ? $attrs['canonical_url'] : $dynamic->canonical_url;
        $robots = self::filled($attrs['robots'] ?? null) ? $attrs['robots'] : $dynamic->robots;

        return new SEOData(
            title: $title,
            description: $description,
            author: $dynamic->author,
            image: $image,
            url: $dynamic->url,
            enableTitleSuffix: true,
            published_time: $dynamic->published_time,
            modified_time: $dynamic->modified_time,
            articleBody: $dynamic->articleBody,
            section: $dynamic->section,
            tags: $dynamic->tags,
            twitter_username: $dynamic->twitter_username,
            schema: $dynamic->schema,
            type: $dynamic->type,
            site_name: $dynamic->site_name,
            favicon: $dynamic->favicon,
            locale: $dynamic->locale,
            robots: $robots,
            canonical_url: $canonical_url,
            openGraphTitle: $title,
            alternates: $dynamic->alternates,
        );
    }

    /** Проверка, что значение заполнено (не пустая строка и не null). */
    protected static function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    /**
     * Пустой массив SEO (одинаковая структура для всех полей null).
     *
     * @return array{title: null, description: null, image: null, canonical_url: null, robots: null, open_graph_title: null, locale: null}
     */
    public static function emptySeoArray(): array
    {
        return [
            'title' => null,
            'description' => null,
            'image' => null,
            'canonical_url' => null,
            'robots' => null,
            'open_graph_title' => null,
            'locale' => null,
        ];
    }
}
