import { isOnlineCardPaymentMethod } from '../../../utils/paymentUtils';
import { Radio, RadioGroup } from '../../../components/ui/primitives/Radio';
import type { CheckoutState } from '../hooks/useWarehouseCheckout';
import { selectable } from '../hooks/useWarehouseCheckout';
import CheckoutSelect from './CheckoutSelect';

export default function CheckoutPayment({
  checkout: c,
}: {
  checkout: Pick<CheckoutState, 'methods' | 'payment' | 'setPayment'>;
}) {
  const online = c.methods.filter((method) => isOnlineCardPaymentMethod(method.code));
  const current = c.methods.find((method) => method.code === c.payment);
  const group = c.payment === 'cash' ? 'cash' : 'online';
  return (
    <section className="space-y-4" aria-labelledby="checkout-payment">
      <h2 id="checkout-payment" className="text-20 font-semibold max-vsm:text-16">
        Оплата
      </h2>
      <RadioGroup
        label="Тип оплаты"
        value={group}
        onChange={(value) => {
          const code = value === 'cash' ? 'cash' : online.find(selectable)?.code;
          if (code) c.setPayment(code);
        }}
        className="!flex-row flex-wrap gap-x-6 gap-y-3"
      >
        <Radio
          value="online"
          label="Онлайн"
          disabled={!online.some(selectable)}
          className="!flex-row-reverse !bg-transparent !p-0 !text-14 !font-medium"
        />
        <Radio
          value="cash"
          label="Наличными при получении"
          disabled={!selectable(c.methods.find((method) => method.code === 'cash'))}
          className="!flex-row-reverse !bg-transparent !p-0 !text-14 !font-medium"
        />
      </RadioGroup>
      {group === 'online' && (
        <CheckoutSelect
          label="Способ оплаты"
          value={c.payment}
          onChange={c.setPayment}
          placeholder="Нет доступных способов оплаты"
          options={online.map((method) => ({
            value: method.code,
            label: `${method.name}${method.is_coming_soon ? ' — Скоро' : ''}`,
            disabled: !selectable(method),
          }))}
        />
      )}
      {current?.description && <p className="text-12 text-ink-secondary">{current.description}</p>}
    </section>
  );
}
