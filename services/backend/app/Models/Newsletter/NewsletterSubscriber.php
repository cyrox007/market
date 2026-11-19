<?php

namespace App\Models\Newsletter;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $subscriber): void {
            if ($subscriber->wasChanged('is_active') || $subscriber->wasRecentlyCreated) {
                \App\Models\User::where('email', $subscriber->email)->update(['email_promotions' => $subscriber->is_active]);
            }
        });
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

}
