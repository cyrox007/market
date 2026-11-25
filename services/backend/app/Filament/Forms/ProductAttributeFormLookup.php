<?php

namespace App\Filament\Forms;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use Closure;
use Filament\Forms\Components\Field;

final class ProductAttributeFormLookup
{
    private array $options = [];

    private array $attributes = [];

    private array $values = [];

    public static function current(): self
    {
        $attributes = request()->attributes;
        $lookup = $attributes->get(self::class);

        if (! $lookup instanceof self) {
            $lookup = new self;
            $attributes->set(self::class, $lookup);
        }

        return $lookup;
    }

    public function options(string $key, Closure $load): array
    {
        return $this->options[$key] ??= $load()->all();
    }

    public function attribute(Field $component, ?int $id): ?Attribute
    {
        if (! $id) {
            return null;
        }

        if (! array_key_exists($id, $this->attributes)) {
            $missing = array_diff($this->selectedIds($component, $id), array_keys($this->attributes));
            $this->attributes += Attribute::query()->whereKey($missing)->get()->keyBy('id')->all()
                + array_fill_keys($missing, null);
        }

        return $this->attributes[$id];
    }

    public function values(Field $component, ?int $id, bool $ordered = false): array
    {
        if (! $id) {
            return [];
        }

        if (! isset($this->values[$ordered][$id])) {
            $missing = array_diff($this->selectedIds($component, $id), array_keys($this->values[$ordered] ?? []));
            $query = AttributeValue::query()->whereIn('attribute_id', $missing);
            if ($ordered) {
                $query->orderBy('sort_order')->orderBy('value');
            }

            $values = $query->get(['attribute_id', 'id', 'value'])->groupBy('attribute_id')
                ->map(fn ($rows) => $rows->pluck('value', 'id')->all())->all();
            $this->values[$ordered] = ($this->values[$ordered] ?? []) + $values + array_fill_keys($missing, []);
        }

        return $this->values[$ordered][$id];
    }

    public function forgetOptions(): void
    {
        $this->options = [];
    }

    public function forgetValues(int $id): void
    {
        unset($this->values[false][$id], $this->values[true][$id]);
    }

    private function selectedIds(Field $component, int $id): array
    {
        $rows = $component->getContainer()->getParentComponent()?->getRawState();

        return array_unique([$id, ...array_filter(array_map('intval', array_column((array) $rows, 'attribute_id')))]);
    }
}
