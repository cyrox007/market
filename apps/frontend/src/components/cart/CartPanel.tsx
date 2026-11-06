import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ShoppingCart, X } from 'lucide-react';
import { useCartPanel } from '../../contexts/cart-panel-context';
import { useCartToast } from '../../contexts/cart-toast-context';
import { useCart } from '../../hooks/useCart';
import { useWishlistAndCompare } from '../../hooks/useWishlistAndCompare';
import { useMediaQuery } from '../../hooks/useMediaQuery';
import { BELOW_MD } from '../../lib/layout';
import { getCartErrorMessage } from '../../utils/cartErrors';
import Sheet from '../ui/primitives/Sheet';
import Button from '../ui/primitives/Button';
import IconButton from '../ui/primitives/IconButton';
import CartPanelItem from './CartPanelItem';
import { cartPanelTotals, cartPanelSavings, cartQuantityLabel } from './cartPanelModel';
import { isSheetHistoryEntry } from '../ui/primitives/sheetHistory';

export default function CartPanel() {
  const { isOpen, closeCart } = useCartPanel();
  // Sheet сохраняет содержимое на время анимации закрытия.
  return <CartPanelContent open={isOpen} onClose={closeCart} />;
}

function CartPanelContent({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { cart, isLoading, error, setCartLineQuantity, clearCart, reloadCart } = useCart();
  const { wishlistProductIds, toggleWishlist } = useWishlistAndCompare();
  const { showCartToast } = useCartToast();
  const narrow = useMediaQuery(BELOW_MD);
  const navigate = useNavigate();
  const [confirmClear, setConfirmClear] = useState(false);
  const [clearing, setClearing] = useState(false);
  const [actionError, setActionError] = useState('');
  useEffect(() => {
    if (!open) {
      setConfirmClear(false);
      setActionError('');
    }
  }, [open]);
  const items = cart?.items ?? [];
  const totals = cartPanelTotals(items);
  const savings = cartPanelSavings(items);
  const loading = !cart && isLoading;
  const unavailable = totals.hasUnavailable;
  const goTo = (path: string) => {
    const replace = isSheetHistoryEntry();
    onClose();
    // Заменяем временную запись панели: «Назад» должен вернуть исходную страницу один раз.
    navigate(path, { replace });
  };
  function changeLine(item: (typeof items)[number], quantity: number, max?: number) {
    // Один SKU с разными ценами: накапливаем намерения по конкретной строке.
    const siblingQuantity = items
      .filter((line) => line.product_id === item.product_id && line.id !== item.id)
      .reduce((sum, line) => sum + line.quantity, 0);
    setCartLineQuantity(item.id, quantity, {
      max: max === undefined ? undefined : Math.max(item.quantity, max - siblingQuantity),
    });
  }
  async function confirm() {
    if (clearing) return;
    setClearing(true);
    setActionError('');
    try {
      await clearCart();
      setConfirmClear(false);
    } catch (problem) {
      setActionError(getCartErrorMessage(problem));
    } finally {
      setClearing(false);
    }
  }

  return (
    <Sheet
      open={open}
      onClose={onClose}
      side={narrow ? 'bottom' : 'right'}
      appearance="cart"
      title={
        <span className="flex items-center gap-2">
          Корзина{' '}
          {items.length > 0 && (
            <span className="text-14 font-medium text-ink-secondary">
              / {cartQuantityLabel(totals.quantity)}
            </span>
          )}
        </span>
      }
      headerAction={
        <div className="flex shrink-0 items-center gap-3">
          {!!items.length && (
            <>
              <button
                type="button"
                disabled={clearing}
                onClick={() => setConfirmClear(true)}
                className="text-14 font-medium text-ink-secondary hover:text-brand-green focus-visible:outline-brand-green"
              >
                Очистить
              </button>
              <span aria-hidden="true" className="h-4 border-l border-surface-border" />
            </>
          )}
          <IconButton label="Закрыть корзину" className="!size-6 !bg-transparent" onClick={onClose}>
            <X aria-hidden="true" className="size-6 text-ink-secondary" />
          </IconButton>
        </div>
      }
      footer={
        <div className="space-y-4">
          <div className="flex items-baseline gap-2 text-14 font-medium">
            <span className="text-ink-secondary">Стоимость товаров</span>
            <span
              aria-hidden="true"
              className="min-w-0 flex-1 border-b border-dashed border-surface-border"
            />
            <span className="whitespace-nowrap">
              {(savings.old_subtotal ?? totals.subtotal).toLocaleString('ru-RU')} ₽
            </span>
          </div>
          {!!savings.discount_total && (
            <div className="flex justify-between gap-4 text-14 text-brand-red">
              <span>Скидка</span>
              <span>−{savings.discount_total.toLocaleString('ru-RU')} ₽</span>
            </div>
          )}
          <div className="flex justify-between gap-4 text-20 font-semibold">
            <span>Итого</span>
            <span data-testid="cart-total">{totals.subtotal.toLocaleString('ru-RU')} ₽</span>
          </div>
          {unavailable && (
            <p id="cart-unavailable" className="text-14 text-brand-red">
              Удалите недоступные товары
            </p>
          )}
          <Button
            shape="rounded"
            size="lg"
            fullWidth
            className="shadow-card max-vsm:!h-11 max-vsm:!text-14"
            disabled={!items.length || loading || unavailable || clearing || confirmClear}
            aria-describedby={unavailable ? 'cart-unavailable' : undefined}
            onClick={() => goTo('/checkout')}
          >
            Перейти к оформлению
          </Button>
        </div>
      }
    >
      {(error || actionError) && (
        <div role="alert" className="mb-4 rounded-btn bg-brand-red/10 p-3 text-14 text-brand-red">
          {actionError || error}
          {error && (
            <button
              type="button"
              className="ml-2 underline"
              onClick={() =>
                void reloadCart().catch((problem) =>
                  showCartToast(getCartErrorMessage(problem), 'error'),
                )
              }
            >
              Повторить
            </button>
          )}
        </div>
      )}
      {confirmClear ? (
        <div
          className="rounded-card bg-surface-grey p-6"
          role="group"
          aria-label="Очистить корзину?"
        >
          <p className="text-20 font-semibold">Очистить корзину?</p>
          <p className="mt-2 text-14 text-ink-secondary">Все товары будут удалены из корзины.</p>
          <div className="mt-6 flex flex-wrap gap-3">
            <Button
              shape="rounded"
              disabled={clearing}
              onClick={() => setConfirmClear(false)}
              variant="outline"
            >
              Отмена
            </Button>
            <Button shape="rounded" isLoading={clearing} onClick={() => void confirm()}>
              Да, очистить
            </Button>
          </div>
        </div>
      ) : loading ? (
        <div role="status" aria-label="Загружаем корзину" className="space-y-4">
          {Array.from({ length: 4 }, (_, index) => (
            <div
              key={index}
              data-testid="cart-skeleton"
              aria-hidden="true"
              className="flex gap-4 border-b border-surface-border pb-4 motion-safe:animate-pulse"
            >
              <div className="size-[134px] shrink-0 rounded-lg bg-surface-grey max-vsm:size-[92px]" />
              <div className="flex flex-1 flex-col gap-3 py-1">
                <div className="h-5 w-4/5 rounded bg-surface-grey" />
                <div className="h-4 w-1/2 rounded bg-surface-grey" />
                <div className="mt-auto h-9 w-28 rounded-pill bg-surface-grey" />
              </div>
            </div>
          ))}
        </div>
      ) : !items.length && !error ? (
        <div className="flex min-h-[330px] flex-1 items-center justify-center py-4 max-vsm:min-h-0">
          <div className="flex w-full flex-col items-center gap-4 rounded-card bg-surface-grey px-6 py-16 text-center max-vsm:py-8">
            <span className="flex size-[60px] items-center justify-center rounded-full bg-surface">
              <ShoppingCart aria-hidden="true" className="size-[30px]" />
            </span>
            <h3 className="text-24 font-bold max-vsm:text-20">В корзине пока пусто</h3>
            <p className="text-14 text-ink-secondary">
              Добавляйте товары в корзину, чтобы оформить заказ.
            </p>
            <Button
              size="lg"
              shape="rounded"
              className="shadow-card"
              onClick={() => goTo('/catalog')}
            >
              Перейти в каталог
            </Button>
          </div>
        </div>
      ) : (
        <div className="space-y-4">
          {items.map((item) => (
            <CartPanelItem
              key={item.id}
              item={item}
              favorite={wishlistProductIds.includes(item.product_id)}
              onFavorite={() => void toggleWishlist(item.product_id)}
              onRemove={() => void changeLine(item, 0)}
              onQuantity={(quantity, max) => void changeLine(item, quantity, max)}
              onNavigate={goTo}
              disabled={clearing}
            />
          ))}
        </div>
      )}
    </Sheet>
  );
}
