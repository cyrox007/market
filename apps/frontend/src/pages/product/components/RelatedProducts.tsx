import ProductCardConnected from '../../../components/ui/ProductCardConnected';
import type { Product } from '../../../lib/api';

export default function RelatedProducts({ products }: { products: Product[] }) {
  if (products.length === 0) return null;
  return (
    <div>
      <h2 className="text-2xl font-bold mb-6">Сопутствующие товары</h2>
      <div
        className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5"
        data-product-shop
      >
        {products.map((item, index) => (
          <ProductCardConnected key={item.id} product={item} priority={index < 4} />
        ))}
      </div>
    </div>
  );
}
