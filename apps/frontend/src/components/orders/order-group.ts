import type { CustomerOrderGroup, Order } from '../../lib/api';

/** Server presentation is authoritative. Fallback matches the legacy API contract. */
export function orderGroup(order: Pick<Order, 'status' | 'presentation'>): CustomerOrderGroup {
  return order.presentation?.group ?? (order.status === 'delivered' ? 'completed' : order.status === 'cancelled' ? 'cancelled' : 'active');
}
