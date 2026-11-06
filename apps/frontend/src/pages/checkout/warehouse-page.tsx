import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import CartLink from '../../components/cart/CartLink';
import { PAGE_CONTAINER } from '../../lib/layout';
import { useWarehouseCheckout } from './hooks/useWarehouseCheckout';
import { CheckoutContacts } from './components/CheckoutContacts';
import CheckoutDelivery from './components/CheckoutDelivery';
import CheckoutPayment from './components/CheckoutPayment';
import CheckoutSummary from './components/CheckoutSummary';

export default function WarehouseCheckout() {
  const checkout = useWarehouseCheckout();
  return (
    <div className={`${PAGE_CONTAINER} py-6 pb-16`}>
      <h1 className="sr-only">Оформление заказа</h1>
      <nav
        aria-label="Хлебные крошки"
        className="mb-6 flex flex-wrap items-center gap-2 text-14 max-vsm:mb-4"
      >
        <Link to="/">Главная</Link>
        <ChevronRight aria-hidden="true" className="size-4" />
        <CartLink>Корзина</CartLink>
        <ChevronRight aria-hidden="true" className="size-4" />
        <span aria-current="page" className="text-ink-secondary">
          Оформление заказа
        </span>
      </nav>
      {checkout.error && (
        <p role="alert" className="mb-6 rounded-btn bg-brand-red/10 p-4 text-14 text-brand-red">
          {checkout.error}
        </p>
      )}
      {!checkout.items.length && (
        <p className="mb-6 text-14 text-ink-secondary">
          {checkout.isLoading || (checkout.direct && !checkout.prepared && !checkout.error) ? (
            'Загружаем товары для оформления…'
          ) : (
            <>
              Нет товаров для оформления.{' '}
              <CartLink className="underline">Перейти в корзину</CartLink>
            </>
          )}
        </p>
      )}
      <form
        onSubmit={checkout.submit}
        className="grid grid-cols-[minmax(0,480px)_minmax(0,1fr)] items-start gap-11 max-md:grid-cols-1 max-md:gap-11"
      >
        <div className="min-w-0 space-y-8">
          <CheckoutContacts
            value={checkout.contact}
            onChange={checkout.setContact}
            showErrors={checkout.contactValidationAttempted}
          />
          <CheckoutDelivery checkout={checkout} />
          <section className="space-y-4">
            <h2 className="text-20 font-semibold max-vsm:text-16">
              <label htmlFor="checkout-comment">Комментарий к заказу</label>
            </h2>
            <textarea
              id="checkout-comment"
              maxLength={1000}
              rows={4}
              placeholder="Комментарий"
              value={checkout.comment}
              onChange={(event) => checkout.setComment(event.target.value)}
              className="block min-h-[114px] w-full resize-y rounded-btn border border-surface-stroke/[0.08] bg-surface px-5 py-3.5 text-14 outline-none placeholder:text-ink-secondary focus:border-ink-secondary"
            />
          </section>
          <CheckoutPayment checkout={checkout} />
        </div>
        <CheckoutSummary checkout={checkout} />
      </form>
    </div>
  );
}
