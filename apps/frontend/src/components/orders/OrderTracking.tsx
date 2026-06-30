import { OrderStatusHistory } from '../../lib/api';
import { formatDateTime } from '../../utils/orderUtils';

interface OrderTrackingProps {
  statusHistory: OrderStatusHistory[];
  currentStatus: string;
}

export default function OrderTracking({ statusHistory, currentStatus }: OrderTrackingProps) {
  if (!statusHistory || statusHistory.length === 0) {
    return null;
  }

  // Сортируем историю по дате (от новых к старым)
  const sortedHistory = [...statusHistory].sort((a, b) => 
    new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
  );

  return (
    <div className="space-y-4">
      {sortedHistory.map((item, idx) => {
        const isActive = idx === 0; // Первый элемент - текущий статус
        return (
          <div key={item.id} className="flex items-start gap-4">
            <div className={`w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 ${
              isActive ? 'bg-red-600' : 'bg-gray-200'
            }`}>
              <i className={`ri-checkbox-circle-line text-xl ${
                isActive ? 'text-white' : 'text-gray-400'
              }`}></i>
            </div>
            <div className="flex-1">
              <p className={`font-medium ${isActive ? 'text-gray-900' : 'text-gray-600'}`}>
                {item.status_label}
              </p>
              {item.comment && (
                <p className="text-sm text-gray-500 mt-1">{item.comment}</p>
              )}
              <p className="text-sm text-gray-500">{formatDateTime(item.created_at)}</p>
            </div>
          </div>
        );
      })}
    </div>
  );
}
