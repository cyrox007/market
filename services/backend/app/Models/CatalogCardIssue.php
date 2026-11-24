<?php

namespace App\Models;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogCardIssue extends Model
{
    public $timestamps = false;

    protected $fillable = ['run_id', 'product_id', 'critical_count', 'warning_count', 'reasons', 'checked_at'];

    protected $casts = [
        'reasons' => 'array',
        'checked_at' => 'datetime',
        'critical_count' => 'integer',
        'warning_count' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CatalogCardAuditRun::class, 'run_id');
    }

    public function scopeFromLatestCompleted($query)
    {
        return $query->where('run_id', CatalogCardAuditRun::query()
            ->select('id')->where('status', 'completed')->orderByDesc('id')->limit(1));
    }

    public function getReasonLabelsAttribute(): array
    {
        return collect($this->reasons ?? [])->pluck('label')->all();
    }
}
