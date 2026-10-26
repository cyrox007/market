<?php

namespace App\Services\Product;

use Illuminate\Support\Str;

/** HTML remains the storage format; plain-text line breaks are made explicit. */
class ProductDescriptionHtml
{
    public function forEditor(?string $value): string
    {
        $value = $value ?? '';
        if (! $this->hasContent($value)) {
            return '';
        }

        if ($value === strip_tags($value)) {
            return '<p>'.nl2br(e($value), false).'</p>';
        }

        return $this->sanitize($value);
    }

    public function sanitize(?string $value): string
    {
        $html = Str::sanitizeHtml($value ?? '');

        return $this->containsContent($html) ? $html : '';
    }

    public function hasContent(?string $value): bool
    {
        return $this->containsContent(Str::sanitizeHtml($value ?? ''));
    }

    private function containsContent(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\p{Z}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $text);

        return $text !== null && $text !== ''
            || preg_match('/<(?:img|video|audio|iframe)\b[^>]*\bsrc\s*=\s*["\']?[^"\'\s>]+/iu', $html) === 1;
    }
}
