# Расширяемость системы блоков товаров

Система блоков товаров спроектирована для легкого добавления новых типов блоков в будущем.

## Текущая архитектура

### Существующие типы блоков:
1. **ProductFeatureBlock** - блоки фич товаров (доставка, гарантия, сборка, возврат)
2. **ProductDeliveryBlock** - блоки доставки (доставка по городу, в регионы, сборка)

### Структура:
- **Модели**: `App\Models\Product\ProductFeatureBlock`, `App\Models\Product\ProductDeliveryBlock`
- **Миграции**: таблицы блоков + pivot таблицы для связи с категориями и товарами
- **Filament Resources**: CRUD для управления блоками
- **RelationManagers**: управление привязкой блоков к категориям и товарам
- **API**: блоки автоматически включаются в ответ API товара

## Как добавить новый тип блока

### 1. Создать модель

Создайте модель в `app/Models/Product/`, например `ProductWarrantyBlock`:

```php
<?php

namespace App\Models\Product;

use App\Contracts\ProductBlockInterface;
use App\Models\Traits\Cacheable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductWarrantyBlock extends Model implements HasMedia, ProductBlockInterface
{
    use Cacheable, HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title', 'description', 'icon', 'icon_color', 'bg_color', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_warranty_blocks',
            'warranty_block_id',
            'category_id'
        )->withPivot('sort_order')->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_warranty_blocks_pivot',
            'warranty_block_id',
            'product_id'
        )->withPivot('sort_order')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public static function getForProduct(Product $product): Collection
    {
        $cacheKey = "warranty_blocks_product_{$product->id}";
        
        return static::cached($cacheKey, function () use ($product) {
            // Сначала проверяем блоки товара
            $productBlocks = $product->warrantyBlocks()->active()->ordered()->get();
            if ($productBlocks->isNotEmpty()) {
                return $productBlocks;
            }
            
            // Затем блоки категории
            if (!$product->relationLoaded('taxons')) {
                $product->load('taxons');
            }
            
            $taxon = $product->taxons->first();
            if ($taxon) {
                $category = Category::find($taxon->id);
                if ($category) {
                    $categoryBlocks = $category->warrantyBlocks()->active()->ordered()->get();
                    if ($categoryBlocks->isNotEmpty()) {
                        return $categoryBlocks;
                    }
                }
            }
            
            return collect();
        }, 36000);
    }

    public static function getForCategory(Category $category): Collection
    {
        $cacheKey = "warranty_blocks_category_{$category->id}";
        
        return static::cached($cacheKey, function () use ($category) {
            return $category->warrantyBlocks()->active()->ordered()->get();
        }, 36000);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
            ->singleFile();
    }
}
```

### 2. Создать миграции

#### Основная таблица блока:
```php
Schema::create('product_warranty_blocks', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('icon')->nullable();
    $table->string('icon_color')->default('gray-600');
    $table->string('bg_color')->default('gray-100');
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    
    $table->index('is_active');
    $table->index('sort_order');
});
```

#### Pivot таблица для категорий:
```php
Schema::create('category_warranty_blocks', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('category_id');
    $table->unsignedBigInteger('warranty_block_id');
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    
    $table->unique(['category_id', 'warranty_block_id'], 'category_warranty_unique');
    $table->index('category_id');
    $table->index('warranty_block_id');
    $table->index('sort_order');
});
```

#### Pivot таблица для товаров:
```php
Schema::create('product_warranty_blocks_pivot', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('product_id');
    $table->unsignedBigInteger('warranty_block_id');
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    
    $table->unique(['product_id', 'warranty_block_id'], 'product_warranty_unique');
    $table->index('product_id');
    $table->index('warranty_block_id');
    $table->index('sort_order');
});
```

### 3. Добавить связи в модели Category и Product

#### В `Category`:
```php
public function warrantyBlocks(): BelongsToMany
{
    return $this->belongsToMany(
        ProductWarrantyBlock::class,
        'category_warranty_blocks',
        'category_id',
        'warranty_block_id'
    )->withPivot('sort_order')->withTimestamps();
}
```

#### В `Product`:
```php
public function warrantyBlocks(): BelongsToMany
{
    return $this->belongsToMany(
        ProductWarrantyBlock::class,
        'product_warranty_blocks_pivot',
        'product_id',
        'warranty_block_id'
    )->withPivot('sort_order')->withTimestamps();
}
```

### 4. Создать Filament Resource

Создайте структуру как в `app/Filament/Resources/ProductBlocks/WarrantyBlocks/`:
- `ProductWarrantyBlockResource.php`
- `Pages/` (List, Create, Edit)
- `Tables/ProductWarrantyBlocksTable.php`
- `Schemas/ProductWarrantyBlockForm.php`

### 5. Создать RelationManagers

#### Для категорий: `CategoryWarrantyBlocksRelationManager`
#### Для товаров: `ProductWarrantyBlocksRelationManager`

Используйте существующие RelationManagers как шаблон, они уже содержат:
- Редактирование pivot данных (sort_order)
- Массовое управление
- Drag & drop сортировку

### 6. Добавить в API

В `ProductDetailResource` добавьте:
```php
$warrantyBlocks = ProductWarrantyBlock::getForProduct($this->resource)->map(function ($block) {
    return [
        'id' => $block->id,
        'title' => $block->title,
        'description' => $block->description,
        'icon' => $block->icon,
        'icon_image' => $block->icon_image_url,
        'icon_color' => $block->icon_color,
        'bg_color' => $block->bg_color,
    ];
})->values()->toArray();
```

### 7. Обновить фронтенд

Добавьте интерфейс в `apps/frontend/src/lib/api.ts`:
```typescript
export interface ProductWarrantyBlock {
  id: number;
  title: string;
  description?: string | null;
  icon?: string | null;
  icon_image?: string | null;
  icon_color: string;
  bg_color: string;
}
```

И отображение в `apps/frontend/src/pages/product/page.tsx`.

### 8. Создать Policy и добавить разрешения

Создайте `ProductWarrantyBlockPolicy` и добавьте разрешения в `RolesAndPermissionsSeeder`.

## Массовое управление

Все RelationManagers поддерживают:
- **Массовое изменение порядка сортировки** - выберите несколько блоков и измените их порядок
- **Массовое отвязывание** - отвяжите несколько блоков одновременно
- **Редактирование порядка** - измените порядок отдельного блока
- **Drag & drop сортировка** - перетаскивайте блоки для изменения порядка

## Интерфейс ProductBlockInterface

Все модели блоков должны реализовывать `ProductBlockInterface`, который определяет:
- `categories()` - связь с категориями
- `products()` - связь с товарами
- `getForProduct()` - логика получения блоков для товара
- `getForCategory()` - логика получения блоков для категории

Это обеспечивает единообразие и упрощает добавление новых типов блоков.
