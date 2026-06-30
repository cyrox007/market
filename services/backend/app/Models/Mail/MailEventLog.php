<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailEventLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_event_id',
        'mail_event_template_id',
        'recipient_email',
        'subject',
        'body',
        'status',
        'error_message',
        'sent_at',
        'variables',
    ];

    protected $casts = [
        'variables' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the mail event that owns the log.
     */
    public function mailEvent(): BelongsTo
    {
        return $this->belongsTo(MailEvent::class);
    }

    /**
     * Get the template that was used.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MailEventTemplate::class, 'mail_event_template_id');
    }

    /**
     * Mark log as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark log as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
