<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class PublicAuthSettings extends Model
{
    protected $table = 'public_auth_settings';

    protected $fillable = ['id', 'registration_enabled', 'login_enabled'];

    protected $attributes = ['registration_enabled' => true, 'login_enabled' => true];

    protected $casts = ['registration_enabled' => 'boolean', 'login_enabled' => 'boolean'];

    /** Fresh read: disabling access must not wait for a shared cache to expire. */
    public static function current(): self
    {
        return static::find(1) ?? new static;
    }
}
