<?php

namespace Tests\Feature\Shipping;

use App\Models\Inventory\Warehouse;
use App\Models\Shipping\AdditionalService;
use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryProfile;
use App\Services\Shipping\WarehouseServices;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WarehouseServiceTariffsTest extends TestCase
{
    private function profile(array $tariffs = []): WarehouseDeliveryProfile
    {
        return WarehouseDeliveryProfile::create(['warehouse_id' => Warehouse::create(['name' => 'Warehouse'])->id,
            'name' => 'Profile', 'coverage_type' => 'locations', 'service_tariffs' => $tariffs]);
    }

    public function test_services_do_not_require_any_legacy_locations(): void
    {
        $service = AdditionalService::create(['name' => 'Packing', 'slug' => 'packing', 'code' => 'packing', 'price_type' => 'fixed', 'is_active' => true]);
        $profile = $this->profile(['requires_assembly' => true, 'assembly_price' => 400, 'assembly_days' => 2,
            'additional' => [['id' => $service->id, 'price' => 300]]]);
        $result = app(WarehouseServices::class)->calculate($profile, ['requires_assembly' => false, 'additional_services' => [['id' => $service->id]]]);
        $this->assertSame(0, ShippingLocation::count());
        $this->assertSame(400.0, $result['assembly_cost']);
        $this->assertSame(300.0, $result['additional_services_cost']);
        $this->assertSame(2, $result['assembly_days']);
    }

    public function test_floor_tariff_overrides_base_and_respects_limit(): void
    {
        $type = DeliveryHandlingType::factory()->create(['requires_floor' => true, 'max_floor' => 5, 'code' => 'stairs']);
        $profile = $this->profile(['handling' => [['id' => $type->id, 'base_price' => 100, 'floor_prices' => [['floor' => 3, 'price' => 450]]]]]);
        $calculator = app(WarehouseServices::class);
        $this->assertSame(450.0, $calculator->calculate($profile, ['delivery_handling_type_id' => $type->id, 'delivery_floor' => 3])['handling_cost']);
        $this->assertSame(100.0, $calculator->calculate($profile, ['delivery_handling_type_id' => $type->id, 'delivery_floor' => 2])['handling_cost']);
        $this->expectException(ValidationException::class);
        $calculator->calculate($profile, ['delivery_handling_type_id' => $type->id, 'delivery_floor' => 6]);
    }

    public function test_elevator_price_and_missing_floor_are_not_guessed(): void
    {
        $type = DeliveryHandlingType::factory()->create(['code' => 'elevator', 'requires_floor' => true]);
        $profile = $this->profile(['handling' => [['id' => $type->id, 'base_price' => 100, 'elevator_price' => 250]]]);
        $this->assertSame(250.0, app(WarehouseServices::class)->calculate($profile, ['delivery_handling_type_id' => $type->id, 'delivery_floor' => 2])['handling_cost']);
        $this->expectException(ValidationException::class);
        app(WarehouseServices::class)->calculate($profile, ['delivery_handling_type_id' => $type->id]);
    }

    public function test_unconfigured_service_is_not_free(): void
    {
        $this->expectException(ValidationException::class);
        app(WarehouseServices::class)->calculate($this->profile(), ['requires_assembly' => true]);
    }

    public function test_negative_tariffs_and_duplicate_floor_entries_are_rejected(): void
    {
        $calculator = app(WarehouseServices::class);
        try {
            $calculator->validateTariffs(['assembly_price' => -1]);
            $this->fail('Negative price accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assembly_price', $e->errors());
        }
        $type = DeliveryHandlingType::factory()->create();
        $this->expectException(ValidationException::class);
        $this->profile(['handling' => [['id' => $type->id, 'floor_prices' => [['floor' => 2, 'price' => 10], ['floor' => 2, 'price' => 20]]]]]);
    }

    public function test_disabled_service_is_rejected_even_with_profile_price(): void
    {
        $service = AdditionalService::create(['name' => 'Packing', 'slug' => 'packing', 'code' => 'packing', 'price_type' => 'fixed', 'is_active' => false]);
        $profile = $this->profile(['additional' => [['id' => $service->id, 'price' => 0]]]);
        $this->expectException(ValidationException::class);
        app(WarehouseServices::class)->calculate($profile, ['additional_services' => [['id' => $service->id]]]);
    }

    public function test_old_reference_cannot_silently_drop_required_services(): void
    {
        $profile = $this->profile();
        $profile->setRawAttributes(['service_tariffs' => null, 'services_location_id' => 123], true);
        $this->expectException(ValidationException::class);
        app(WarehouseServices::class)->calculate($profile, []);
    }
}
