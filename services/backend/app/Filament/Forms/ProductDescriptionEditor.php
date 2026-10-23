<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\RichEditor;

class ProductDescriptionEditor extends RichEditor
{
    public function getTools(): array
    {
        $tools = parent::getTools();
        foreach ($tools as $tool) {
            $tool->extraAttributes([
                'aria-label' => $tool->getLabel(),
                'title' => $tool->getLabel(),
            ], merge: true);
        }

        return $tools;
    }
}
