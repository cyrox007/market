import { CreditCard } from 'lucide-react';
import { isOnlineCardPaymentMethod } from '../../../utils/paymentUtils';
import { Radio, RadioGroup } from '../../../components/ui/primitives/Radio';
import type { CheckoutState } from '../hooks/useWarehouseCheckout';
import { selectable } from '../hooks/useWarehouseCheckout';
import CheckoutSelect from './CheckoutSelect';

export default function CheckoutPayment({ checkout: c }: { checkout: CheckoutState }) {
  const online = c.methods.filter((method) => isOnlineCardPaymentMethod(method.code));
  const current = c.methods.find((method) => method.code === c.payment);
  const group = isOnlineCardPaymentMethod(c.payment)
    ? 'online'
    : c.payment === 'cash'
      ? 'cash'
      : 'other';
  return (
    <section className="space-y-4" aria-labelledby="checkout-payment">
      <h2 id="checkout-payment" className="text-20 font-semibold max-vsm:text-16">
        Оплата
      </h2>
      <RadioGroup
        label="Тип оплаты"
        value={group}
        onChange={(value) => {
          const code =
            value === 'online'
              ? online.find(selectable)?.code
              : value === 'cash'
                ? 'cash'
                : c.methods.find(
                    (method) =>
                      selectable(method) &&
                      method.code !== 'cash' &&
                      !isOnlineCardPaymentMethod(method.code),
                  )?.code;
          if (code) c.setPayment(code);
        }}
        className="!flex-row flex-wrap gap-x-6 gap-y-3"
      >
        {!!online.length && (
          <Radio
            value="online"
            label="Онлайн"
            disabled={!online.some(selectable)}
            className="!flex-row-reverse !bg-transparent !p-0 !text-14 !font-medium"
          />
        )}
        {c.methods.some((method) => method.code === 'cash') && (
          <Radio
            value="cash"
            label="Наличными при получении"
            disabled={!selectable(c.methods.find((method) => method.code === 'cash'))}
            className="!flex-row-reverse !bg-transparent !p-0 !text-14 !font-medium"
          />
        )}
        {c.methods.some(
          (method) => method.code !== 'cash' && !isOnlineCardPaymentMethod(method.code),
        ) && (
          <Radio
            value="other"
            label="Другие способы"
            className="!flex-row-reverse !bg-transparent !p-0 !text-14 !font-medium"
          />
        )}
      </RadioGroup>
      {group === 'online' && (
        <div className="flex flex-wrap gap-3 text-12 text-ink-secondary">
          {online.filter(selectable).map((method) => (
            <span
              key={method.code}
              className="inline-flex items-center gap-2 rounded-lg border border-surface-border px-3 py-2"
            >
              <CreditCard aria-hidden="true" className="size-5" />
              {method.name}
            </span>
          ))}
        </div>
      )}
      <CheckoutSelect
        label="Способ оплаты"
        value={c.payment}
        onChange={c.setPayment}
        placeholder="Нет доступных способов оплаты"
        options={c.methods.map((method) => ({
          value: method.code,
          label: `${method.name}${method.is_coming_soon ? ' — Скоро' : ''}`,
          disabled: !selectable(method),
        }))}
      />
      {current?.description && <p className="text-12 text-ink-secondary">{current.description}</p>}
    </section>
  );
}
