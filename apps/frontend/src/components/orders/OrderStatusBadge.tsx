import { formatOrderStatus } from '../../utils/orderUtils';

interface OrderStatusBadgeProps {
  status: string;
  className?: string;
  appearance?: 'default' | 'account';
  fallbackLabel?: string;
}

const colorClasses: Record<string, { bg: string; text: string }> = {
  green: { bg: 'bg-green-100', text: 'text-green-700' },
  blue: { bg: 'bg-blue-100', text: 'text-blue-700' },
  yellow: { bg: 'bg-yellow-100', text: 'text-yellow-700' },
  red: { bg: 'bg-red-100', text: 'text-red-700' },
  gray: { bg: 'bg-gray-100', text: 'text-gray-700' },
};

export default function OrderStatusBadge({ status, className = '', appearance = 'default', fallbackLabel }: OrderStatusBadgeProps) {
  const statusConfig = formatOrderStatus(status);
  if (appearance === 'account') {
    const accountColors: Record<string, string> = {
      new: 'bg-surface-grey text-ink',
      awaiting_payment: 'bg-brand-yellow text-ink',
      shipped: 'bg-brand-green/10 text-brand-green',
      in_transit: 'bg-brand-green/10 text-brand-green',
      delivered: 'bg-brand-green text-ink-inverse',
      cancelled: 'bg-brand-red/10 text-brand-red',
    };
    return <span className={`inline-flex max-w-full rounded-pill px-2.5 py-1 text-12 font-medium [overflow-wrap:anywhere] ${accountColors[status] || (statusConfig.color === 'gray' ? 'bg-surface-grey text-ink-secondary' : 'bg-brand-green/10 text-brand-green')} ${className}`}>
      {fallbackLabel || (statusConfig.color === 'gray' ? 'Статус уточняется' : statusConfig.label)}
    </span>;
  }
  const colors = colorClasses[statusConfig.color] || colorClasses.gray;

  return (
    <span
      className={`inline-block px-3 py-1 rounded-full text-sm font-medium ${colors.bg} ${colors.text} ${className}`}
    >
      {statusConfig.label}
    </span>
  );
}
