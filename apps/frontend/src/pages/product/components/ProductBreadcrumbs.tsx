import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import type { ProductDetail } from '../../../lib/api';

export default function ProductBreadcrumbs({ product }: { product: ProductDetail }) {
  return (
    <div className="flex items-center gap-2 text-xs sm:text-sm mb-6">
      <Link to="/" className="text-gray-600 hover:text-red-600">
        Главная
      </Link>
      <ChevronRight className="size-[1em] text-gray-400" />
      <Link to="/catalog" className="text-gray-600 hover:text-red-600">
        Каталог
      </Link>
      {product.category && (
        <>
          <ChevronRight className="size-[1em] text-gray-400" />
          <Link
            to={`/catalog/${product.category.slug}`}
            className="text-gray-600 hover:text-red-600"
          >
            {product.category.name}
          </Link>
        </>
      )}
      <ChevronRight className="size-[1em] text-gray-400" />
      <span className="text-gray-900">{product.name}</span>
    </div>
  );
}
