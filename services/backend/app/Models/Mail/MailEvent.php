<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailEvent extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\MailEventFactory::new();
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the templates for the mail event.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(MailEventTemplate::class);
    }

    /**
     * Get the logs for the mail event.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MailEventLog::class);
    }

    /**
     * Get the default template for this event.
     */
    public function defaultTemplate(): ?MailEventTemplate
    {
        return $this->templates()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get an active template for this event.
     */
    public function getActiveTemplate(): ?MailEventTemplate
    {
        // Сначала пытаемся получить шаблон по умолчанию
        $default = $this->defaultTemplate();
        if ($default) {
            return $default;
        }

        // Если нет шаблона по умолчанию, берем первый активный
        return $this->templates()
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find event by code.
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)
            ->where('is_active', true)
            ->first();
    }
}
