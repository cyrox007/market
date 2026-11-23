<?php

namespace App\Console\Commands;

use App\Models\CatalogCardAuditRun;
use App\Models\Product\Product;
use App\Services\Catalog\CatalogCardQuality;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuditCatalogCards extends Command
{
    protected $signature = 'catalog:audit-cards {--skip-files : Skip physical media checks (useful for diagnostics/tests)}';

    protected $description = 'Scan active catalog cards in the background, retaining only the latest completed result';

    public function handle(CatalogCardQuality $quality): int
    {
        $lock = Cache::lock('catalog-card-audit', 7200);
        if (! $lock->get()) {
            $this->warn('Another card audit is already running.');

            return self::FAILURE;
        }

        $run = null;
        try {
            $verifyFiles = ! $this->option('skip-files');
            $run = CatalogCardAuditRun::query()->create([
                'status' => 'running',
                'started_at' => now(),
                'checked_count' => 0,
                'affected_count' => 0,
            ]);

            // Duplicate external IDs matter only across active sellable SKUs, not virtual parents.
            $duplicates = Product::query()->catalogItems()
                ->whereNotNull('external_id')
                ->where('external_id', '!=', '')
                ->select('external_id')->groupBy('external_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('external_id')->mapWithKeys(fn (string $id): array => [trim($id) => true])
                ->all();

            $checked = 0;
            $affected = 0;
            Product::query()->where('state', Product::ACTIVE)
                ->with([
                    'taxons', 'media', 'colorRows.solution',
                    'parentProduct.taxons', 'parentProduct.media',
                    'variants' => fn ($query) => $query->where('state', Product::ACTIVE)->orderBy('id'),
                    'variants.media',
                ])
                ->chunkById(100, function ($products) use ($run, $quality, $duplicates, $verifyFiles, &$checked, &$affected): void {
                    $issues = [];
                    foreach ($products as $product) {
                        $checked++;
                        $reasons = $quality->inspect($product, $duplicates, $verifyFiles);
                        $critical = count(array_filter($reasons, fn (array $reason) => $reason['severity'] === 'critical'));
                        $warnings = count(array_filter($reasons, fn (array $reason) => $reason['severity'] === 'warning'));
                        // One critical defect is enough; minor notes require at least two.
                        if ($critical === 0 && $warnings < 2) {
                            continue;
                        }

                        $affected++;
                        $issues[] = [
                            'run_id' => $run->id,
                            'product_id' => $product->id,
                            'critical_count' => $critical,
                            'warning_count' => $warnings,
                            'reasons' => json_encode($reasons, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                            'checked_at' => now(),
                        ];
                    }
                    if ($issues !== []) {
                        DB::table('catalog_card_issues')->insert($issues);
                    }
                });

            // Commit the completed marker only when every batch and file check succeeded.
            $run->update([
                'status' => 'completed',
                'checked_count' => $checked,
                'affected_count' => $affected,
                'files_checked_at' => $verifyFiles ? now() : null,
                'finished_at' => now(),
            ]);

            // Keep only a consistent complete snapshot; avoid unbounded historical growth.
            CatalogCardAuditRun::query()->where('id', '!=', $run->id)->delete();
            $this->info("Checked {$checked} active records; found {$affected} cards requiring attention.");

            return self::SUCCESS;
        } catch (Throwable $error) {
            if ($run) {
                $run->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error' => mb_substr($error->getMessage(), 0, 250),
                ]);
            }

            report($error);
            $this->error('Card audit failed; the previous completed snapshot is retained.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
