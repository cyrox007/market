import { ArrowLeftRight, Heart } from 'lucide-react';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import { cn } from '../../../lib/cn';
import {
  productIdForCompare,
  productIdForWishlist,
  useWishlistAndCompare,
} from '../../../hooks/useWishlistAndCompare';
import { isVariableParent } from '../lib/variants';

interface Props {
  product: ProductDetail;
  selectedVariant: ProductVariant | null;
}

const ICON =
  'rounded-badge transition-[color,transform] duration-200 hover:text-brand-green active:scale-90 motion-reduce:transition-none';

/** «Сравнить» и «Избранное» у названия (Figma 669:43401) */
export default function WishlistCompareButtons({ product, selectedVariant }: Props) {
  const { wishlistProductIds, compareProductIds, toggleWishlist, toggleCompare } =
    useWishlistAndCompare();
  const isInWishlist = wishlistProductIds.includes(productIdForWishlist(product));
  const isInCompare = compareProductIds.includes(productIdForCompare(product));

  // У вариативного товара в избранное и сравнение кладём выбранный вариант
  const targetId = isVariableParent(product)
    ? (selectedVariant?.id ?? product.variants?.[0]?.id ?? product.id)
    : product.id;

  // Тексты — как у карточки каталога, они же подпись для читалки
  const compareLabel = isInCompare ? 'Удалить из сравнения' : 'Добавить в сравнение';
  const favoriteLabel = isInWishlist ? 'Удалить из избранного' : 'Добавить в избранное';

  return (
    <div className="flex shrink-0 items-center gap-4 py-0.5">
      <button
        type="button"
        aria-label={compareLabel}
        aria-pressed={isInCompare}
        title={compareLabel}
        onClick={async () => {
          try {
            await toggleCompare(targetId);
          } catch (error: any) {
            console.error('Failed to toggle compare:', error);
            if (error.status === 422) {
              alert(error.data?.message || 'Не удалось добавить товар в сравнение.');
            }
          }
        }}
        className={cn(ICON, isInCompare ? 'text-brand-green' : 'text-ink')}
      >
        <ArrowLeftRight className="size-6" />
      </button>
      <button
        type="button"
        aria-label={favoriteLabel}
        aria-pressed={isInWishlist}
        title={favoriteLabel}
        onClick={async () => {
          try {
            await toggleWishlist(targetId);
          } catch (error) {
            console.error('Failed to toggle wishlist:', error);
          }
        }}
        className={cn(ICON, isInWishlist ? 'text-brand-red' : 'text-ink')}
      >
        <Heart className="size-6" fill={isInWishlist ? 'currentColor' : 'none'} />
      </button>
    </div>
  );
}
