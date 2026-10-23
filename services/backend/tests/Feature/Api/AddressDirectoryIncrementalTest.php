<?php

namespace Tests\Feature\Api;

use App\Services\Address\AddressDirectoryClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressDirectoryIncrementalTest extends TestCase
{
    public function test_empty_building_list_is_rechecked_during_incremental_transfer(): void
    {
        Http::fakeSequence()->push([])->push([['externalId' => 'building-guid', 'name' => '14']]);
        $client = app(AddressDirectoryClient::class);
        $query = ['parentExternalId' => 'street-guid'];
        $this->assertSame([], $client->options('buildings', $query));
        $this->assertSame('14', $client->options('buildings', $query)[0]['name']);
        $this->assertSame('14', $client->options('buildings', $query)[0]['name']);
        Http::assertSentCount(2);
    }
}
