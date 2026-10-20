<?php

namespace Tests\Feature\Services;

use App\Services\Address\WarehouseAddressOptions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarehouseAddressOptionsTest extends TestCase
{
    public function test_localities_show_name_first_with_district_but_preserve_full_address(): void
    {
        Http::fake(['*' => Http::response([
            ['externalId' => 'village', 'type' => 'с.', 'name' => 'Авдулово', 'label' => 'обл. Липецкая, р-н Данковский, с. Авдулово'],
            ['externalId' => 'town', 'type' => 'г.', 'name' => 'Липецк', 'label' => 'обл. Липецкая, г. Липецк'],
        ])]);
        $options = app(WarehouseAddressOptions::class);
        $this->assertSame(['village' => 'с. Авдулово (р-н Данковский)', 'town' => 'г. Липецк'], $options->initial('localities', 'region'));
        $this->assertSame('с. Авдулово (р-н Данковский)', $options->localityLabel('village'));
        $this->assertSame('обл. Липецкая, р-н Данковский, с. Авдулово', app(\App\Services\Address\AddressDirectoryClient::class)->cachedLabel('village'));
    }

    public function test_regions_are_filtered_by_name_even_when_api_returns_all_regions(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([
            ['externalId' => 'adygea', 'name' => 'Адыгея', 'label' => 'респ. Адыгея'],
            ['externalId' => 'lipetsk', 'name' => 'Липецкая', 'label' => 'обл. Липецкая'],
        ])]);
        $options = app(WarehouseAddressOptions::class);
        $this->assertSame(['lipetsk' => 'обл. Липецкая'], $options->search('regions', null, ' ЛИПЕЦ '));
        $this->assertSame([], $options->search('regions', null, 'несуществующий'));
        $this->assertCount(2, $options->initial('regions', null));
        Http::assertSent(fn ($request): bool => ! isset($request['q']));
    }

    public function test_locality_search_passes_name_and_selected_region_to_api(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([
            ['externalId' => 'city', 'name' => 'Липецк', 'label' => 'обл. Липецкая, г. Липецк'],
        ])]);
        $this->assertArrayHasKey('city', app(WarehouseAddressOptions::class)->search('localities', 'region48', 'Липец'));
        Http::assertSent(fn ($request): bool => $request['q'] === 'Липец' && $request['parentExternalId'] === 'region48');
    }

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

    public function test_initial_options_are_bounded_and_children_are_scoped(): void
    {
        Http::fake(['*' => Http::response([['externalId' => 'item', 'name' => 'Мира', 'label' => 'Мира']])]);
        $options = app(WarehouseAddressOptions::class);
        $this->assertSame(['item' => 'Мира'], $options->initial('localities', null));
        $this->assertSame(['item' => 'Мира'], $options->initial('streets', 'city-id'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/streets')
            && $request['parentExternalId'] === 'city-id' && (int) $request['limit'] === 50);
    }

    public function test_select_has_distinct_loading_and_search_messages(): void
    {
        $select = \App\Filament\Forms\DirectorySelectLoading::apply(\Filament\Forms\Components\Select::make('street'), 'улицы');
        $this->assertSame('Загружаем улицы…', $select->getLoadingMessage());
        $this->assertSame('Ищем улицы…', $select->getSearchingMessage());
    }

    public function test_compact_house_options_keep_full_address_separately(): void
    {
        Http::fake(['*' => Http::response([[
            'externalId' => 'house-code', 'source' => 'kladr', 'name' => '1,10',
            'label' => 'Липецкая область, Липецк, ул. Мира, ДОМ 1,10',
        ]])]);
        $options = app(WarehouseAddressOptions::class)->search('buildings', 'street-code', '10', compact: true);
        $this->assertSame(['house-code::10' => 'д. 10'], $options);
        $this->assertSame('Липецкая область, Липецк, ул. Мира, ДОМ 10',
            app(\App\Services\Address\AddressDirectoryClient::class)->cachedLabel('house-code::10'));
    }
}
