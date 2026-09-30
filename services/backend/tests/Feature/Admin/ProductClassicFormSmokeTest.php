<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\OperatorProductVariantsRelationManager;
use App\Filament\Resources\Products\RelationManagers\OperatorProductVariationAttributeSelectionRelationManager;
use App\Filament\Resources\Products\RelationManagers\ProductVariantsRelationManager;
use App\Filament\Resources\Products\RelationManagers\ProductVariationAttributeSelectionRelationManager;
use App\Filament\Resources\Products\Schemas\ProductClassicForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ProductClassicFormSmokeTest extends TestCase
{
    public function test_classic_product_editor_schema_can_be_built(): void
    {
        $schema = ProductClassicForm::configure(Schema::make());

        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function test_product_resource_uses_classic_schema(): void
    {
        $schema = ProductResource::form(Schema::make());

        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function test_classic_seo_section_uses_supported_plugin_api(): void
    {
        $method = new ReflectionMethod(ProductClassicForm::class, 'seoSection');
        $method->setAccessible(true);

        $section = $method->invoke(null);

        $this->assertInstanceOf(Section::class, $section);
    }

    public function test_resource_restores_standard_relation_managers(): void
    {
        $relations = ProductResource::getRelations();

        $this->assertContains(ProductVariantsRelationManager::class, $relations);
        $this->assertContains(ProductVariationAttributeSelectionRelationManager::class, $relations);
        $this->assertNotContains(OperatorProductVariantsRelationManager::class, $relations);
        $this->assertNotContains(OperatorProductVariationAttributeSelectionRelationManager::class, $relations);
    }
}
