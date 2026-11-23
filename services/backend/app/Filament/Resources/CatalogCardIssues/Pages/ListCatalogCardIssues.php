<?php

namespace App\Filament\Resources\CatalogCardIssues\Pages;

use App\Filament\Resources\CatalogCardIssues\CatalogCardIssueResource;
use App\Models\CatalogCardAuditRun;
use Filament\Resources\Pages\ListRecords;

class ListCatalogCardIssues extends ListRecords
{
    protected static string $resource = CatalogCardIssueResource::class;

    public function getSubheading(): ?string
    {
        $run = CatalogCardAuditRun::latestCompleted();
        if (! $run) {
            return 'Фоновая проверка ещё не завершалась. После запуска появятся причины и дата.';
        }

        return 'Проверено '.$run->checked_count.' карточек · результат '
            .$run->finished_at?->format('d.m.Y H:i')
            .($run->files_checked_at ? ' · файлы проверены' : ' · файлы не проверены');
    }
}
