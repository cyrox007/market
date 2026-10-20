<?php

namespace App\Livewire;

use App\Filament\Pages\CatalogTransferPage;
use Illuminate\Contracts\View\View;

/** Isolated polling keeps the upload form and expanded results untouched. */
class CatalogTransferProgress extends CatalogTransferPage
{
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function render(): View
    {
        return view('livewire.catalog-transfer-progress', $this->getViewData());
    }

    public function applyRun(): void
    {
        parent::applyRun();
        $this->dispatch('catalog-transfer-selected', id: $this->runId);
    }
}
