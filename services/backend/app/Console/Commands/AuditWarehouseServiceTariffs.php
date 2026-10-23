<?php

namespace App\Console\Commands;

use App\Models\Shipping\WarehouseDeliveryProfile;
use Illuminate\Console\Command;

class AuditWarehouseServiceTariffs extends Command
{
    protected $signature = 'shipping:audit-service-tariffs';

    protected $description = 'Read-only inventory of warehouse tariff migration readiness';

    public function handle(): int
    {
        $rows = WarehouseDeliveryProfile::with('servicesLocation')->orderBy('id')->get()->map(function (WarehouseDeliveryProfile $profile): array {
            $source = $profile->service_tariffs !== null ? 'profile' : ($profile->services_location_id ? 'legacy' : 'no services');

            return [$profile->id, $profile->warehouse_id, $profile->name, $profile->is_active ? 'yes' : 'no', $source,
                $profile->services_location_id ?? '-',
                $profile->services_location_id && ! $profile->servicesLocation ? 'MISSING LEGACY LOCATION' : ''];
        });
        $this->table(['Profile', 'Warehouse', 'Name', 'Active', 'Tariff source', 'Legacy location', 'Warning'], $rows);
        $this->info('Read-only: no prices or profiles were changed. Compare assembly, handling and extra-service quotes before enabling profile tariffs.');

        return self::SUCCESS;
    }
}
