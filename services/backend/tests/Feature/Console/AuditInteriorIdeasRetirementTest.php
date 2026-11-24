<?php

namespace Tests\Feature\Console;

use App\Models\Page\InteriorIdea;
use App\Models\Page\InteriorIdeaHotspot;
use App\Models\Product\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class AuditInteriorIdeasRetirementTest extends TestCase
{
    public function test_empty_inventory_is_read_only_and_never_reports_deletion_as_safe(): void
    {
        $report = $this->audit();

        $this->assertTrue($report['read_only']);
        $this->assertSame('inventory_complete', $report['status']);
        $this->assertSame(0, $report['ideas']['total']);
        $this->assertSame(0, $report['hotspots']['total']);
        $this->assertSame(0, $report['media']['total']);
        $this->assertFalse($report['external_api_consumers_verified']);
        $this->assertFalse($report['safe_to_delete']);
    }

    public function test_inventory_counts_orphaned_hotspots_without_changing_data(): void
    {
        $idea = InteriorIdea::factory()->create(['is_active' => true]);
        InteriorIdea::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['state' => 'active', 'parent_product_id' => null]);

        InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
        ]);
        InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id + 100000,
            'product_id' => $product->id + 100000,
        ]);

        $report = $this->audit();

        $this->assertSame(2, $report['ideas']['total']);
        $this->assertSame(1, $report['ideas']['active']);
        $this->assertSame(2, $report['hotspots']['total']);
        $this->assertSame(1, $report['hotspots']['missing_idea']);
        $this->assertSame(1, $report['hotspots']['missing_product']);
        $this->assertSame(2, DB::table('interior_idea_hotspots')->count());
        $this->assertSame(2, DB::table('interior_ideas')->count());
        $this->assertFalse($report['safe_to_delete']);
    }

    public function test_optional_media_file_inventory_checks_only_read_access(): void
    {
        Storage::fake('public');
        $idea = InteriorIdea::factory()->create();
        $media = new Media();
        $media->model_type = $idea->getMorphClass();
        $media->model_id = $idea->id;
        $media->collection_name = 'image';
        $media->name = 'archive';
        $media->file_name = 'archive.jpg';
        $media->mime_type = 'image/jpeg';
        $media->disk = 'public';
        $media->conversions_disk = 'public';
        $media->size = 3;
        $media->manipulations = [];
        $media->custom_properties = [];
        $media->generated_conversions = [];
        $media->responsive_images = [];
        $media->save();

        Storage::disk('public')->put($media->getPathRelativeToRoot(), 'abc');

        $present = $this->audit(['--verify-files' => true, '--file-limit' => 1]);
        $this->assertSame(1, $present['media']['total']);
        $this->assertSame(1, $present['media']['image_collection']);
        $this->assertSame(1, $present['media_file_check']['checked']);
        $this->assertSame(0, $present['media_file_check']['missing_originals_count']);
        $this->assertSame(0, $present['media_file_check']['storage_errors_count']);

        Storage::disk('public')->delete($media->getPathRelativeToRoot());
        $missing = $this->audit(['--verify-files' => true, '--file-limit' => 1]);
        $this->assertSame(1, $missing['media_file_check']['missing_originals_count']);
        $this->assertSame([$media->id], $missing['media_file_check']['missing_original_ids']);
        $this->assertSame(1, Media::query()->whereKey($media->id)->count());
        $this->assertFalse($missing['safe_to_delete']);
    }

    private function audit(array $options = []): array
    {
        $this->assertSame(0, Artisan::call('content:audit-interior-ideas', $options));

        return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    }
}
