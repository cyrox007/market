<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ActivityAuditEvent extends Model
{
    public $timestamps = false;
    protected $guarded = ['*'];
    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit records are immutable'));
        static::deleting(fn () => throw new \LogicException('Audit records are immutable'));
    }
}
