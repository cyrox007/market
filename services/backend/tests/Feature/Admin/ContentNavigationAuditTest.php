<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\AboutPageSettingsPage;
use App\Filament\Resources\About\AboutPageResource;
use App\Filament\Resources\Newsletter\NewsletterSubscriberResource;
use App\Filament\Resources\Sliders\SliderResource;
use App\Filament\Resources\Stores\StoreResource;
use Tests\TestCase;

class ContentNavigationAuditTest extends TestCase
{
    public function test_stores_are_grouped_with_warehouses_in_delivery_navigation(): void
    {
        $this->assertSame('Доставка', StoreResource::getNavigationGroup());
        $this->assertSame('Доставка', \App\Filament\Resources\Shipping\Warehouses\WarehouseResource::getNavigationGroup());
        $this->assertTrue(StoreResource::shouldRegisterNavigation());
    }

    public function test_single_about_editor_and_retired_ideas_are_not_duplicated_in_menu(): void
    {
        $this->assertTrue(AboutPageResource::shouldRegisterNavigation());
        $this->assertFalse(AboutPageSettingsPage::shouldRegisterNavigation());
        $this->assertFalse(class_exists('App\\Filament\\Resources\\InteriorIdeas\\InteriorIdeaResource'));
    }

    public function test_existing_home_banners_and_subscriber_management_stay_visible(): void
    {
        $this->assertTrue(SliderResource::shouldRegisterNavigation());
        $this->assertTrue(NewsletterSubscriberResource::shouldRegisterNavigation());
    }
}
