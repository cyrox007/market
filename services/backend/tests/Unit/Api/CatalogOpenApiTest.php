<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;

class CatalogOpenApiTest extends TestCase
{
    public function test_catalog_and_count_document_shared_filters_and_facet_semantics(): void
    {
        $spec = json_decode(file_get_contents(__DIR__.'/../../../public/api-docs/openapi.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['/api/v1/products', '/api/v1/products/count'] as $path) {
            $parameters = array_column($spec['paths'][$path]['get']['parameters'], 'name');
            $this->assertSame($parameters, array_values(array_unique($parameters)));
            foreach (['on_sale', 'category_slugs', 'room_slug', 'attr_proizvoditel',
                'width_min', 'width_max', 'depth_min', 'depth_max', 'height_min', 'height_max'] as $name) {
                $this->assertContains($name, $parameters);
            }
        }
        $filters = $spec['paths']['/api/v1/products']['get']['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties']['filters'];
        $this->assertSame(['excluding_self'], $filters['properties']['facet_mode']['enum']);
        foreach (['width', 'depth', 'height'] as $key) {
            $this->assertSame(['mm'], $filters['properties']['dimensions']['properties'][$key]['properties']['unit']['enum']);
        }
        $count = $spec['paths']['/api/v1/products/count']['get']['responses']['200']['content']['application/json']['schema'];
        $this->assertSame(['total'], array_keys($count['properties']));
    }
}
