import { Heart, ImageIcon, Trash2 } from 'lucide-react';
import type { MouseEvent } from 'react';
import type { CartItem } from '../../lib/api';
import { cn } from '../../lib/cn';
import { isUnconfiguredKit } from '../../utils/cartProduct';
import ProductLink from '../ui/ProductLink';
import IconButton from '../ui/primitives/IconButton';
import QuantityStepper from '../ui/composites/QuantityStepper';
import { cartItemAvailability } from './cartPanelModel';

interface Props {
  item: CartItem;
  favorite: boolean;
  onFavorite: () => void;
  onRemove: () => void;
  onQuantity: (quantity: number, max?: number) => void;
  onNavigate: (path: string) => void;
  disabled?: boolean;
}

export default function CartPanelItem({
  item,
  favorite,
  onFavorite,
  onRemove,
  onQuantity,
  onNavigate,
  disabled,
}: Props) {
  const availability = cartItemAvailability(item);
  const unavailable = availability.status === 'out';
  const attributes =
    item.variation_attributes?.map((a) => a.value_name || a.value_slug).filter(Boolean) ?? [];
  const variantText = attributes.length
    ? attributes.join(' · ')
    : [item.color?.name, item.size?.name].filter(Boolean).join(' · ');
  const to = item.slug
    ? `/product/${item.slug}${item.is_variant ? `?variant=${item.product_id}` : ''}`
    : null;
  const followProduct = (event: MouseEvent<HTMLAnchorElement>) => {
    if (!to || event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)
      return;
    event.preventDefault();
    onNavigate(to);
  };
  const image = item.image ? (
    <img src={item.image} alt="" className="size-full rounded-lg object-cover" />
  ) : (
    <ImageIcon aria-hidden="true" className="size-8 text-ink-secondary" />
  );
  return (
    <article
      aria-label={item.name}
      className="grid grid-cols-[134px_minmax(0,1fr)] gap-x-4 gap-y-3 border-b border-surface-stroke/[0.08] pb-4 last:border-b-0 max-vsm:grid-cols-[92px_minmax(0,1fr)] max-vsm:gap-x-3"
    >
      <div
        className={cn(
          'col-start-1 row-start-1 row-span-2 size-[134px] self-start overflow-hidden rounded-lg bg-surface-grey max-vsm:row-span-1 max-vsm:size-[92px]',
          unavailable && 'opacity-50',
        )}
      >
        {to ? (
          <ProductLink
            to={to}
            aria-label={`Фото: ${item.name}`}
            onClick={followProduct}
            className="flex size-full items-center justify-center"
          >
            {image}
          </ProductLink>
        ) : (
          <div className="flex size-full items-center justify-center">{image}</div>
        )}
      </div>
      <div className="col-start-2 row-start-1 flex min-w-0 flex-col gap-2 py-1">
        <div className="flex items-start justify-between gap-2">
          {to ? (
            <ProductLink
              to={to}
              onClick={followProduct}
              className={cn(
                'min-w-0 break-words text-16 font-semibold hover:text-brand-green max-vsm:text-14',
                unavailable && 'opacity-50',
              )}
            >
              {item.name}
            </ProductLink>
          ) : (
            <span className="min-w-0 break-words text-16 font-semibold opacity-50">
              {item.name}
            </span>
          )}
          <div className="flex shrink-0 gap-3 max-vsm:gap-1">
            <IconButton
              label={favorite ? 'Удалить из избранного' : 'Добавить в избранное'}
              className="!size-6 !rounded-none"
              disabled={disabled}
              onClick={onFavorite}
            >
              <Heart
                aria-hidden="true"
                className={cn('size-6 max-vsm:size-5', favorite && 'fill-brand-red text-brand-red')}
              />
            </IconButton>
            <IconButton
              label="Удалить товар"
              className="!size-6 !rounded-none"
              disabled={disabled}
              onClick={onRemove}
            >
              <Trash2 aria-hidden="true" className="size-6 max-vsm:size-5" />
            </IconButton>
          </div>
        </div>
        {variantText && (
          <div
            className={cn(
              'flex items-start gap-2 text-14 text-ink-secondary',
              unavailable && 'opacity-50',
            )}
          >
            {item.color?.code && (
              <span
                aria-hidden="true"
                className="mt-px size-[18px] shrink-0 rounded border border-surface-stroke/[0.08]"
                style={{ backgroundColor: item.color.code }}
              />
            )}
            <span className="break-words">{variantText}</span>
          </div>
        )}
        {!!item.bundle_components?.length && (
          <p className="break-words text-14 text-ink-secondary">
            Комплектация:{' '}
            {item.bundle_components
              .map((part) => `${part.name}${part.quantity > 1 ? ` × ${part.quantity}` : ''}`)
              .join(', ')}
          </p>
        )}
        {item.price_source === 'private_link' && (
          <p className="text-12 text-ink-secondary">по закрытой ссылке</p>
        )}
        {unavailable && (
          <p className="text-14 text-ink-secondary">
            {isUnconfiguredKit(item) ? 'Цена уточняется' : 'Нет в наличии'}
          </p>
        )}
      </div>
      <div className="col-start-2 row-start-2 self-end max-vsm:col-start-1">
        <QuantityStepper
          tone="cart"
          value={item.quantity}
          min={1}
          max={availability.maxQuantity}
          disabled={disabled || unavailable}
          onChange={(value) => onQuantity(value, availability.maxQuantity)}
        />
      </div>
      <p
        className={cn(
          'col-start-2 row-start-2 self-end justify-self-end whitespace-nowrap pb-1 text-16 font-semibold',
          unavailable && 'opacity-50',
        )}
      >
        {item.total.toLocaleString('ru-RU')} ₽
      </p>
    </article>
  );
}
