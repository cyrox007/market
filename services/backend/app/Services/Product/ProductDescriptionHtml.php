<?php

namespace App\Services\Product;

use Illuminate\Support\Str;

/** HTML remains the storage format; plain-text line breaks are made explicit. */
class ProductDescriptionHtml
{
    public function forEditor(?string $value): string
    {
        $value = $value ?? '';
        if ($value === '') {
            return '';
        }

        if ($value === strip_tags($value)) {
            return '<p>'.nl2br(e($value), false).'</p>';
        }

        return $this->sanitize($value);
    }

    public function sanitize(?string $value): string
    {
        return Str::sanitizeHtml($value ?? '');
    }
}
