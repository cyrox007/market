<?php

namespace Tests\Feature\Shipping;

use App\Models\Inventory\Warehouse;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Isolated Lipetsk-area fixtures, not confirmation of live directory records or production tariffs. */
class PilotCheckoutAuditTest extends TestCase
{
    public static function addresses(): array
    {
        return [
            ['lipetsk-house', false, true], ['lipetsk-house', true, true],
            ['chaplygin-house', false, true], ['chaplygin-house', true, true],
            ['lipetsk-house', false, false], ['lipetsk-house', true, false],
            ['chaplygin-house', false, false], ['chaplygin-house', true, false],
        ];
    }

    #[DataProvider('addresses')]
    public function test_delivery_coverage_price_order_and_stock(string $building, bool $authenticated, bool $covered): void
    {
        $region = ['kind' => 'region', 'externalId' => 'region48', 'name' => 'Липецкая область'];
        Http::fake(function ($request) use ($region) {
            $id = rawurldecode(basename(parse_url($request->url(), PHP_URL_PATH)));
            $city = str_starts_with($id, 'chaplygin') ? 'Чаплыгин' : 'Липецк';
            $target = $id === 'region48' ? $region : ['kind' => 'building', 'source' => 'gar', 'externalId' => $id,
                'name' => '1', 'kladrCode' => '4800000100000010001'];
            return Http::response(['target' => $target, 'path' => [$region,
                ['kind' => 'locality', 'externalId' => $city, 'name' => $city]],
                'label' => 'Липецкая область, '.$city.', тестовый дом 1']);
        });
        $sku = Product::factory()->create(['price' => 1000, 'stock' => 5, 'backorder' => false]);
        $warehouse = Warehouse::create(['name' => 'Липецк — центральный', 'external_id' => 'LP00001',
            'source_type' => 'physical', 'stock_mode' => 'quantity', 'is_active' => true]);
        $warehouse->productStocks()->create(['product_id' => $sku->id, 'quantity' => 5]);
        if ($covered) {
            $warehouse->deliveryProfiles()->create(['name' => 'Тестовое покрытие области', 'coverage_type' => 'locations',
                'directory_territories' => [['region_id' => 'region48']], 'base_price' => 500,
                'service_tariffs' => ['requires_assembly' => true, 'assembly_price' => 200]]);
        }
        PaymentMethod::create(['name' => 'При получении', 'code' => 'cash', 'gateway' => 'manual', 'is_active' => true, 'is_enabled' => true]);
        if ($authenticated) {
            $this->actingAs(User::factory()->create());
        }
        $data = ['directory_external_id' => $building, 'items' => [['product_id' => $sku->id, 'quantity' => 2]]];
        $quote = $this->postJson('/api/v1/shipping/quote', $data)->assertOk();
        $quote->assertJsonPath('available', $covered);
        $response = $this->postJson('/api/v1/orders', $data + ['delivery_type' => 'delivery',
            'warehouse_id' => $warehouse->id, 'payment_method' => 'cash', 'contact_name' => 'Тест',
            'contact_phone' => '+79001234567', 'contact_email' => 'pilot@example.test',
            'accept_offer' => true, 'accept_personal_data' => true]);
        if (! $covered) {
            $response->assertUnprocessable();
            $this->assertSame(0, Order::count());
            $this->assertEquals(5, $warehouse->productStocks()->first()->quantity);
            return;
        }
        $response->assertCreated();
        $order = Order::latest('id')->firstOrFail();
        $this->assertEquals(500, $order->delivery_cost);
        $this->assertEquals(200, $order->assembly_cost);
        $this->assertEquals(2700, $order->total);
        $this->assertEquals(5, $warehouse->productStocks()->first()->quantity);
        $this->assertEquals(2, $warehouse->productStocks()->first()->reserved_quantity);
        $payload = app(\App\Actions\Integration\Integration1C\BuildOrderSyncPayloadAction::class)->execute($order);
        $this->assertFalse($payload['pickFromStore']);
        $this->assertSame('LP00001', $payload['warehouseExternalId']);
        $this->assertSame('4800000100000010001', $payload['addressKladrCode']);
        $this->assertStringContainsString('Липецкая область', $payload['address']);
    }
}
