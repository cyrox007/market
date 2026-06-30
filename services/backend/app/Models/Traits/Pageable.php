<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Pageable
{
    /**
     * Scope a query to only include active records.
     */
    public function scopeActive(Builder $query): Builder
    {
        $activeConstant = static::getActiveConstant();
        $activeField = static::getActiveFieldName();

        return $query->where($activeField, $activeConstant);
    }

    /**
     * Scope a query to order by sort field.
     */
    public function scopeOrdered(Builder $query, string $direction = 'asc'): Builder
    {
        $sortField = static::getSortFieldName();

        return $query->orderBy($sortField, $direction);
    }
}
