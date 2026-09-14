<?php

namespace App\Filament\Resources\Products\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class OperatorEditProduct extends EditProduct
{
    public static bool $formActionsAreSticky = false;

    /**
     * Null means the product card. Relation managers are workspaces, not a second
     * row of tabs, so we render only the selected workspace.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->activeRelationManager === null
                    ? $this->getFormContentComponent()
                    : $this->getActiveRelationManagerContentComponent(),
            ]);
    }

    /**
     * Saving is always available in the header and never covers form content.
     */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getWorkspaceActionGroup(),

            Action::make('saveHeader')
                ->label('Сохранить')
                ->icon('heroicon-m-check')
                ->color('primary')
                ->action('save')
                ->keyBindings(['mod+s'])
                ->visible(fn (): bool => $this->activeRelationManager === null),

            Action::make('saveAndExitHeader')
                ->label('Сохранить и выйти')
                ->icon('heroicon-m-arrow-right-start-on-rectangle')
                ->color('gray')
                ->action('saveAndExit')
                ->keyBindings(['mod+shift+s'])
                ->visible(fn (): bool => $this->activeRelationManager === null),

            ActionGroup::make(parent::getHeaderActions())
                ->label('Действия')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button()
                ->dropdownWidth(Width::Medium),
        ];
    }

    protected function getWorkspaceActionGroup(): ActionGroup
    {
        $record = $this->getRecord();
        $pageClass = static::class;

        $actions = [
            Action::make('workspace-card')
                ->label('Карточка товара')
                ->icon('heroicon-m-pencil-square')
                ->disabled($this->activeRelationManager === null)
                ->action(fn () => $this->activeRelationManager = null),
        ];

        foreach ($this->getRelationManagers() as $key => $manager) {
            if (! is_string($manager) && ! ($manager instanceof RelationManagerConfiguration)) {
                continue;
            }

            $managerClass = $this->normalizeRelationManagerClass($manager);
            $relationKey = (string) $key;

            $actions[] = Action::make("workspace-relation-{$relationKey}")
                ->label($managerClass::getTitle($record, $pageClass))
                ->icon($managerClass::getIcon($record, $pageClass))
                ->disabled($this->activeRelationManager === $relationKey)
                ->action(fn () => $this->activeRelationManager = $relationKey);
        }

        return ActionGroup::make($actions)
            ->label('Раздел: ' . $this->getWorkspaceLabel())
            ->icon('heroicon-m-squares-2x2')
            ->color('gray')
            ->button()
            ->dropdownWidth(Width::Medium);
    }

    protected function getWorkspaceLabel(): string
    {
        if ($this->activeRelationManager === null) {
            return 'Карточка товара';
        }

        $manager = $this->getRelationManagers()[$this->activeRelationManager] ?? null;

        if (! is_string($manager) && ! ($manager instanceof RelationManagerConfiguration)) {
            return 'Карточка товара';
        }

        $managerClass = $this->normalizeRelationManagerClass($manager);

        return $managerClass::getTitle($this->getRecord(), static::class);
    }

    protected function getActiveRelationManagerContentComponent(): Component
    {
        $manager = $this->getRelationManagers()[$this->activeRelationManager] ?? null;

        if (! is_string($manager) && ! ($manager instanceof RelationManagerConfiguration)) {
            return $this->getFormContentComponent();
        }

        $managerClass = $this->normalizeRelationManagerClass($manager);
        $managerLivewireData = [
            'ownerRecord' => $this->getRecord(),
            'pageClass' => static::class,
        ];

        if ($activeLocale = (property_exists($this, 'activeLocale') ? $this->activeLocale : null)) {
            $managerLivewireData['activeLocale'] = $activeLocale;
        }

        $managerProperties = $manager instanceof RelationManagerConfiguration
            ? [...$managerClass::getDefaultProperties(), ...$manager->getProperties()]
            : $managerClass::getDefaultProperties();

        return Livewire::make(
            $managerClass,
            [...$managerLivewireData, ...$managerProperties],
        )->key("product-workspace-{$managerClass}");
    }
}
