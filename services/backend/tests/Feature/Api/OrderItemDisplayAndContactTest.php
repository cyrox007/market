<?php
namespace Tests\Feature\Api;

use App\Http\Resources\OrderItemResource;
use App\Models\Inventory\PhysicalSite;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Page\Store;
use App\Models\Product\Attribute;
use App\Models\Product\Color\ColorSolution;
use App\Models\Product\Product;
use App\Models\Settings\ContactSettings;
use App\Models\Shipping\ShippingLocation;
use App\Services\Order\OrderItemDisplay;
use App\Services\Order\OrderManagerContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderItemDisplayAndContactTest extends TestCase
{
    private function item(Product $product, array $extra = []): OrderItem
    {
        $order = Order::factory()->create();
        return OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'price' => 100, 'total' => 100, ...$extra]);
    }

    private function contacts(): void
    {
        ContactSettings::updateOrCreate(['id' => 1], ['phones' => [['phone' => '+78002228586']], 'emails' => [['email' => 'global@example.test']],
            'working_hours' => ['Ежедневно 09:00–21:00']]);
    }

    private function store(ShippingLocation $region, array $extra = []): Store
    {
        return Store::create(['name' => 'Региональный магазин', 'slug' => 'store-'.uniqid(), 'address' => 'Адрес', 'city' => 'Город',
            'phone' => '+74742222222', 'email' => 'local@example.test', 'shipping_location_id' => $region->id,
            'is_active' => true, 'priority' => 1, ...$extra]);
    }

    public function test_snapshot_freezes_selected_offer_colors_materials_and_configuration(): void
    {
        $parent = Product::factory()->create(['name' => 'Модена', 'is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id, 'name' => 'Модена с боками', 'external_id' => '437667']);
        $material = Attribute::create(['name' => 'Материал корпуса', 'slug' => 'material-korpusa', 'type' => 'text']);
        $configuration = Attribute::create(['name' => 'Вариант', 'slug' => 'variant', 'type' => 'text', 'is_use_in_variations' => true]);
        $parent->attributes()->attach($material->id, ['custom_value' => 'ЛДСП']);
        $offer->attributes()->attach($configuration->id, ['custom_value' => 'Бока Бруклин']);
        $white = ColorSolution::create(['name' => 'Белый']);
        $offer->colorRows()->create(['color_element_id' => null, 'color_solution_id' => $white->id]);
        $item = $this->item($offer);
        $view = app(OrderItemDisplay::class)->present($item);
        $this->assertSame('snapshot', $view['source']);
        $this->assertSame('437667', $view['article']);
        $this->assertSame('Модена', $view['parent_name']);
        $this->assertSame('ЛДСП', $view['materials'][0]['value']);
        $this->assertSame('Бока Бруклин', $view['configuration'][0]['value']);
        $this->assertSame('Белый', $view['colors'][0]['solution']['name']);
        $offer->update(['name' => 'Другое имя', 'external_id' => '999999']);
        $white->update(['name' => 'Другой цвет']);
        $parent->attributes()->updateExistingPivot($material->id, ['custom_value' => 'Массив']);
        $this->assertSame($view, app(OrderItemDisplay::class)->present($item->fresh()));
    }

    public function test_own_material_overrides_parent_and_bundle_remains_in_snapshot(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id]);
        $material = Attribute::create(['name' => 'Материал', 'slug' => 'material', 'type' => 'text']);
        $parent->attributes()->attach($material->id, ['custom_value' => 'ЛДСП']);
        $offer->attributes()->attach($material->id, ['custom_value' => 'Массив']);
        $item = $this->item($offer, ['pricing_snapshot' => ['bundle' => ['bundle_id' => 17, 'primary_sku_id' => $offer->id, 'bundle_quantity' => 2]]]);
        $this->assertSame('Массив', $item->display_snapshot['materials'][0]['value']);
        $this->assertSame(17, $item->display_snapshot['bundle']['bundle_id']);
    }

    public function test_existing_order_without_snapshot_does_not_invent_historical_colors(): void
    {
        $product = Product::factory()->create(['name' => 'Старое имя']);
        $item = $this->item($product);
        DB::table('order_items')->where('id', $item->id)->update(['display_snapshot' => null]);
        $product->update(['name' => 'Новое имя']);
        $view = app(OrderItemDisplay::class)->present($item->fresh());
        $this->assertSame('legacy', $view['source']);
        $this->assertSame('Старое имя', $view['name']);
        $this->assertSame([], $view['colors']);
        $this->assertSame([], $view['configuration']);
    }

    public function test_snapshot_cannot_be_overwritten(): void
    {
        $item = $this->item(Product::factory()->create());
        $this->expectException(ValidationException::class);
        $item->forceFill(['display_snapshot' => ['name' => 'Подмена']])->save();
    }

    public function test_resource_keeps_snapshot_even_when_catalog_product_is_unavailable(): void
    {
        $item = $this->item(Product::factory()->create(['name' => 'Исторический товар']));
        $item->setRelation('product', null);
        $data = (new OrderItemResource($item))->resolve(request());
        $this->assertNull($data['product']);
        $this->assertSame('Исторический товар', $data['display']['name']);
    }

    public function test_regional_contacts_override_globals_and_browser_region_is_ignored(): void
    {
        $this->contacts();
        $region = ShippingLocation::factory()->create();
        $otherRegion = ShippingLocation::factory()->create();
        $local = $this->store($region);
        $this->store($otherRegion, ['phone' => '+74742333333']);
        $order = Order::factory()->create(['region_id' => $region->id]);
        request()->merge(['region_id' => $otherRegion->id]);
        $contact = app(OrderManagerContact::class)->resolve($order);
        $this->assertSame($local->id, $contact['store_id']);
        $this->assertSame(['value' => '+74742222222', 'source' => 'regional_store'], $contact['phone']);
        $this->assertSame('local@example.test', $contact['email']['value']);
        $this->assertSame('tel:+74742222222', $contact['primary_action']['url']);
        $this->assertStringContainsString(rawurlencode('Заказ '.$order->number), $contact['actions'][1]['url']);
    }

    public function test_phone_and_email_fall_back_independently(): void
    {
        $this->contacts();
        $region = ShippingLocation::factory()->create();
        $local = $this->store($region, ['phone' => null]);
        $order = Order::factory()->create(['region_id' => $region->id]);
        $contact = app(OrderManagerContact::class)->resolve($order);
        $this->assertSame('global', $contact['phone']['source']);
        $this->assertSame('regional_store', $contact['email']['source']);
        $local->update(['phone' => '+74742222222', 'email' => null]);
        $contact = app(OrderManagerContact::class)->resolve($order);
        $this->assertSame('regional_store', $contact['phone']['source']);
        $this->assertSame('global', $contact['email']['source']);
    }

    public function test_pickup_store_contacts_take_precedence_over_region(): void
    {
        $this->contacts();
        $region = ShippingLocation::factory()->create();
        $other = ShippingLocation::factory()->create();
        $this->store($region);
        $pickup = $this->store($other, ['phone' => '+74742333333']);
        $order = Order::factory()->create(['region_id' => $region->id, 'delivery_type' => 'pickup', 'fulfillment_snapshot' => ['store_id' => $pickup->id]]);
        $this->assertSame($pickup->id, app(OrderManagerContact::class)->resolve($order)['store_id']);
    }

    public function test_canonical_locality_match_is_used_without_matching_city_names(): void
    {
        $this->contacts();
        $region = ShippingLocation::factory()->create();
        $node = ['kind' => 'locality', 'source' => 'gar', 'externalId' => 'city48'];
        $site = PhysicalSite::create(['name' => 'Магазин', 'address' => 'Адрес', 'city' => 'Липецк', 'address_snapshot' => ['path' => [$node]]]);
        $local = $this->store($region, ['physical_site_id' => $site->id]);
        $order = Order::factory()->create(['region_id' => null, 'fulfillment_snapshot' => ['address_directory' => ['path' => [$node]]]]);
        $this->assertSame($local->id, app(OrderManagerContact::class)->resolve($order)['store_id']);
    }

    public function test_inactive_store_and_unconfigured_contacts_do_not_create_fake_actions(): void
    {
        $region = ShippingLocation::factory()->create();
        $this->store($region, ['is_active' => false]);
        ContactSettings::query()->delete();
        $order = Order::factory()->create(['region_id' => $region->id]);
        $contact = app(OrderManagerContact::class)->resolve($order);
        $this->assertNull($contact['store_id']);
        $this->assertNull($contact['phone']);
        $this->assertNull($contact['email']);
        $this->assertNull($contact['primary_action']);
        $this->assertSame([], $contact['actions']);
    }
    public function test_order_detail_exposes_snapshot_and_contacts_after_owner_authorization(): void
    {
        $this->contacts();
        $item = $this->item(Product::factory()->create(['name' => 'Зафиксированный диван']));
        $order = $item->order;
        $this->actingAs($order->user)->getJson('/api/v1/orders/'.$order->id)->assertOk()
            ->assertJsonPath('order.items.0.display.name', 'Зафиксированный диван')
            ->assertJsonPath('order.items.0.display.source', 'snapshot')
            ->assertJsonPath('order.manager_contact.phone.source', 'global');
        $this->actingAs($order->user)->getJson('/api/v1/orders')->assertOk()->assertJsonMissingPath('data.0.manager_contact');
        $this->actingAs(\App\Models\User::factory()->create())->getJson('/api/v1/orders/'.$order->id)->assertForbidden();
    }

}
