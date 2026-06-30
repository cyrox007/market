<?php

namespace App\Contracts\Models;

interface PageableContract
{
    /**
     * Get full URL path for the model.
     */
    public function getFullPathAttribute(): ?string;

    /**
     * Get root path constant.
     */
    public static function getRootPath(): string;

    /**
     * Get active status constant.
     * Can be bool (true/false) or string (e.g., 'published').
     */
    public static function getActiveConstant(): bool|string;

    /**
     * Get the name of the active field.
     */
    public static function getActiveFieldName(): string;

    /**
     * Get the name of the sort field.
     */
    public static function getSortFieldName(): string;
}
