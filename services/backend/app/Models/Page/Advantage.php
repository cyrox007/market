<?php

namespace App\Models\Page;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Advantage extends Model
{
    use HasFactory;

    protected $fillable = [
        'about_page_id',
        'title',
        'description',
        'icon',
        'color',
        'priority',
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    /**
     * Get the about page that owns the advantage.
     */
    public function aboutPage(): BelongsTo
    {
        return $this->belongsTo(AboutPage::class);
    }
}




