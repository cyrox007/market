import { formatOrderStatus } from '../../utils/orderUtils';

interface OrderStatusBadgeProps {
  status: string;
  className?: string;
}

const colorClasses: Record<string, { bg: string; text: string }> = {
  green: { bg: 'bg-green-100', text: 'text-green-700' },
  blue: { bg: 'bg-blue-100', text: 'text-blue-700' },
  yellow: { bg: 'bg-yellow-100', text: 'text-yellow-700' },
  red: { bg: 'bg-red-100', text: 'text-red-700' },
  gray: { bg: 'bg-gray-100', text: 'text-gray-700' },
};

export default function OrderStatusBadge({ status, className = '' }: OrderStatusBadgeProps) {
  const statusConfig = formatOrderStatus(status);
  const colors = colorClasses[statusConfig.color] || colorClasses.gray;

  return (
    <span className={`inline-block px-3 py-1 rounded-full text-sm font-medium ${colors.bg} ${colors.text} ${className}`}>
      {statusConfig.label}
    </span>
  );
}
