<?php

namespace App\Filament\Forms;

use App\Filament\Resources\Colors\ColorElementResource;
use App\Filament\Resources\Colors\ColorSolutionResource;
use App\Models\Product\Color\ColorElement;
use App\Models\Product\Color\ColorSolution;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Gate;

/** Блок «Цвета товара»: строка = элемент (пусто — основной цвет) + его цветовое решение. */
class ProductColorRowsField
{
    /** Значение пункта «Основной цвет»: в базе — строка без элемента. */
    private const MAIN = 'main';

    public static function section(?string $description = null): Section
    {
        return Section::make('Цвета товара')
            ->description($description)
            ->key('product_color_rows_section')
            ->schema([static::repeater()])
            ->collapsible();
    }

    public static function repeater(): Repeater
    {
        return Repeater::make('colorRows')
            ->hiddenLabel()
            // Свой шаблон таблицы: столбик перетаскивания есть и при одной строке, чтобы форма не дёргалась.
            ->view('filament.forms.product-color-rows-table')
            ->extraAttributes(['class' => 'product-color-rows-table'])
            ->relationship('colorRows')
            ->orderColumn('sort_order')
            ->reorderable()
            ->table([
                TableColumn::make('Элемент')->width('42%'),
                TableColumn::make('Цветовое решение')->markAsRequired(),
            ])
            ->schema([
                Select::make('color_element_id')
                    ->label('Элемент')
                    ->options(fn (): array => static::options('elements', fn (): array => [self::MAIN => 'Основной цвет']
                        + ColorElement::orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all()))
                    ->default(self::MAIN)
                    ->formatStateUsing(fn ($state) => $state ?? self::MAIN)
                    ->dehydrateStateUsing(fn ($state) => $state === self::MAIN ? null : $state)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->searchable()
                    ->createOptionForm(static::createOptionFields(ColorElementResource::formFields()))
                    ->createOptionUsing(fn (array $data): int => ColorElement::create($data)->getKey())
                    ->createOptionAction(fn (Action $action) => $action
                        ->authorize(fn (): bool => Gate::allows('create', ColorElement::class))
                        ->after(fn (Select $component) => static::refreshOptions($component, 'elements')))
                    ->createOptionModalHeading('Новый элемент'),

                Select::make('color_solution_id')
                    ->label('Цветовое решение')
                    ->relationship('solution', 'name', fn ($query) => $query->with('media'))
                    ->required()
                    ->validationMessages(['required' => 'Выберите решение.'])
                    ->searchable()
                    ->preload()
                    ->options(fn (Select $component): array => static::options(
                        'solutions:'.$component->getOptionsLimit(),
                        fn (): array => ColorSolution::query()->with('media')->orderBy('name')->orderBy('id')
                            ->limit($component->getOptionsLimit())->get()
                            ->mapWithKeys(fn (ColorSolution $solution) => [$solution->id => static::solutionLabel($solution)])->all(),
                    ))
                    ->allowHtml()
                    ->getOptionLabelFromRecordUsing(fn (ColorSolution $record): string => static::solutionLabel($record))
                    ->createOptionForm(static::createOptionFields(ColorSolutionResource::formFields()))
                    ->createOptionAction(fn (Action $action) => $action
                        ->authorize(fn (): bool => Gate::allows('create', ColorSolution::class))
                        ->after(fn (Select $component) => static::refreshOptions($component, 'solutions:'.$component->getOptionsLimit())))
                    ->createOptionModalHeading('Новое цветовое решение'),
            ])
            ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                $repeated = collect((array) $value)
                    ->map(fn ($row) => (string) ($row['color_element_id'] ?? self::MAIN))
                    ->duplicates()->unique();
                if ($repeated->contains(self::MAIN)) {
                    $fail('Основной цвет выбран в нескольких строках. У товара может быть только один основной цвет — оставьте одну строку.');
                }
                $elements = $repeated->reject(fn (string $id) => $id === self::MAIN);
                if ($elements->isNotEmpty()) {
                    $names = $elements->map(fn (string $id) => '«' . ColorElement::find($id)?->name . '»');
                    $fail($names->implode(', ') . ' выбран в нескольких строках. У элемента может быть только один цвет — оставьте одну строку.');
                }
            })
            ->defaultItems(0)
            ->addActionLabel('Добавить цвет');
    }

    private static function createOptionFields(array $fields): array
    {
        foreach ($fields as $field) {
            if ($field instanceof TextInput || $field instanceof Select) {
                $field->skipRenderAfterStateUpdated();
            }
        }

        return $fields;
    }

    private static function options(string $name, Closure $load): array
    {
        $attributes = request()->attributes;
        $key = self::class.':'.$name;

        if (! $attributes->has($key)) {
            $attributes->set($key, $load());
        }

        return $attributes->get($key);
    }

    private static function refreshOptions(Select $component, string $name): void
    {
        request()->attributes->remove(self::class.':'.$name);
        $component->getContainer()->getParentComponent()?->partiallyRender();
    }

    private static function solutionLabel(ColorSolution $record): string
    {
        $url = $record->getFirstMediaUrl('icon');
        $icon = $url !== ''
            ? '<img src="' . e($url) . '" alt="" style="width:20px;height:20px;object-fit:contain;display:inline-block;vertical-align:middle;margin-right:6px;border:1px solid rgb(0 0 0 / 0.2);border-radius:4px">'
            : '';

        return $icon . '<span title="' . e($record->name) . '">' . e($record->name) . '</span>';
    }
}
