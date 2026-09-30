<?php

namespace Tests\Feature\Api;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Комнаты (вторая таксономия): изоляция от каталога, конечные точки, резолв товаров, фильтр.
 */
class RoomControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeRoom(string $name, string $slug, ?int $parentId = null): Room
    {
        $room = new Room();
        $room->name = $name;
        $room->slug = $slug;
        $room->parent_id = $parentId;
        $room->is_active = true;
        $room->save();

        return $room;
    }

    private function makeProduct(Category $category, array $attrs = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'state' => Product::ACTIVE,
            'parent_product_id' => null,
        ], $attrs));
        $product->taxons()->attach($category->id);

        return $product;
    }

    public function test_taxonomies_are_isolated(): void
    {
        $category = Category::factory()->create(['name' => 'Диваны', 'slug' => 'divany']);
        $room = $this->makeRoom('Гостиная', 'gostinaya');

        // Комната не попадает в каталог, категория — не в комнаты.
        $this->assertTrue(Category::whereKey($room->id)->doesntExist());
        $this->assertTrue(Room::whereKey($category->id)->doesntExist());
        $this->assertSame('rooms', $room->taxonomy->slug);
    }

    public function test_rooms_tree_endpoint_returns_tree(): void
    {
        $parent = $this->makeRoom('Гостиная', 'gostinaya');
        $this->makeRoom('Диваны', 'gostinaya-divany', $parent->id);

        $response = $this->getJson('/api/v1/rooms/tree');

        $response->assertStatus(200)->assertJsonStructure(['tree' => [['id', 'name', 'slug', 'children']]]);
        $this->assertSame('Гостиная', $response->json('tree.0.name'));
        $this->assertCount(1, $response->json('tree.0.children'));
    }

    public function test_room_show_returns_category_format(): void
    {
        $room = $this->makeRoom('Спальня', 'spalnya');

        $response = $this->getJson('/api/v1/rooms/spalnya');

        // Тот же ключ и формат, что у категории (фронт переиспользует страницу).
        $response->assertStatus(200)
            ->assertJsonPath('category.name', 'Спальня')
            ->assertJsonPath('category.slug', 'spalnya')
            ->assertJsonPath('category.full_path', '/rooms/spalnya');
    }

    public function test_room_leaf_resolves_products_from_categories(): void
    {
        $category = Category::factory()->create(['slug' => 'krovati']);
        $this->makeProduct($category);
        $this->makeProduct($category);

        $room = $this->makeRoom('Кровати', 'spalnya-krovati');
        $room->productCategories()->sync([$category->id]);

        $response = $this->getJson('/api/v1/products?room_slug=spalnya-krovati&per_page=10');

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_parent_room_aggregates_products_from_subrooms(): void
    {
        $category = Category::factory()->create(['slug' => 'divany']);
        $this->makeProduct($category);

        $parent = $this->makeRoom('Гостиная', 'gostinaya');
        $child = $this->makeRoom('Диваны', 'gostinaya-divany', $parent->id);
        $child->productCategories()->sync([$category->id]);

        // Родитель показывает товары подкомнат.
        $response = $this->getJson('/api/v1/products?room_slug=gostinaya&per_page=10');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_room_price_filter_applies(): void
    {
        $category = Category::factory()->create(['slug' => 'stoly']);
        $this->makeProduct($category, ['price' => 1000]);
        $this->makeProduct($category, ['price' => 9000]);

        $room = $this->makeRoom('Столы', 'kuhnya-stoly');
        $room->productCategories()->sync([$category->id]);
        $room->filters = ['price_min' => 5000];
        $room->save();

        $response = $this->getJson('/api/v1/products?room_slug=kuhnya-stoly&per_page=10');

        // Применён фильтр комнаты по умолчанию — только товар за 9000.
        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_user_request_cannot_relax_room_price(): void
    {
        $category = Category::factory()->create(['slug' => 'stoly-relax']);
        $this->makeProduct($category, ['price' => 1000]);
        $this->makeProduct($category, ['price' => 9000]);

        $room = $this->makeRoom('Столы', 'kuhnya-stoly-relax');
        $room->productCategories()->sync([$category->id]);
        $room->filters = ['price_min' => 5000];
        $room->save();

        // Пользователь пытается ослабить порог комнаты (1000 < 5000) — ограничение комнаты сильнее.
        $response = $this->getJson('/api/v1/products?room_slug=kuhnya-stoly-relax&price_min=1000&per_page=10');
        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));

        // А сузить (7000 > 5000) — можно: не остаётся ни одного.
        $narrower = $this->getJson('/api/v1/products?room_slug=kuhnya-stoly-relax&price_min=7000&per_page=10');
        $this->assertSame(1, $narrower->json('meta.total'));
    }

    public function test_user_color_forbidden_by_room_yields_empty(): void
    {
        $category = Category::factory()->create(['slug' => 'divany-color']);
        $color = Attribute::create([
            'name' => 'Цвет',
            'slug' => Attribute::SLUG_COLOR,
            'type' => 'color',
            'is_filterable' => true,
            'is_use_in_variations' => true,
        ]);
        $gray = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Серый',
            'slug' => 'seryi',
            'color_code' => '#808080',
        ]);
        $beige = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Бежевый',
            'slug' => 'bezevyi',
            'color_code' => '#d8c3a5',
        ]);

        $grayProduct = $this->makeProduct($category);
        $grayProduct->attributes()->attach($color->id, ['attribute_value_id' => $gray->id]);
        $beigeProduct = $this->makeProduct($category);
        $beigeProduct->attributes()->attach($color->id, ['attribute_value_id' => $beige->id]);

        $room = $this->makeRoom('Диваны', 'gostinaya-divany-color');
        $room->productCategories()->sync([$category->id]);
        $room->filters = ['colors' => ['seryi', 'bezevyi']];
        $room->save();

        // Разрешённый комнатой цвет — сужение работает.
        $allowed = $this->getJson('/api/v1/products?room_slug=gostinaya-divany-color&colors[]=seryi&per_page=10');
        $this->assertSame(1, $allowed->json('meta.total'));

        // Запрещённый комнатой цвет — пустое пересечение → пусто (а не «показать всё»).
        $forbidden = $this->getJson('/api/v1/products?room_slug=gostinaya-divany-color&colors[]=krasnyi&per_page=10');
        $this->assertSame(0, $forbidden->json('meta.total'));
    }

    public function test_room_attribute_filter_applies(): void
    {
        $category = Category::factory()->create(['slug' => 'shkafy']);
        $attribute = Attribute::create(['name' => 'Размер', 'slug' => 'size']);
        $small = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => '120x80', 'slug' => '120x80']);
        $large = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => '200x100', 'slug' => '200x100']);

        $p1 = $this->makeProduct($category);
        $p1->attributeValues()->attach($small->id, ['attribute_id' => $attribute->id]);
        $p2 = $this->makeProduct($category);
        $p2->attributeValues()->attach($large->id, ['attribute_id' => $attribute->id]);

        $room = $this->makeRoom('Шкафы', 'spalnya-shkafy');
        $room->productCategories()->sync([$category->id]);
        $room->filters = ['attributes' => ['size' => ['120x80']]];
        $room->save();

        $response = $this->getJson('/api/v1/products?room_slug=spalnya-shkafy&per_page=10');

        // Отбор по характеристике комнаты — только товар с размером 120x80.
        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_child_inherits_parent_filter(): void
    {
        $category = Category::factory()->create(['slug' => 'divany']);
        $this->makeProduct($category, ['price' => 1000]);
        $this->makeProduct($category, ['price' => 9000]);

        $parent = $this->makeRoom('Гостиная', 'gostinaya');
        $parent->filters = ['price_min' => 5000];
        $parent->save();
        $child = $this->makeRoom('Диваны', 'gostinaya-divany', $parent->id);
        $child->productCategories()->sync([$category->id]);

        // Лист без своего фильтра наследует price_min=5000 → только товар за 9000.
        $response = $this->getJson('/api/v1/products?room_slug=gostinaya-divany&per_page=10');
        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_child_cannot_relax_parent_filter(): void
    {
        $category = Category::factory()->create(['slug' => 'shkafy']);
        $this->makeProduct($category, ['price' => 1000]);
        $this->makeProduct($category, ['price' => 9000]);

        $parent = $this->makeRoom('Спальня', 'spalnya');
        $parent->filters = ['price_min' => 5000];
        $parent->save();
        $child = $this->makeRoom('Шкафы', 'spalnya-shkafy', $parent->id);
        $child->productCategories()->sync([$category->id]);
        $child->filters = ['price_min' => 1000]; // попытка ослабить
        $child->save();

        // Наследуется строгое max(5000,1000)=5000 → только товар за 9000.
        $response = $this->getJson('/api/v1/products?room_slug=spalnya-shkafy&per_page=10');
        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_effective_filters_intersect_colors(): void
    {
        $parent = $this->makeRoom('Гостиная', 'gostinaya');
        $parent->filters = ['colors' => ['seryi', 'sinii', 'belyi']];
        $parent->save();
        $child = $this->makeRoom('Диваны', 'gostinaya-divany', $parent->id);
        $child->filters = ['colors' => ['seryi', 'krasnyi']]; // krasnyi нет у родителя
        $child->save();

        // Пересечение: остаётся только seryi (krasnyi отброшен как расширение).
        $effective = $child->fresh()->effectiveFilters();
        $this->assertSame(['seryi'], array_values($effective['colors']));
    }

    public function test_saving_room_flushes_tree_cache(): void
    {
        $this->makeRoom('Гостиная', 'gostinaya');

        // Прогрев кэша дерева комнат.
        $this->getJson('/api/v1/rooms/tree')->assertStatus(200);
        $this->assertTrue(Cache::has(Room::cacheKey('tree')));

        // Правка комнаты в админке сбрасывает кэш дерева (иначе меню стухнет до TTL).
        $room = Room::first();
        $room->name = 'Гостиная и кухня';
        $room->save();

        $this->assertFalse(Cache::has(Room::cacheKey('tree')));
    }

    public function test_fifth_level_is_forbidden(): void
    {
        $l1 = $this->makeRoom('Уровень 1', 'lvl-1');
        $l2 = $this->makeRoom('Уровень 2', 'lvl-2', $l1->id);
        $l3 = $this->makeRoom('Уровень 3', 'lvl-3', $l2->id);
        $l4 = $this->makeRoom('Уровень 4', 'lvl-4', $l3->id);

        $this->assertSame(4, $l4->depth());

        // Пятый уровень запрещён.
        $this->expectException(\RuntimeException::class);
        $this->makeRoom('Уровень 5', 'lvl-5', $l4->id);
    }
}
