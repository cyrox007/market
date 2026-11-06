<?php

namespace App\Console\Commands;

use App\Services\Address\AddressDirectoryClient;
use Illuminate\Console\Command;

/**
 * Cache hot public discovery data proactively while upstream is healthy.
 * No raw addresses, coordinates, user data or payment/checkout side effects.
 */
class WarmAddressDirectoryCache extends Command
{
    protected $signature = 'address-directory:warm-cache
                            {--dry-run : List warm-up requests without contacting the directory}';

    protected $description = 'Warm backend cache of directory regions and configured popular city searches';

    public function handle(AddressDirectoryClient $directory): int
    {
        $queries = [['level' => 'regions', 'query' => []]];
        $configured = explode(',', (string) config('address_directory.warm_localities', ''));
        foreach (array_slice(array_unique(array_filter(array_map('trim', $configured), fn ($s) => mb_strlen($s) >= 2)), 0, 20) as $name) {
            $queries[] = ['level' => 'localities', 'query' => ['q' => $name]];
        }

        $errors = 0;
        foreach ($queries as $item) {
            $city = $item['query']['q'] ?? null;
            $label = $item['level'].($city ? ': '.$city : '');
            if ($this->option('dry-run')) {
                $this->line('PLANNED '.$label);
                continue;
            }
            try {
                $items = $directory->options($item['level'], $item['query']);
                if ($directory->lastListCacheStatus() === 'stale') {
                    $this->warn('STALE '.$label.' (upstream unavailable; old data retained)');
                    $errors++;
                } elseif ($items === []) {
                    $this->warn('EMPTY '.$label);
                } else {
                    $this->line('OK '.$label.' ('.count($items).' entries)');
                }
            } catch (\Throwable $error) {
                $errors++;
                $this->error('FAIL '.$label.' (check server logs)');
                report($error);
            }
        }

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
