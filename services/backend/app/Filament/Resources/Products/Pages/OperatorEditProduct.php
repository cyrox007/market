<?php

namespace App\Filament\Resources\Products\Pages;

class OperatorEditProduct extends EditProduct
{
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Карточка товара';
    }
}
