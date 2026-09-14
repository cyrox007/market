<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\OperatorEditProduct;
use App\Filament\Resources\Products\Schemas\ProductTabbedForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ProductTabbedFormSmokeTest extends TestCase
{
    public function test_product_editor_full_schema_can_be_built(): void
    {
        $schema = ProductTabbedForm::configure(Schema::make());

        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function test_product_editor_compact_sections_can_be_built(): void
    {
        foreach ([
            'coreSection',
            'technicalSection',
            'descriptionSection',
            'operatorAttributesSection',
            'mediaSection',
            'seoSection',
        ] as $methodName) {
            $method = new ReflectionMethod(ProductTabbedForm::class, $methodName);
            $method->setAccessible(true);

            $this->assertInstanceOf(Section::class, $method->invoke(null), $methodName);
        }
    }

    public function test_product_editor_does_not_use_sticky_footer_actions(): void
    {
        $this->assertFalse(OperatorEditProduct::$formActionsAreSticky);

        $method = new ReflectionMethod(OperatorEditProduct::class, 'getFormActions');
        $this->assertSame(OperatorEditProduct::class, $method->getDeclaringClass()->getName());
    }
}
