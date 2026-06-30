import { useState, useEffect } from 'react';
import type { Product, ProductDetail } from '../../lib/api';
import { getProductStockStatus, formatStockDisplayText } from '../../utils/productUtils';
import { api } from '../../lib/api';

interface ProductStockBadgeProps {
  product: Product | ProductDetail;
  className?: string;
  showStockCount?: boolean;
}

/**
 * Компонент для отображения статуса наличия товара
 */
export default function ProductStockBadge({ 
  product, 
  className = '',
  showStockCount = true 
}: ProductStockBadgeProps) {
  const [stockSettings, setStockSettings] = useState<{
    stock_low_max: number;
    stock_medium_max: number;
    stock_high_max: number;
    show_exact_above: number;
  } | null>(null);

  // Загружаем настройки остатков
  useEffect(() => {
    api.stockSettings.get()
      .then((data) => {
        setStockSettings(data.stock_settings);
      })
      .catch((err) => {
        console.error('Failed to load stock settings:', err);
        // Используем значения по умолчанию
        setStockSettings({
          stock_low_max: 1,
          stock_medium_max: 5,
          stock_high_max: 10,
          show_exact_above: 0,
        });
      });
  }, []);

  const stockStatus = getProductStockStatus(product);
  
  if (!stockStatus.available && stockStatus.status === 'out_of_stock') {
    return (
      <span className={`px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 ${className}`}>
        Нет в наличии
      </span>
    );
  }
  
  if (stockStatus.status === 'backorder') {
    return (
      <span className={`px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 ${className}`}>
        Под заказ
      </span>
    );
  }
  
  if (stockStatus.status === 'in_stock') {
    const stockText = formatStockDisplayText(stockStatus.stock, stockSettings || undefined);

    return (
      <span className={`px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 ${className}`}>
        {showStockCount && stockStatus.stock > 0 
          ? `В наличии (${stockText})` 
          : 'В наличии'}
      </span>
    );
  }
  
  // Fallback
  return (
    <span className={`px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 ${className}`}>
      Проверить наличие
    </span>
  );
}
