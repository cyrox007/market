<?php

namespace App\Models\Pricing;

use App\Models\Traits\InvalidatesFulfillment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class PricingCustomerGroup extends Model
{
    use InvalidatesFulfillment;

    protected $fillable = ['name', 'user_ids'];

    protected $casts = ['user_ids' => 'array'];

    protected static function booted(): void
    {
        static::saving(fn (self $group) => Validator::make($group->attributesToArray(), ['name' => 'required|string|max:255', 'user_ids' => 'required|array|min:1|max:500', 'user_ids.*' => 'integer|distinct|exists:users,id'])->validate());
    }
}
