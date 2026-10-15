<?php

namespace Tests\Feature\Pricing;

use App\Models\Pricing\PricingCustomerGroup;
use App\Models\Pricing\PricingProductGroup;
use App\Models\Pricing\PricingRule;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\ProductRegionRule;
use App\Models\Product\Room;
use App\Models\Shipping\ShippingLocation;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VisualPricingTest extends TestCase
{
    private function rule(array $data = []): PricingRule
    {
        return PricingRule::create(array_merge(['name' => 'Скидка', 'is_active' => true, 'target_type' => 'all', 'audience_type' => 'all', 'adjustment_type' => 'discount_percent', 'amount' => 10, 'priority' => 0], $data));
    }

    private function price(Product $sku, ?ShippingLocation $location = null, ?User $user = null): array
    {
        return app(PricingEngine::class)->explain($sku, $location, $user, true);
    }

    public function test_one_priority_winner_and_bounds_are_identical_in_sql_and_service(): void
    {
        $sku = Product::factory()->create(['price' => 1000]);
        $first = $this->rule();
        $this->assertEquals(900, $this->price($sku)['price']);
        $second = $this->rule(['amount' => 50, 'priority' => 10]);
        $this->assertEquals(500, $this->price($sku)['price']);
        $second->update(['is_active' => false]);
        $this->assertEquals($first->id, $this->price($sku)['rule_id']);
        $first->update(['adjustment_type' => 'discount_fixed', 'amount' => 2000]);
        $this->assertEquals(0, $this->price($sku)['price']);
    }

    public function test_regional_price_is_the_base_and_dates_are_immediate(): void
    {
        $sku = Product::factory()->create(['price' => 1000]);
        $region = ShippingLocation::factory()->create();
        ProductRegionRule::create(['product_id' => $sku->id, 'shipping_location_id' => $region->id, 'price_override' => 1200, 'is_active' => true]);
        $rule = $this->rule(['starts_at' => now()->addHour()]);
        $this->assertEquals(1200, $this->price($sku, $region)['price']);
        $this->travel(2)->hours();
        $this->assertEquals(1080, $this->price($sku, $region)['price']);
        $rule->update(['ends_at' => now()]);
        $this->assertEquals(1200, $this->price($sku, $region)['price']);
    }

    public function test_product_group_parent_variants_and_exclusions(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $a = Product::factory()->create(['parent_product_id' => $parent->id, 'price' => 1000]);
        $b = Product::factory()->create(['parent_product_id' => $parent->id, 'price' => 2000]);
        $group = PricingProductGroup::create(['name' => 'Диваны', 'product_ids' => [$parent->id]]);
        $this->rule(['target_type' => 'groups', 'product_group_ids' => [$group->id], 'excluded_product_ids' => [$b->id]]);
        $this->assertEquals(900, $this->price($a)['price']);
        $this->assertEquals(2000, $this->price($b)['price']);
    }

    public function test_users_groups_and_guests_never_share_personal_price(): void
    {
        $sku = Product::factory()->create(['price' => 1000]);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $group = PricingCustomerGroup::create(['name' => 'Партнёры', 'user_ids' => [$user->id]]);
        $this->rule(['audience_type' => 'groups', 'customer_group_ids' => [$group->id]]);
        $this->assertEquals(900, $this->price($sku, user: $user)['price']);
        $this->assertEquals(1000, $this->price($sku, user: $other)['price']);
        $this->assertEquals(1000, $this->price($sku)['price']);
    }

    public function test_category_target_includes_descendants_and_room_filters_are_reused(): void
    {
        $category = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $category->id]);
        $sku = Product::factory()->create(['price' => 1000]);
        $sku->taxons()->attach($child);
        $rule = $this->rule(['target_type' => 'categories', 'category_ids' => [$category->id]]);
        $this->assertEquals(900, $this->price($sku)['price']);
        $room = Room::create(['name' => 'Гостиная', 'filters' => ['price_max' => 500]]);
        $room->productCategories()->attach($category);
        $rule->update(['target_type' => 'rooms', 'room_ids' => [$room->id]]);
        $this->assertEquals(1000, $this->price($sku)['price']);
        $room->update(['filters' => ['price_max' => 1500]]);
        $this->assertEquals(900, $this->price($sku)['price']);
    }

    public function test_empty_targets_fail_closed(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->rule(['target_type' => 'products', 'product_ids' => []]);
    }

    public function test_conditions_count_only_delivered_orders_and_allow_zero_as_a_condition(): void
    {
        $sku = Product::factory()->create(['price' => 1000]);
        $user = User::factory()->create();
        $rule = $this->rule(['audience_type' => 'conditions', 'conditions' => ['max_orders' => 0]]);
        $this->assertEquals(900, $this->price($sku, user: $user)['price']);
        $order = \App\Models\Order\Order::factory()->create(['user_id' => $user->id, 'status' => 'cancelled', 'subtotal' => 1500]);
        $this->assertEquals(900, $this->price($sku, user: $user)['price']);
        $order->update(['status' => 'delivered']);
        // Start another evaluation context; no process-global eligibility cache.
        request()->attributes->replace([]);
        $this->assertEquals(1000, $this->price($sku, user: $user)['price']);
        $rule->update(['conditions' => ['min_orders' => 1, 'min_spend' => 1000]]);
        $this->assertEquals(900, $this->price($sku, user: $user)['price']);
    }

    public function test_personal_catalog_filter_cache_does_not_leak_and_matches_detail_cart_checkout(): void
    {
        Cache::flush();
        $category = Category::factory()->create();
        $sku = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $sku->taxons()->attach($category);
        $user = User::factory()->create();
        $this->rule(['audience_type' => 'users', 'user_ids' => [$user->id]]);
        $this->getJson('/api/v1/products?category_slug='.$category->slug)->assertOk()->assertJsonPath('data.0.price', 1000);
        $this->actingAs($user)->getJson('/api/v1/products?category_slug='.$category->slug)->assertOk()->assertJsonPath('data.0.price', 900)->assertJsonPath('meta.filters.price.min', 900);
        $this->actingAs($user)->getJson('/api/v1/products/count?price_max=950')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($user)->getJson('/api/v1/products/'.$sku->slug)->assertOk()->assertJsonPath('product.price', 900);
        $this->actingAs($user)->postJson('/api/v1/cart', ['product_id' => $sku->id, 'quantity' => 2])->assertCreated();
        $cart = $this->actingAs($user)->getJson('/api/v1/cart')->assertOk();
        $this->assertEquals(1800, $cart->json('subtotal'));
        \App\Models\Payment\PaymentMethod::create(['code' => 'card', 'name' => 'Карта', 'is_active' => true, 'is_enabled' => true, 'gateway' => 'manual']);
        $order = $this->actingAs($user)->postJson('/api/v1/orders', ['contact_name' => 'Покупатель', 'contact_phone' => '+79991234567', 'contact_email' => $user->email, 'payment_method' => 'card', 'delivery_type' => 'pickup'])->assertCreated();
        $item = \App\Models\Order\OrderItem::where('order_id', $order->json('order.id'))->firstOrFail();
        $this->assertEquals(900, $item->price);
        $this->assertEquals('Скидка', $item->pricing_snapshot['rule_name']);
        $this->assertEquals(1000, $item->pricing_snapshot['base_price']);
    }

    public function test_expiration_changes_catalog_filter_cache_without_a_manual_save(): void
    {
        $category = Category::factory()->create();
        $sku = Product::factory()->create(['price' => 1000]);
        $sku->taxons()->attach($category);
        $this->rule(['ends_at' => now()->addMinute()]);
        $this->getJson('/api/v1/products?category_slug='.$category->slug)->assertOk()->assertJsonPath('meta.filters.price.min', 900);
        $this->travel(2)->minutes();
        $this->getJson('/api/v1/products?category_slug='.$category->slug)->assertOk()->assertJsonPath('meta.filters.price.min', 1000);
    }

    public function test_catalog_cards_facets_count_and_guest_cache_are_consistent(): void
    {
        Cache::flush();
        $category = Category::factory()->create();
        $sku = Product::factory()->create(['price' => 1000]);
        $sku->taxons()->attach($category);
        $this->rule();
        $this->getJson('/api/v1/products?category_slug='.$category->slug)->assertOk()->assertJsonPath('data.0.price', 900)->assertJsonPath('meta.filters.price.min', 900);
        $this->getJson('/api/v1/products/count?price_max=950')->assertOk()->assertJsonPath('total', 1);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('pricing_rule_changes')->count());
    }

    public function test_sale_filter_and_old_price_include_new_discounts_without_original_price(): void
    {
        $category = Category::factory()->create();
        $sku = Product::factory()->create(['price' => 1000, 'original_price' => null]);
        $sku->taxons()->attach($category);
        $rule = $this->rule();
        $this->getJson('/api/v1/products?category_slug='.$category->slug.'&on_sale=1')->assertOk()->assertJsonPath('data.0.old_price', 1000)->assertJsonPath('data.0.discount_percent', 10)->assertJsonPath('meta.filters.has_sale', true);
        $this->getJson('/api/v1/products/count?on_sale=1')->assertOk()->assertJsonPath('total', 1);
        $rule->update(['is_active' => false]);
        $this->getJson('/api/v1/products/count?on_sale=1')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_fixed_price_markup_and_equal_priority_have_deterministic_results(): void
    {
        $sku = Product::factory()->create(['price' => 1000]);
        $rule = $this->rule(['adjustment_type' => 'markup_fixed', 'amount' => 100]);
        $this->rule(['adjustment_type' => 'fixed_price', 'amount' => 1]);
        $this->assertEquals(1100, $this->price($sku)['price']);
        $rule->update(['adjustment_type' => 'markup_percent', 'amount' => 12.5]);
        $this->assertEquals(1125, $this->price($sku)['price']);
        $rule->update(['adjustment_type' => 'fixed_price', 'amount' => 777]);
        $this->assertEquals(777, $this->price($sku)['price']);
    }

    public function test_location_restriction_and_customer_group_changes_take_effect(): void
    {
        $sku = Product::factory()->create(['price' => 1000]);
        $user = User::factory()->create();
        $region = ShippingLocation::factory()->create();
        $group = PricingCustomerGroup::create(['name' => 'Группа', 'user_ids' => [$user->id]]);
        $this->rule(['location_ids' => [$region->id], 'audience_type' => 'groups', 'customer_group_ids' => [$group->id]]);
        $this->assertEquals(1000, $this->price($sku, user: $user)['price']);
        $this->assertEquals(900, $this->price($sku, $region, $user)['price']);
        $other = User::factory()->create();
        $group->update(['user_ids' => [$other->id]]);
        $this->assertEquals(1000, $this->price($sku, $region, $user)['price']);
    }

    public function test_percent_above_one_hundred_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->rule(['amount' => 101]);
    }
}
