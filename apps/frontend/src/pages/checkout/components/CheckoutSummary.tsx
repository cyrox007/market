import { Link } from 'react-router-dom';
import { ImageIcon } from 'lucide-react';
import Button from '../../../components/ui/primitives/Button';
import Checkbox from '../../../components/ui/primitives/Checkbox';
import CartLink from '../../../components/cart/CartLink';
import { cartQuantityLabel } from '../../../components/cart/cartPanelModel';
import type { CheckoutState } from '../hooks/useWarehouseCheckout';

export default function CheckoutSummary({ checkout: c }: { checkout: CheckoutState }) {
  const rows = !c.direct
    ? (c.cart?.items ?? [])
    : (c.prepared?.priced_components?.map((part) => ({
        id: part.product_id,
        name: part.name,
        price: part.unit_price,
        quantity: part.quantity,
        total: part.total,
        image: null,
        variation_attributes: [],
        bundle_components: [],
      })) ??
      c.items.map((item) => ({
        id: item.product_id,
        name: c.directProduct?.name || `Товар № ${item.product_id}`,
        image: c.directProduct?.thumbnail || c.directProduct?.image,
        price: c.prepared?.unit_price ?? c.subtotal / item.quantity,
        quantity: item.quantity,
        total: c.subtotal,
        variation_attributes: [],
        bundle_components: [],
      })));
  const money = (value: number) => `${value.toLocaleString('ru-RU')} ₽`;
  return (
    <aside
      aria-labelledby="checkout-summary"
      className="flex min-w-0 flex-col gap-4 rounded-card bg-surface-grey p-6 max-vsm:p-4"
    >
      <div className="flex items-center justify-between gap-3">
        <h2 id="checkout-summary" className="text-20 font-semibold max-vsm:text-16">
          Детали заказа
        </h2>
        <span className="whitespace-nowrap text-14 text-ink-secondary">
          {cartQuantityLabel(rows.reduce((sum, item) => sum + item.quantity, 0))}
        </span>
      </div>
      <div className="space-y-3">
        {rows.map((item) => (
          <div
            key={item.id}
            className="flex items-center gap-4 rounded-card bg-surface p-3 max-vsm:items-start max-vsm:gap-3"
          >
            <div className="flex size-[92px] shrink-0 items-center justify-center overflow-hidden rounded bg-surface-grey max-vsm:size-16">
              {item.image ? (
                <img src={item.image} alt="" className="size-full object-cover" />
              ) : (
                <ImageIcon aria-hidden="true" className="size-6 text-ink-secondary" />
              )}
            </div>
            <div className="min-w-0 flex-1 space-y-1.5">
              <p className="break-words text-16 font-medium max-vsm:text-14">{item.name}</p>
              {!!item.variation_attributes?.length && (
                <p className="text-14 text-ink-secondary">
                  {item.variation_attributes
                    .map((attribute) => attribute.value_name || attribute.value_slug)
                    .join(' · ')}
                </p>
              )}
              {!!item.bundle_components?.length && (
                <p className="text-12 text-ink-secondary">
                  Комплектация: {item.bundle_components.map((part) => part.name).join(', ')}
                </p>
              )}
              <p className="text-12 text-ink-secondary">
                {item.quantity} шт. × {money(item.price)}
              </p>
              <p className="text-14 font-semibold">{money(item.total)}</p>
            </div>
          </div>
        ))}
      </div>
      <div className="mt-12 space-y-4 max-md:mt-4">
        <div className="flex items-baseline gap-2 text-14">
          <span className="text-ink-secondary">Стоимость товаров</span>
          <span
            aria-hidden="true"
            className="flex-1 border-b border-dashed border-surface-border"
          />
          <span>{money(c.subtotal)}</span>
        </div>
        <div className="flex items-baseline gap-2 text-14">
          <span className="text-ink-secondary">
            {c.mode === 'pickup' ? 'Самовывоз' : 'Доставка и услуги'}
          </span>
          <span
            aria-hidden="true"
            className="flex-1 border-b border-dashed border-surface-border"
          />
          <span>
            {c.calculating
              ? 'Расчёт…'
              : c.fulfillment == null
                ? 'Не рассчитана'
                : money(c.fulfillment)}
          </span>
        </div>
        <div className="flex justify-between gap-3 text-24 font-semibold max-vsm:text-20">
          <span>Итого</span>
          <output aria-label="Итоговая стоимость">{money(c.total)}</output>
        </div>
        {c.mode === 'delivery' && !c.selected && (
          <p className="text-12 text-ink-secondary">Выберите адрес для расчёта полной стоимости.</p>
        )}
        {c.hasUnavailable && (
          <p role="alert" className="text-14 text-brand-red">
            Удалите недоступные товары. <CartLink className="underline">Открыть корзину</CartLink>
          </p>
        )}
        <Checkbox
          required
          checked={c.consent}
          onChange={c.setConsent}
          className="items-start !text-12"
          label={
            <>
              Принимаю{' '}
              <Link to="/oferta" className="underline">
                оферту
              </Link>{' '}
              и условия{' '}
              <Link to="/privacy" className="underline">
                обработки персональных данных
              </Link>
              .
            </>
          }
        />
        <Button
          type="submit"
          shape="rounded"
          size="lg"
          fullWidth
          className="shadow-card max-vsm:!h-11 max-vsm:!text-14"
          isLoading={c.busy}
          disabled={!c.canSubmit}
        >
          Оформить заказ
        </Button>
        <p className="text-12 text-ink-secondary">
          После оформления — 60 минут в обработке у менеджера. В это время заказ можно отменить
          самостоятельно.
        </p>
      </div>
    </aside>
  );
}
