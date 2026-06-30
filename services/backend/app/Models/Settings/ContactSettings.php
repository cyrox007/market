<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'address',
        'phones',
        'emails',
        'social_networks',
        'map_embed',
        'working_hours',
    ];

    protected $casts = [
        'phones' => 'array',
        'emails' => 'array',
        'social_networks' => 'array',
        'working_hours' => 'array',
    ];

    /**
     * Get the singleton instance of contact settings.
     */
    public static function getInstance(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    /**
     * Get or create the singleton instance.
     */
    public static function instance(): self
    {
        return static::getInstance();
    }
}


