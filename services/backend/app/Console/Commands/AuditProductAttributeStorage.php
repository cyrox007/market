<?php

namespace App\Console\Commands;

use App\Services\Product\ConsolidateProductAttributes;
use Illuminate\Console\Command;

class AuditProductAttributeStorage extends Command
{
    protected $signature = 'catalog:attribute-storage-audit';

    protected $description = 'Read-only preflight of legacy offer attributes before consolidation';

    public function handle(ConsolidateProductAttributes $service): int
    {
        $report = $service->audit();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $report['conflicts_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
