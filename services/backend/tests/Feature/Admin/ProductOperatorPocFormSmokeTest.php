<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Schemas\ProductOperatorPocForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ProductOperatorPocFormSmokeTest extends TestCase
{
    public function test_operator_product_editor_schema_can_be_built(): void
    {
        $schema = ProductOperatorPocForm::configure(Schema::make());

        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function test_product_resource_can_build_operator_poc_schema(): void
    {
        $schema = ProductResource::form(Schema::make());

        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function test_operator_characteristics_table_section_can_be_built(): void
    {
        $method = new ReflectionMethod(ProductOperatorPocForm::class, 'operatorAttributesSection');
        $method->setAccessible(true);

        $section = $method->invoke(null);

        $this->assertInstanceOf(Section::class, $section);
    }

    public function test_operator_top_sections_can_be_built(): void
    {
        foreach (['operatorMainSection', 'operatorSalesSection', 'operatorDescriptionSection', 'technicalSection'] as $methodName) {
            $method = new ReflectionMethod(ProductOperatorPocForm::class, $methodName);
            $method->setAccessible(true);

            $this->assertInstanceOf(Section::class, $method->invoke(null));
        }
    }
}
