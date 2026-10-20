import { ArrowLeftRight, Heart } from 'lucide-react';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import {
  productIdForCompare,
  productIdForWishlist,
  useWishlistAndCompare,
} from '../../../hooks/useWishlistAndCompare';

interface Props {
  product: ProductDetail;
  matchedVariant: ProductVariant | null;
}

export default function WishlistCompareButtons({ product, matchedVariant }: Props) {
  const { wishlistProductIds, compareProductIds, toggleWishlist, toggleCompare } =
    useWishlistAndCompare();
  const isInWishlist = wishlistProductIds.includes(productIdForWishlist(product));
  const isInCompare = compareProductIds.includes(productIdForCompare(product));

  // У вариативного товара в избранное и сравнение кладём выбранный вариант
  const targetId =
    product.is_variable && !product.is_variant
      ? (matchedVariant?.id ?? product.variants?.[0]?.id ?? product.id)
      : product.id;

  return (
    <div className="flex items-center gap-2 mt-2">
      <button
        onClick={async () => {
          try {
            await toggleWishlist(targetId);
          } catch (error) {
            console.error('Failed to toggle wishlist:', error);
          }
        }}
        className={`flex-1 flex items-center justify-center gap-2 border-2 rounded-lg py-2.5 cursor-pointer transition-colors ${
          isInWishlist
            ? 'border-red-600 bg-red-50'
            : 'border-gray-300 hover:border-red-600 hover:bg-red-50'
        }`}
      >
        <Heart
          className="size-[1em] text-xl text-red-600"
          fill={isInWishlist ? 'currentColor' : 'none'}
        />
        <span className="text-sm font-medium">{isInWishlist ? 'В избранном' : 'В избранное'}</span>
      </button>
      <button
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
        className={`flex-1 flex items-center justify-center gap-2 border-2 rounded-lg py-2.5 cursor-pointer transition-colors ${
          isInCompare
            ? 'border-red-600 bg-red-50'
            : 'border-gray-300 hover:border-red-600 hover:bg-red-50'
        }`}
      >
        <ArrowLeftRight
          className={`size-[1em] text-xl ${isInCompare ? 'text-red-600' : 'text-gray-700'}`}
        />
        <span className="text-sm font-medium">{isInCompare ? 'В сравнении' : 'Сравнить'}</span>
      </button>
    </div>
  );
}
