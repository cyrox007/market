import CheckoutSelect from './CheckoutSelect';
import CheckoutDeliveryEstimate from './CheckoutDeliveryEstimate';
import { Radio, RadioGroup } from '../../../components/ui/primitives/Radio';
import CheckoutServices from './CheckoutServices';
import Input from '../../../components/ui/primitives/Input';
import CheckoutAddresses from './CheckoutAddresses';
import type { CheckoutState } from '../hooks/useWarehouseCheckout';
import CheckoutPickupNotice from './CheckoutPickupNotice';

export const checkoutField =
  'block min-h-12 w-full rounded-btn border border-surface-stroke/[0.08] bg-surface px-4 py-3 text-14 text-ink outline-none focus:border-ink-secondary disabled:opacity-50';
const radioClass =
  '!flex-row-reverse !justify-end !gap-3 !rounded-none !bg-transparent !p-0 !text-14 !font-medium';
export default function CheckoutDelivery({ checkout: c }: { checkout: CheckoutState }) {
  const { services } = c;
  return (
    <section className="space-y-4" aria-labelledby="checkout-delivery">
      <div className="flex min-h-9 items-center justify-between gap-3">
        <h2 id="checkout-delivery" className="text-20 font-semibold max-vsm:text-16">
          Способ доставки
        </h2>
        {c.mode === 'pickup' ? (
          <span className="rounded-pill bg-brand-green px-3 py-2 text-14 font-medium text-white">
            Бесплатно
          </span>
        ) : (
          c.selected && (
            <span className="rounded-pill bg-brand-green px-3 py-2 text-14 font-medium text-white">
              + {c.selected.delivery_cost.toLocaleString('ru-RU')} ₽
            </span>
          )
        )}
      </div>
      <RadioGroup
        label="Способ получения"
        value={c.mode}
        onChange={(value) => c.setMode(value as 'delivery' | 'pickup')}
        className="!flex-row flex-wrap gap-6"
      >
        <Radio value="delivery" label="Доставка" className={radioClass} />
        <Radio value="pickup" label="Самовывоз" className={radioClass} />
      </RadioGroup>
      <div
        hidden={c.mode !== 'delivery'}
        className={c.mode === 'delivery' ? 'space-y-4' : 'hidden'}
      >
        <CheckoutAddresses checkout={c} />
        {!c.user && (
          <div className="grid grid-cols-2 gap-3">
            {(['apartment', 'entrance', 'intercom'] as const).map((key) => (
              <label key={key} className="space-y-2 text-14 text-ink-secondary">
                {
                  { apartment: 'Квартира / помещение', entrance: 'Подъезд', intercom: 'Домофон' }[
                    key
                  ]
                }
                <Input
                  shape="rounded"
                  tone="outline"
                  inputSize="field"
                  value={c.details[key]}
                  maxLength={64}
                  onChange={(event) => c.setDetails({ ...c.details, [key]: event.target.value })}
                />
              </label>
            ))}
          </div>
        )}
        {!c.user && (
          <label className="block space-y-2 text-14 text-ink-secondary">
            Этаж
            <Input
              type="number"
              min={1}
              max={100}
              value={c.floor}
              onChange={(event) => c.setFloor(Number(event.target.value))}
              shape="rounded"
              tone="outline"
            />
          </label>
        )}
        {c.calculating && (
          <p role="status" className="text-14 text-ink-secondary">
            Рассчитываем доставку…
          </p>
        )}
        {!!c.quote?.options.length && (
          <label className="block space-y-2 text-14 text-ink-secondary">
            Вариант доставки
            <select
              className={checkoutField}
              aria-label="Склад"
              value={c.selected?.warehouse_id || ''}
              onChange={(event) => {
                c.setWarehouse(Number(event.target.value));
                c.setHandling(null);
                c.setAdditional([]);
                c.setAssembly(false);
              }}
            >
              {c.quote.options.map((option) => (
                <option key={option.warehouse_id} value={option.warehouse_id}>
                  {option.warehouse_name} — {option.total_fulfillment_cost.toLocaleString('ru-RU')}{' '}
                  ₽
                </option>
              ))}
            </select>
          </label>
        )}
        {c.selected && <CheckoutDeliveryEstimate option={c.selected} />}
        {services && <CheckoutServices {...c} services={services} />}
      </div>
      <div hidden={c.mode !== 'pickup'} className={c.mode === 'pickup' ? 'space-y-4' : 'hidden'}>
        <CheckoutSelect
          label="Точка самовывоза"
          value={c.point}
          onChange={c.setPoint}
          placeholder="Выберите точку"
          options={c.pickup.map((point) => ({
            value: `${point.warehouse_id}:${point.store_id || ''}`,
            label: `${point.name} — ${point.address || ''}`,
          }))}
        />
        {!c.pickup.length && (
          <p className="text-14 text-ink-secondary">
            Для этих товаров нет доступных точек самовывоза.
          </p>
        )}
        {c.pickupPoint && <CheckoutPickupNotice point={c.pickupPoint} />}
      </div>
    </section>
  );
}
