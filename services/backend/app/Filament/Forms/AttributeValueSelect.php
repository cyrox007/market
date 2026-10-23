<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Select;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/** A value picker whose single/multiple mode can change while editing a row. */
class AttributeValueSelect extends Select
{
    public function getLivewireKey(): ?string
    {
        $key = parent::getLivewireKey();

        return $key === null ? null : $key.'.'.($this->isMultiple() ? 'multiple' : 'single');
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function getOptionLabel(bool $withDefault = true): ?string
    {
        // A request from the previous single-select widget can arrive after the
        // attribute changed to multiple. Do not use its array state as an option
        // key or flatten it: the current widget loads all labels separately.
        if ($this->isMultiple() || is_array($this->getState())) {
            return null;
        }

        return parent::getOptionLabel($withDefault);
    }
}
