<?php

namespace App\Models\Page;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteriorIdeaHotspot extends Model
{
    use HasFactory;

    protected $fillable = [
        'interior_idea_id',
        'product_id',
        'x',
        'y',
        'priority',
    ];

    protected $casts = [
        'x' => 'decimal:2',
        'y' => 'decimal:2',
        'priority' => 'integer',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\InteriorIdeaHotspotFactory::new();
    }

    protected static function boot(): void
    {
        parent::boot();

        $flushParentListCache = static function (InteriorIdeaHotspot $hotspot): void {
            InteriorIdea::flushListCache();
        };

        static::saved($flushParentListCache);
        static::deleted($flushParentListCache);
    }

    /**
     * Get the interior idea that owns this hotspot.
     */
    public function interiorIdea()
    {
        return $this->belongsTo(InteriorIdea::class);
    }

    /**
     * Get the product associated with this hotspot.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
