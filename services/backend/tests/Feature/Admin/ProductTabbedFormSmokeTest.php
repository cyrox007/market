<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Schemas\ProductTabbedForm;
use Filament\Schemas\Components\Section;
use ReflectionMethod;
use Tests\TestCase;

class ProductTabbedFormSmokeTest extends TestCase
{
    public function test_product_editor_seo_section_can_be_built(): void
    {
        $method = new ReflectionMethod(ProductTabbedForm::class, 'seoSection');
        $method->setAccessible(true);

        $section = $method->invoke(null);

        $this->assertInstanceOf(Section::class, $section);
    }
}
