<?php

namespace Tests\Feature\Services;

use App\Services\Address\WarehouseAddressOptions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarehouseAddressOptionsTest extends TestCase
{
    public function test_building_search_is_scoped_and_kladr_groups_are_split(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([[
            'externalId' => '123', 'source' => 'kladr', 'name' => '1,10,10д,20-30',
            'label' => 'Воронеж, ДОМ 1,10,10д,20-30',
        ]])]);
        $options = app(WarehouseAddressOptions::class)->search('buildings', 'street-id', '10');
        $this->assertSame(['123::10' => 'Воронеж, ДОМ 10', '123::10%D0%B4' => 'Воронеж, ДОМ 10д'], $options);
        Http::assertSent(fn ($request): bool => $request['parentExternalId'] === 'street-id' && $request['q'] === '10');
    }

    public function test_buildings_are_never_requested_without_parent(): void
    {
        Http::preventStrayRequests();
        $this->assertSame([], app(WarehouseAddressOptions::class)->search('buildings', null, '10'));
        Http::assertNothingSent();
    }
}
