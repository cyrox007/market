<?php

namespace App\Models\Pricing;

use App\Models\Traits\InvalidatesFulfillment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PricingRule extends Model
{
    use InvalidatesFulfillment, SoftDeletes;

    public const ACTIONS = ['discount_percent' => 'Скидка в процентах', 'discount_fixed' => 'Скидка в рублях', 'markup_percent' => 'Наценка в процентах', 'markup_fixed' => 'Наценка в рублях', 'fixed_price' => 'Установить цену'];

    public const TARGETS = ['all' => 'Все товары', 'products' => 'Выбранные товары и варианты', 'categories' => 'Категории', 'rooms' => 'Комнаты', 'groups' => 'Подборки товаров'];

    public const AUDIENCES = ['all' => 'Все покупатели', 'guests' => 'Только гости', 'registered' => 'Все зарегистрированные', 'users' => 'Выбранные покупатели', 'groups' => 'Группы покупателей', 'conditions' => 'Покупатели по условиям'];

    public const ARRAYS = ['product_ids', 'category_ids', 'room_ids', 'product_group_ids', 'excluded_product_ids', 'user_ids', 'customer_group_ids', 'location_ids'];

    protected $guarded = ['id'];

    protected $attributes = ['is_active' => false, 'priority' => 0, 'target_type' => 'all', 'audience_type' => 'all'];

    protected function casts(): array
    {
        return array_merge(array_fill_keys(self::ARRAYS, 'array'), ['conditions' => 'array', 'is_active' => 'boolean', 'amount' => 'decimal:2', 'priority' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime']);
    }

    public function validateConfiguration(): void
    {
        $data = $this->attributesToArray();
        $rules = ['name' => 'required|string|max:255', 'target_type' => ['required', Rule::in(array_keys(self::TARGETS))],
            'audience_type' => ['required', Rule::in(array_keys(self::AUDIENCES))], 'adjustment_type' => ['required', Rule::in(array_keys(self::ACTIONS))],
            'amount' => 'required|numeric|min:0|max:999999999999', 'is_active' => 'required|boolean', 'priority' => 'required|integer|between:0,10000',
            'starts_at' => 'nullable|date', 'ends_at' => $this->starts_at ? 'nullable|date|after:starts_at' : 'nullable|date', 'conditions' => 'nullable|array:min_orders,max_orders,min_spend,min_account_days',
            'conditions.min_orders' => 'nullable|integer|min:0', 'conditions.max_orders' => 'nullable|integer|min:0',
            'conditions.min_spend' => 'nullable|numeric|min:0', 'conditions.min_account_days' => 'nullable|integer|min:0'];
        foreach (['product_ids' => 'products', 'excluded_product_ids' => 'products', 'category_ids' => 'taxons', 'room_ids' => 'taxons', 'product_group_ids' => 'pricing_product_groups', 'user_ids' => 'users', 'customer_group_ids' => 'pricing_customer_groups', 'location_ids' => 'shipping_locations'] as $field => $table) {
            $rules[$field] = 'nullable|array|max:500';
            $rules[$field.'.*'] = 'integer|distinct|exists:'.$table.',id';
        }
        Validator::make($data, $rules)->validate();
        $conditions = $this->conditions ?? [];
        if (isset($conditions['min_orders'], $conditions['max_orders']) && $conditions['max_orders'] < $conditions['min_orders']) {
            throw ValidationException::withMessages(['conditions.max_orders' => 'Верхняя граница должна быть не меньше нижней.']);
        }
        foreach (['products' => 'product_ids', 'categories' => 'category_ids', 'rooms' => 'room_ids', 'groups' => 'product_group_ids'] as $type => $field) {
            if ($this->target_type === $type && empty($this->$field)) {
                throw ValidationException::withMessages([$field => 'Выберите хотя бы один пункт. Пустой выбор не означает «все товары».']);
            }
        }
        foreach (['users' => 'user_ids', 'groups' => 'customer_group_ids', 'conditions' => 'conditions'] as $type => $field) {
            if ($this->audience_type === $type && ! collect($this->$field)->contains(fn ($value) => $value !== null && $value !== '')) {
                throw ValidationException::withMessages([$field => 'Заполните выбор покупателей или хотя бы одно условие.']);
            }
        }
        if ($this->adjustment_type === 'discount_percent' && $this->amount > 100) {
            throw ValidationException::withMessages(['amount' => 'Скидка не может превышать 100%.']);
        }
    }

    protected static function booted(): void
    {
        static::saving(fn (self $rule) => $rule->validateConfiguration());
        static::created(fn (self $rule) => $rule->audit('created'));
        static::updated(fn (self $rule) => $rule->audit('updated'));
        static::deleted(fn (self $rule) => $rule->audit('deleted'));
    }

    private function audit(string $event): void
    {
        DB::table('pricing_rule_changes')->insert(['pricing_rule_id' => $this->id, 'actor_id' => auth()->id(), 'event' => $event,
            'before' => json_encode($this->getRawOriginal()), 'after' => json_encode($this->getAttributes()), 'created_at' => now()]);
    }

    public function getSummaryAttribute(): string
    {
        $unit = str_ends_with($this->adjustment_type, 'percent') ? '%' : ' ₽';

        return (self::ACTIONS[$this->adjustment_type] ?? 'Изменение цены').' '.$this->amount.$unit.' · '.(self::TARGETS[$this->target_type] ?? '').' · '.(self::AUDIENCES[$this->audience_type] ?? '');
    }

    public function getStatusLabelAttribute(): string
    {
        return ! $this->is_active ? 'Выключено' : ($this->starts_at?->isFuture() ? 'Запланировано' : ($this->ends_at && $this->ends_at <= now() ? 'Завершено' : 'Действует'));
    }
}
