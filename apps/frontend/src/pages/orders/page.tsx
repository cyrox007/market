import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { api, type Order } from '../../lib/api';
import OrderStatusBadge from '../../components/orders/OrderStatusBadge';
import OrderTracking from '../../components/orders/OrderTracking';
import OrderItemsList from '../../components/orders/OrderItemsList';
import { formatDate, formatOrderStatus } from '../../utils/orderUtils';
import {
  buildPaymentUrl,
  closePaymentTab,
  isOnlineCardPaymentMethod,
  openPaymentInNewTab,
  preparePaymentTab,
  showPaymentTabError,
  type GatewayClientConfig,
} from '../../utils/paymentUtils';

import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import Icon from '../../components/ui/icons/Icon';
import { ChevronRight, ClipboardList, ImageIcon, TriangleAlert } from 'lucide-react';

const iconColorClasses: Record<string, string> = {
  green: 'text-green-600',
  blue: 'text-blue-600',
  yellow: 'text-yellow-600',
  red: 'text-red-600',
  gray: 'text-gray-600',
};

export default function Orders() {
  const navigate = useNavigate();
  const [orders, setOrders] = useState<Order[]>([]);
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [showCancelModal, setShowCancelModal] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isPaymentLoading, setIsPaymentLoading] = useState(false);
  const [paymentError, setPaymentError] = useState<string | null>(null);

  useEffect(() => {
    loadOrders();
  }, []);

  usePageSeo({
    title: buildTitle('Мои заказы'),
    description: 'История ваших заказов в интернет-магазине Светофор-Мебель.',
    image: '/logo.png',
    robots: 'noindex, follow',
    open_graph_title: buildTitle('Мои заказы'),
    locale: 'ru_RU',
  });

  const loadOrders = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await api.orders.list();
      setOrders(response.data || []);
      if (response.data && response.data.length > 0) {
        setSelectedOrder(response.data[0]);
      }
    } catch (err: any) {
      console.error('Failed to load orders:', err);
      setError('Не удалось загрузить заказы');
    } finally {
      setIsLoading(false);
    }
  };

  const handleCancelOrder = async () => {
    if (!selectedOrder) return;

    try {
      await api.orders.cancel(selectedOrder.id);
      setShowCancelModal(false);
      await loadOrders();
      if (orders.length > 0) {
        setSelectedOrder(orders[0]);
      } else {
        setSelectedOrder(null);
      }
    } catch (err: any) {
      console.error('Failed to cancel order:', err);
      const errorMessage = err?.data?.message || err?.message || 'Ошибка отмены заказа';
      alert(errorMessage);
    }
  };

  const canPaySelected =
    isOnlineCardPaymentMethod(selectedOrder?.payment_method) &&
    selectedOrder?.status === 'awaiting_payment';

  const handlePayOrder = async () => {
    if (!selectedOrder?.id) return;
    const paymentTab = preparePaymentTab();
    setIsPaymentLoading(true);
    setPaymentError(null);
    try {
      const config = await api.orders.getPaymentConfig(selectedOrder.id);
      const paymentUrl = buildPaymentUrl(
        config.gateway_client_config as GatewayClientConfig,
        config,
      );
      if (!openPaymentInNewTab(paymentUrl, paymentTab)) {
        const msg = 'Не удалось открыть вкладку оплаты. Разрешите всплывающие окна для сайта.';
        showPaymentTabError(paymentTab, msg);
        setPaymentError(msg);
      }
    } catch (err: any) {
      console.error('Failed to get payment config:', err);
      const msg = err?.data?.message || err?.message || 'Не удалось открыть оплату';
      showPaymentTabError(paymentTab, msg);
      closePaymentTab(paymentTab);
      setPaymentError(msg);
    } finally {
      setIsPaymentLoading(false);
    }
  };

  const handleRepeatOrder = async () => {
    if (!selectedOrder) return;

    try {
      const response = await api.orders.repeat(selectedOrder.id);
      const message = response?.message || 'Товары добавлены в корзину';

      // Показываем информативное сообщение
      if (response?.skipped_count && response.skipped_count > 0) {
        alert(
          `${message}\n\nОбратите внимание: некоторые товары не были добавлены, так как они недоступны.`,
        );
      } else {
        alert(message);
      }

      navigate('/cart');
    } catch (err: any) {
      console.error('Failed to repeat order:', err);
      const errorMessage = err?.data?.message || err?.message || 'Ошибка повторения заказа';
      alert(errorMessage);
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-6">
          <div className="text-center py-12">
            <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
            <p className="mt-4 text-gray-600">Загрузка заказов...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-6">
          <div className="bg-red-50 border border-red-200 rounded-2xl p-8 text-center">
            <p className="text-red-600">{error}</p>
            <button
              onClick={loadOrders}
              className="mt-4 bg-red-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors"
            >
              Попробовать снова
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-7xl mx-auto px-4 py-6">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-sm mb-2">
          <Link to="/" className="text-gray-600 hover:text-red-600">
            Главная
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <span className="text-gray-900">Мои заказы</span>
        </div>

        <h1 className="text-4xl font-bold mb-8">Мои заказы</h1>

        {orders.length === 0 ? (
          <div className="bg-white border border-gray-200 rounded-2xl p-20 text-center">
            <div className="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <ClipboardList className="size-[1em] text-6xl text-gray-400" />
            </div>
            <h3 className="text-xl font-bold mb-2">У вас пока нет заказов</h3>
            <p className="text-gray-600 mb-6">
              Начните делать покупки, чтобы увидеть свои заказы здесь
            </p>
            <Link
              to="/catalog"
              className="inline-block bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors"
            >
              Перейти в каталог
            </Link>
          </div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {/* Orders List */}
            <div className="lg:col-span-1 space-y-4">
              {orders.map((order) => {
                const statusConfig = formatOrderStatus(order.status);
                const firstItem = order.items?.[0];
                const productImage = firstItem?.product?.image || firstItem?.product?.thumbnail;
                const iconColor = iconColorClasses[statusConfig.color] || iconColorClasses.gray;

                return (
                  <div
                    key={order.id}
                    onClick={() => setSelectedOrder(order)}
                    className={`bg-white border-2 rounded-2xl p-6 cursor-pointer transition-all hover:shadow-lg ${
                      selectedOrder?.id === order.id ? 'border-red-600' : 'border-gray-200'
                    }`}
                  >
                    <div className="flex items-start justify-between mb-3">
                      <div>
                        <h3 className="font-bold text-lg mb-1">Заказ № {order.number}</h3>
                        <p className="text-sm text-gray-600">{formatDate(order.created_at)}</p>
                      </div>
                      <Icon
                        name={statusConfig.icon}
                        className={`size-[1em] text-2xl ${iconColor}`}
                      />
                    </div>
                    <div className="mb-3">
                      <OrderStatusBadge status={order.status} />
                    </div>
                    {firstItem && (
                      <div className="flex items-center gap-3 mb-3">
                        {productImage ? (
                          <img
                            src={productImage}
                            alt=""
                            className="w-16 h-12 object-cover object-top rounded-lg"
                          />
                        ) : (
                          <div className="w-16 h-12 flex items-center justify-center rounded-lg">
                            <ImageIcon className="size-[1em] text-3xl text-gray-400" />
                          </div>
                        )}
                        <div className="flex-1">
                          <p className="text-sm text-gray-600">
                            Товаров: {order.items?.length || 0}
                          </p>
                          <p className="text-lg font-bold text-red-600">
                            {order.total.toLocaleString()} ₽
                          </p>
                        </div>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>

            {/* Order Details */}
            <div className="lg:col-span-2">
              {selectedOrder ? (
                <div className="bg-white border border-gray-200 rounded-2xl p-8">
                  <div className="flex items-start justify-between mb-6 pb-6 border-b border-gray-200">
                    <div>
                      <h2 className="text-2xl font-bold mb-2">Заказ № {selectedOrder.number}</h2>
                      <p className="text-gray-600">{formatDate(selectedOrder.created_at)}</p>
                    </div>
                    <OrderStatusBadge status={selectedOrder.status} />
                  </div>

                  {/* Tracking */}
                  {selectedOrder.status_history && selectedOrder.status_history.length > 0 && (
                    <div className="mb-8">
                      <h3 className="text-xl font-bold mb-4">Отслеживание заказа</h3>
                      <OrderTracking
                        statusHistory={selectedOrder.status_history}
                        currentStatus={selectedOrder.status}
                      />
                    </div>
                  )}

                  {/* Items */}
                  {selectedOrder.items && selectedOrder.items.length > 0 && (
                    <div className="mb-8">
                      <h3 className="text-xl font-bold mb-4">Состав заказа</h3>
                      <OrderItemsList items={selectedOrder.items} />
                    </div>
                  )}

                  {/* Order Info */}
                  <div className="mb-8 space-y-4">
                    <div>
                      <h3 className="text-xl font-bold mb-4">Информация о заказе</h3>
                      <div className="bg-gray-50 rounded-lg p-4 space-y-2">
                        <div className="flex justify-between">
                          <span className="text-gray-600">Товары:</span>
                          <span className="font-medium">
                            {selectedOrder.subtotal.toLocaleString()} ₽
                          </span>
                        </div>
                        {selectedOrder.delivery_cost > 0 && (
                          <div className="flex justify-between">
                            <span className="text-gray-600">Доставка:</span>
                            <span className="font-medium">
                              {selectedOrder.delivery_cost.toLocaleString()} ₽
                            </span>
                          </div>
                        )}
                        {selectedOrder.assembly_cost > 0 && (
                          <div className="flex justify-between">
                            <span className="text-gray-600">Сборка:</span>
                            <span className="font-medium">
                              {selectedOrder.assembly_cost.toLocaleString()} ₽
                            </span>
                          </div>
                        )}
                        {selectedOrder.additional_services &&
                          selectedOrder.additional_services.length > 0 && (
                            <>
                              {selectedOrder.additional_services.map((service) => {
                                const servicePrice = service.price || 0;
                                if (servicePrice === 0 && service.price_type !== 'custom')
                                  return null;
                                return (
                                  <div key={service.id} className="flex justify-between">
                                    <span className="text-gray-600">{service.name}:</span>
                                    <span className="font-medium">
                                      {service.price_type === 'from'
                                        ? `от ${servicePrice.toLocaleString()} ₽`
                                        : service.price_type === 'custom'
                                          ? 'По договоренности'
                                          : `${servicePrice.toLocaleString()} ₽`}
                                    </span>
                                  </div>
                                );
                              })}
                            </>
                          )}
                        {selectedOrder.payment_method_label && (
                          <div className="flex justify-between">
                            <span className="text-gray-600">Способ оплаты:</span>
                            <span className="font-medium">
                              {selectedOrder.payment_method_label}
                            </span>
                          </div>
                        )}
                        {selectedOrder.delivery_type && (
                          <div className="flex justify-between">
                            <span className="text-gray-600">Тип доставки:</span>
                            <span className="font-medium">
                              {selectedOrder.delivery_type === 'delivery'
                                ? 'Доставка'
                                : 'Самовывоз'}
                            </span>
                          </div>
                        )}
                        {selectedOrder.address && (
                          <div>
                            <span className="text-gray-600">Адрес доставки:</span>
                            <p className="font-medium mt-1">
                              {selectedOrder.address.full_address ||
                                `${selectedOrder.address.city || ''}, ${selectedOrder.address.street || ''}, ${selectedOrder.address.house || ''}`.trim()}
                            </p>
                          </div>
                        )}
                        {selectedOrder.shipping_location && (
                          <div>
                            <span className="text-gray-600">Локация доставки:</span>
                            <p className="font-medium mt-1">
                              {selectedOrder.shipping_location.name}
                            </p>
                          </div>
                        )}
                        {selectedOrder.shipping_method && (
                          <div>
                            <span className="text-gray-600">Метод доставки:</span>
                            <p className="font-medium mt-1">
                              {selectedOrder.shipping_method.carrier?.name &&
                                `${selectedOrder.shipping_method.carrier.name} — `}
                              {selectedOrder.shipping_method.name}
                            </p>
                            {selectedOrder.shipping_method.delivery_days_min ||
                            selectedOrder.shipping_method.delivery_days_max ? (
                              <p className="text-sm text-gray-500 mt-1">
                                Срок:{' '}
                                {selectedOrder.shipping_method.delivery_days_min &&
                                selectedOrder.shipping_method.delivery_days_max
                                  ? `${selectedOrder.shipping_method.delivery_days_min}-${selectedOrder.shipping_method.delivery_days_max} дн.`
                                  : selectedOrder.shipping_method.delivery_days_min
                                    ? `от ${selectedOrder.shipping_method.delivery_days_min} дн.`
                                    : `до ${selectedOrder.shipping_method.delivery_days_max} дн.`}
                              </p>
                            ) : null}
                          </div>
                        )}
                        {selectedOrder.delivery_handling_type && (
                          <div>
                            <span className="text-gray-600">Тип обработки доставки:</span>
                            <p className="font-medium mt-1">
                              {selectedOrder.delivery_handling_type.name}
                              {selectedOrder.delivery_floor &&
                                ` (Этаж: ${selectedOrder.delivery_floor})`}
                            </p>
                          </div>
                        )}
                        {selectedOrder.requires_assembly && (
                          <div>
                            <span className="text-gray-600">Сборка:</span>
                            <p className="font-medium mt-1">Требуется</p>
                          </div>
                        )}
                        {selectedOrder.additional_services &&
                          selectedOrder.additional_services.length > 0 && (
                            <div>
                              <span className="text-gray-600">Дополнительные услуги:</span>
                              <div className="mt-2 space-y-1">
                                {selectedOrder.additional_services.map((service) => (
                                  <div
                                    key={service.id}
                                    className="flex items-center justify-between text-sm"
                                  >
                                    <div className="flex items-center gap-2">
                                      {service.icon && (
                                        <Icon
                                          name={service.icon}
                                          className="size-[1em] text-red-600"
                                        />
                                      )}
                                      <span>{service.name}</span>
                                    </div>
                                    <span className="font-medium">
                                      {service.price_type === 'from'
                                        ? `от ${(service.price || 0).toLocaleString()} ₽`
                                        : service.price_type === 'custom'
                                          ? 'По договоренности'
                                          : `${(service.price || 0).toLocaleString()} ₽`}
                                    </span>
                                  </div>
                                ))}
                              </div>
                            </div>
                          )}
                        {selectedOrder.comment && (
                          <div>
                            <span className="text-gray-600">Комментарий:</span>
                            <p className="font-medium mt-1">{selectedOrder.comment}</p>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Total */}
                  <div className="bg-gray-50 rounded-lg p-6 mb-6">
                    <div className="space-y-2 mb-4">
                      <div className="flex justify-between">
                        <span className="text-gray-600">Товары:</span>
                        <span className="font-medium">
                          {selectedOrder.subtotal.toLocaleString()} ₽
                        </span>
                      </div>
                      {selectedOrder.delivery_cost > 0 && (
                        <div className="flex justify-between">
                          <span className="text-gray-600">Доставка:</span>
                          <span className="font-medium">
                            {selectedOrder.delivery_cost.toLocaleString()} ₽
                          </span>
                        </div>
                      )}
                      {selectedOrder.assembly_cost > 0 && (
                        <div className="flex justify-between">
                          <span className="text-gray-600">Сборка:</span>
                          <span className="font-medium">
                            {selectedOrder.assembly_cost.toLocaleString()} ₽
                          </span>
                        </div>
                      )}
                      {selectedOrder.additional_services &&
                        selectedOrder.additional_services.length > 0 && (
                          <>
                            {selectedOrder.additional_services.map((service) => {
                              const servicePrice = service.price || 0;
                              if (servicePrice === 0 && service.price_type !== 'custom')
                                return null;
                              return (
                                <div key={service.id} className="flex justify-between">
                                  <span className="text-gray-600">{service.name}:</span>
                                  <span className="font-medium">
                                    {service.price_type === 'from'
                                      ? `от ${servicePrice.toLocaleString()} ₽`
                                      : service.price_type === 'custom'
                                        ? 'По договоренности'
                                        : `${servicePrice.toLocaleString()} ₽`}
                                  </span>
                                </div>
                              );
                            })}
                          </>
                        )}
                    </div>
                    <div className="flex items-center justify-between pt-4 border-t border-gray-300">
                      <span className="text-xl font-bold">Итого:</span>
                      <span className="text-2xl font-bold text-red-600">
                        {selectedOrder.total.toLocaleString()} ₽
                      </span>
                    </div>
                  </div>

                  {/* Actions: оплата, повтор, отмена — в один ряд с переносом */}
                  {paymentError && <p className="text-red-600 text-sm mb-2">{paymentError}</p>}
                  <div className="flex flex-wrap gap-4">
                    {canPaySelected && (
                      <button
                        type="button"
                        onClick={handlePayOrder}
                        disabled={isPaymentLoading}
                        className="px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap disabled:opacity-50"
                      >
                        {isPaymentLoading ? 'Открываем оплату...' : 'Оплатить заказ'}
                      </button>
                    )}
                    <button
                      onClick={handleRepeatOrder}
                      className="px-6 py-3 border border-gray-300 rounded-lg font-medium hover:border-red-600 transition-colors whitespace-nowrap"
                    >
                      Повторить заказ
                    </button>
                    {selectedOrder.can_be_cancelled && (
                      <button
                        onClick={() => setShowCancelModal(true)}
                        className="px-6 py-3 border border-red-600 text-red-600 rounded-lg font-medium hover:bg-red-50 transition-colors whitespace-nowrap"
                      >
                        Отменить заказ
                      </button>
                    )}
                  </div>
                </div>
              ) : (
                <div className="bg-white border border-gray-200 rounded-2xl p-20 text-center">
                  <div className="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <ClipboardList className="size-[1em] text-6xl text-gray-400" />
                  </div>
                  <h3 className="text-xl font-bold mb-2">Выберите заказ</h3>
                  <p className="text-gray-600">Нажмите на заказ слева, чтобы увидеть детали</p>
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Cancel Order Modal */}
      {showCancelModal && selectedOrder && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl p-8 max-w-md w-full">
            <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <TriangleAlert className="size-[1em] text-3xl text-red-600" />
            </div>
            <h3 className="text-2xl font-bold text-center mb-2">Отменить заказ?</h3>
            <p className="text-gray-600 text-center mb-6">
              Вы уверены, что хотите отменить заказ №{selectedOrder.number}? Это действие нельзя
              будет отменить.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowCancelModal(false)}
                className="flex-1 px-6 py-3 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors whitespace-nowrap"
              >
                Назад
              </button>
              <button
                onClick={handleCancelOrder}
                className="flex-1 px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap"
              >
                Отменить заказ
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
