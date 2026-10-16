<?php

namespace App\Services\Catalog\Transfer;

use DOMDocument;
use DOMXPath;

/** Read-only parser. Breadcrumbs are evidence, not a complete category membership list. */
class OpenCartCardParser
{
    public const HOST = 'svetofor-mebel.ru';

    public function parse(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        // The old storefront theme uses .content-container; standard OpenCart uses #content.
        $scope = '//*[@id="content" or contains(concat(" ", normalize-space(@class), " "), " content-container ")]';
        $images = [];
        foreach ($xpath->query($scope.'//*[contains(concat(" ", normalize-space(@class), " "), " thumbnails ")]//a[@href]') as $link) {
            $url = $this->imageUrl($link->getAttribute('href'));
            if ($url !== null) {
                $images[] = $url;
            }
        }
        $categories = [];
        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " breadcrumb ")]//a[@href]') as $link) {
            parse_str((string) parse_url(html_entity_decode($link->getAttribute('href')), PHP_URL_QUERY), $query);
            if (($query['route'] ?? '') === 'product/category' && preg_match('/^\d+(?:_\d+)*$/', (string) ($query['path'] ?? ''))) {
                $categories[] = ['path' => $query['path'], 'name' => trim($link->textContent)];
            }
        }
        $navigation = [];
        foreach ($xpath->query('//a[contains(@href, "product/category") and @href]') as $link) {
            parse_str((string) parse_url(html_entity_decode($link->getAttribute('href')), PHP_URL_QUERY), $query);
            $path = (string) ($query['path'] ?? '');
            if (($query['route'] ?? '') === 'product/category' && preg_match('/^\d+(?:_\d+)*$/', $path)) {
                $parts = explode('_', $path);
                $navigation[end($parts)][] = $path;
            }
        }

        return ['name' => trim($xpath->evaluate('string('.$scope.'//h1[1])')),
            'images' => array_values(array_unique($images)), 'category_trail' => $categories,
            'category_membership_complete' => false, 'category_navigation' => $navigation];
    }

    public function imageUrl(string $value): ?string
    {
        $value = html_entity_decode(trim($value));
        if (str_starts_with($value, '/image/')) {
            $value = 'https://'.self::HOST.$value;
        }
        $parts = parse_url($value);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== self::HOST
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || ! str_starts_with($parts['path'] ?? '', '/image/')
            || str_contains(rawurldecode($parts['path'] ?? ''), '..')) {
            return null;
        }

        return $value;
    }
}
