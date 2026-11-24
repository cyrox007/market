<?php

namespace App\Console\Commands;

use App\Models\Page\InteriorIdea;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * A non-destructive inventory to decide whether the retired interior ideas
 * subsystem can be removed. It does not disable APIs, delete data or write files.
 */
class AuditInteriorIdeasRetirement extends Command
{
    protected $signature = 'content:audit-interior-ideas
        {--verify-files : Check physical files for a bounded sample of media}
        {--file-limit=100 : Maximum media records to check (1-1000)}';

    protected $description = 'Read-only inventory of legacy interior ideas, hotspots and media before decommissioning';

    public function handle(): int
    {
        $mediaClass = config('media-library.media_model', Media::class);
        $mediaTable = (new $mediaClass())->getTable();
        $requiredTables = array_unique(['interior_ideas', 'interior_idea_hotspots', 'products', $mediaTable]);
        $missingTables = array_values(array_filter($requiredTables, fn (string $table): bool => ! Schema::hasTable($table)));

        if ($missingTables !== []) {
            $this->printReport([
                'read_only' => true,
                'status' => 'missing_tables',
                'missing_tables' => $missingTables,
                'external_api_consumers_verified' => false,
                'safe_to_delete' => false,
            ]);

            return self::FAILURE;
        }

        $morphType = (new InteriorIdea())->getMorphClass();
        $media = DB::table($mediaTable)->where('model_type', $morphType);
        $orphanIdeaHotspots = DB::table('interior_idea_hotspots')
            ->whereNotIn('interior_idea_id', fn ($query) => $query->select('id')->from('interior_ideas'))
            ->count();
        $orphanProductHotspots = DB::table('interior_idea_hotspots')
            ->whereNotIn('product_id', fn ($query) => $query->select('id')->from('products'))
            ->count();
        $orphanMedia = (clone $media)
            ->whereNotIn('model_id', fn ($query) => $query->select('id')->from('interior_ideas'))
            ->count();

        $report = [
            'read_only' => true,
            'status' => 'inventory_complete',
            'ideas' => [
                'total' => DB::table('interior_ideas')->count(),
                'active' => DB::table('interior_ideas')->where('is_active', true)->count(),
            ],
            'hotspots' => [
                'total' => DB::table('interior_idea_hotspots')->count(),
                'missing_idea' => $orphanIdeaHotspots,
                'missing_product' => $orphanProductHotspots,
            ],
            'media' => [
                'morph_type' => $morphType,
                'total' => (clone $media)->count(),
                'image_collection' => (clone $media)->where('collection_name', 'image')->count(),
                'missing_idea' => $orphanMedia,
                'by_disk' => (clone $media)
                    ->select('disk')
                    ->selectRaw('COUNT(*) AS total')
                    ->groupBy('disk')
                    ->orderBy('disk')
                    ->get()
                    ->mapWithKeys(fn ($row): array => [$row->disk => (int) $row->total])
                    ->all(),
            ],
            // Internal SQL inventory cannot identify other clients calling the public endpoint.
            'external_api_consumers_verified' => false,
            'safe_to_delete' => false,
        ];

        if ($this->option('verify-files')) {
            $limit = filter_var($this->option('file-limit'), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 1000],
            ]);
            if ($limit === false) {
                $report['status'] = 'invalid_file_limit';
                $report['media_file_check'] = ['error' => 'file-limit must be between 1 and 1000'];
                $this->printReport($report);

                return self::FAILURE;
            }

            $checked = 0;
            $missingOriginals = [];
            $missingConversions = [];
            $storageErrors = [];
            foreach ($mediaClass::query()->where('model_type', $morphType)->orderBy('id')->limit($limit)->get() as $item) {
                $checked++;
                try {
                    if (! Storage::disk($item->disk)->exists($item->getPathRelativeToRoot())) {
                        $missingOriginals[] = $item->id;
                    }

                    foreach (['thumb', 'main'] as $conversion) {
                        if ($item->hasGeneratedConversion($conversion)
                            && ! Storage::disk($item->conversions_disk ?: $item->disk)
                                ->exists($item->getPathRelativeToRoot($conversion))) {
                            $missingConversions[] = ['media_id' => $item->id, 'conversion' => $conversion];
                        }
                    }
                } catch (Throwable $e) {
                    $storageErrors[] = ['media_id' => $item->id, 'exception' => get_class($e)];
                }
            }

            $report['media_file_check'] = [
                'sample_limit' => $limit,
                'checked' => $checked,
                'total_media' => $report['media']['total'],
                'missing_originals_count' => count($missingOriginals),
                'missing_original_ids' => array_slice($missingOriginals, 0, 20),
                'missing_conversions_count' => count($missingConversions),
                'missing_conversions_sample' => array_slice($missingConversions, 0, 20),
                'storage_errors_count' => count($storageErrors),
                'storage_errors_sample' => array_slice($storageErrors, 0, 20),
            ];
        }

        $this->printReport($report);

        return self::SUCCESS;
    }

    private function printReport(array $report): void
    {
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
