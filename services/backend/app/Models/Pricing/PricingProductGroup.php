<?php

namespace App\Models\Pricing;

use App\Models\Traits\InvalidatesFulfillment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class PricingProductGroup extends Model
{
    use InvalidatesFulfillment;

    protected $fillable = ['name', 'product_ids'];

    protected $casts = ['product_ids' => 'array'];

    protected static function booted(): void
    {
        static::saving(fn (self $group) => Validator::make($group->attributesToArray(), ['name' => 'required|string|max:255', 'product_ids' => 'required|array|min:1|max:500', 'product_ids.*' => 'integer|distinct|exists:products,id'])->validate());
    }
}
