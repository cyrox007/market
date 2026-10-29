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
        $rows = WarehouseDeliveryProfile::query()->orderBy('id')->get()->map(function (WarehouseDeliveryProfile $profile): array {
            $source = $profile->service_tariffs !== null ? 'profile' : 'UNCONFIGURED';

            return [$profile->id, $profile->warehouse_id, $profile->name, $profile->is_active ? 'yes' : 'no', $source,
                $profile->services_location_id ?? '-',
                $profile->service_tariffs === null ? 'PROFILE TARIFFS REQUIRED' : ''];
        });
        $this->table(['Profile', 'Warehouse', 'Name', 'Active', 'Tariff source', 'Legacy location', 'Warning'], $rows);
        $this->info('Read-only: no prices or profiles were changed. Unconfigured profiles cannot calculate shipping. Set profile tariffs explicitly.');

        return self::SUCCESS;
    }
}
