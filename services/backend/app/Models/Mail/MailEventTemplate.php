<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailEventTemplate extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\MailEventTemplateFactory::new();
    }

    protected $fillable = [
        'mail_event_id',
        'name',
        'subject',
        'body',
        'variables',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the mail event that owns the template.
     */
    public function mailEvent(): BelongsTo
    {
        return $this->belongsTo(MailEvent::class);
    }

    /**
     * Get the logs for this template.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MailEventLog::class);
    }

    /**
     * Replace variables in subject and body.
     *
     * @param array<string, mixed> $variables
     */
    public function replaceVariables(array $variables): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $subject = str_replace($placeholder, (string) $value, $subject);
            $body = str_replace($placeholder, (string) $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Если устанавливаем шаблон как default, снимаем default с других шаблонов этого события
        static::saving(function ($template) {
            if ($template->is_default && $template->mail_event_id) {
                static::where('mail_event_id', $template->mail_event_id)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
