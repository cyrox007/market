import { Order } from '../lib/api';

export interface OrderStatusConfig {
  label: string;
  color: string;
  icon: string;
}

export function formatOrderStatus(status: string): OrderStatusConfig {
  const statusMap: Record<string, OrderStatusConfig> = {
    'new': {
      label: 'Новый',
      color: 'blue',
      icon: 'ri-file-add-line',
    },
    'awaiting_payment': {
      label: 'Ожидание оплаты',
      color: 'yellow',
      icon: 'ri-bank-card-line',
    },
    'accepted': {
      label: 'Принят',
      color: 'blue',
      icon: 'ri-checkbox-circle-line',
    },
    'assembled': {
      label: 'Собран',
      color: 'yellow',
      icon: 'ri-tools-line',
    },
    'shipped': {
      label: 'Отправлен',
      color: 'blue',
      icon: 'ri-ship-line',
    },
    'in_transit': {
      label: 'В пути',
      color: 'blue',
      icon: 'ri-truck-line',
    },
    'delivered': {
      label: 'Доставлен',
      color: 'green',
      icon: 'ri-checkbox-circle-line',
    },
    'cancelled': {
      label: 'Отменен',
      color: 'red',
      icon: 'ri-close-circle-line',
    },
  };

  return statusMap[status] || {
    label: status,
    color: 'gray',
    icon: 'ri-question-line',
  };
}

export function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('ru-RU', { 
    day: 'numeric', 
    month: 'long', 
    year: 'numeric' 
  });
}

export function formatDateTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('ru-RU', { 
    day: 'numeric', 
    month: 'long', 
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

export function formatRelativeTime(dateString: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 60) {
    return `${diffMins} минут назад`;
  } else if (diffHours < 24) {
    return `${diffHours} ${diffHours === 1 ? 'час' : diffHours < 5 ? 'часа' : 'часов'} назад`;
  } else {
    return `${diffDays} ${diffDays === 1 ? 'день' : diffDays < 5 ? 'дня' : 'дней'} назад`;
  }
}
