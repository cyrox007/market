<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogCardAuditRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['status', 'checked_count', 'affected_count', 'started_at', 'finished_at', 'files_checked_at', 'error'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'files_checked_at' => 'datetime',
        'checked_count' => 'integer',
        'affected_count' => 'integer',
    ];

    public static function latestCompleted(): ?self
    {
        return static::query()->where('status', 'completed')->latest('id')->first();
    }
}
