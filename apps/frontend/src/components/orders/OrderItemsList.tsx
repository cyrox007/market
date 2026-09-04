import { OrderItem } from '../../lib/api';
import { Link } from 'react-router-dom';
import { ImageIcon } from 'lucide-react';

interface OrderItemsListProps {
  items: OrderItem[];
}

export default function OrderItemsList({ items }: OrderItemsListProps) {
  if (!items || items.length === 0) {
    return <p className="text-gray-600">Товары не найдены</p>;
  }

  return (
    <div className="space-y-4">
      {items.map((item) => {
        const productImage = item.product?.image || item.product?.thumbnail;
        const productName = item.product?.name || `Товар #${item.product_id}`;
        const productSlug = item.product?.slug;

        return (
          <div key={item.id} className="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
            {productImage ? (
              <img
                src={productImage}
                alt={productName}
                className="w-20 h-16 object-cover object-top rounded-lg"
              />
            ) : (
              <div className="w-20 h-16 flex items-center justify-center rounded-lg">
                <ImageIcon className="size-[1em] text-3xl text-gray-400" />
              </div>
            )}
            <div className="flex-1">
              {productSlug ? (
                <Link
                  to={`/product/${productSlug}`}
                  className="font-semibold mb-1 hover:text-red-600 transition-colors"
                >
                  {productName}
                </Link>
              ) : (
                <h4 className="font-semibold mb-1">{productName}</h4>
              )}
              <p className="text-sm text-gray-600">Количество: {item.quantity}</p>
            </div>
            <p className="font-bold text-red-600">{item.total.toLocaleString()} ₽</p>
          </div>
        );
      })}
    </div>
  );
}
