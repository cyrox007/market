<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CatalogCardIssues\CatalogCardIssueResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\CatalogCardAuditRun;
use App\Models\CatalogCardIssue;
use App\Services\Catalog\CatalogCardQuality;
use Filament\Widgets\Widget;

class CatalogCardAttentionWidget extends Widget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.catalog-card-attention-widget';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeProducts();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canView(), 403);
        $run = CatalogCardAuditRun::latestCompleted();
        $lastAttempt = CatalogCardAuditRun::query()->latest('id')->first();

        if (! $run) {
            return ['run' => null, 'lastAttempt' => $lastAttempt, 'criticalCount' => 0, 'warningsCount' => 0, 'items' => [], 'allUrl' => null];
        }

        $query = CatalogCardIssue::query()->where('run_id', $run->id);
        $criticalCount = (clone $query)->where('critical_count', '>', 0)->count();
        $warningsCount = (clone $query)->where('critical_count', 0)->where('warning_count', '>=', 2)->count();
        $items = (clone $query)
            ->with('product')
            ->orderByDesc('critical_count')
            ->orderByDesc('warning_count')
            ->orderBy('product_id')
            ->limit(5)->get()
            ->map(static function (CatalogCardIssue $issue): array {
                $product = $issue->product;
                return [
                    'name' => $product?->name ?? 'Товар удалён',
                    'type' => $product ? CatalogCardQuality::typeLabel($product) : 'Неизвестный тип',
                    'critical' => $issue->critical_count > 0,
                    'reasons' => $issue->reasons ?? [],
                    'url' => $product && (ProductResource::canEdit($product) || ProductResource::canView($product))
                        ? ProductResource::getUrl(ProductResource::canEdit($product) ? 'edit' : 'view',
                            ['record' => $product]) : null,
                    'can_edit' => $product && ProductResource::canEdit($product),
                ];
            })->all();

        return [
            'run' => $run,
            'lastAttempt' => $lastAttempt,
            'criticalCount' => $criticalCount,
            'warningsCount' => $warningsCount,
            'items' => $items,
            'allUrl' => CatalogCardIssueResource::getUrl('index'),
            'criticalUrl' => CatalogCardIssueResource::getUrl('index', [
                'tableFilters' => ['severity' => ['value' => 'critical']],
            ]),
            'warningsUrl' => CatalogCardIssueResource::getUrl('index', [
                'tableFilters' => ['severity' => ['value' => 'warnings']],
            ]),
        ];
    }
}
