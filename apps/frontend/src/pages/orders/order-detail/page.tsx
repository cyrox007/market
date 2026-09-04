import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate, Link, useSearchParams } from 'react-router-dom';
import { api, type Order } from '../../../lib/api';
import OrderStatusBadge from '../../../components/orders/OrderStatusBadge';
import OrderTracking from '../../../components/orders/OrderTracking';
import OrderItemsList from '../../../components/orders/OrderItemsList';
import { formatDate } from '../../../utils/orderUtils';
import { useAuth } from '../../../hooks/useAuth';
import {
  buildPaymentUrl,
  closePaymentTab,
  isOnlineCardPaymentMethod,
  openPaymentInNewTab,
  preparePaymentTab,
  showPaymentTabError,
  type GatewayClientConfig,
} from '../../../utils/paymentUtils';
import Icon from '../../../components/ui/icons/Icon';
import { ChevronRight, Info, TriangleAlert } from 'lucide-react';

export default function OrderDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { user, updateProfile } = useAuth();
  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showCancelModal, setShowCancelModal] = useState(false);
  const [showSyncModal, setShowSyncModal] = useState(false);
  const [syncData, setSyncData] = useState<{
    profileNeedsUpdate: boolean;
    addressData: any;
    shippingLocationId: number | null;
  } | null>(null);
  const [isUpdating, setIsUpdating] = useState(false);
  const [isPaymentLoading, setIsPaymentLoading] = useState(false);
  const [paymentError, setPaymentError] = useState<string | null>(null);
  const [secondsLeft, setSecondsLeft] = useState<number | null>(null);
  const openedPaymentRef = useRef(false);

  useEffect(() => {
    if (id) {
      loadOrder(parseInt(id));
    }
  }, [id]);

  useEffect(() => {
    if (!order || order.status !== 'awaiting_payment' || !order.payment_deadline_at) {
      setSecondsLeft(null);
      return;
    }

    const computeSecondsLeft = (): number => {
      const deadline = new Date(order.payment_deadline_at as string);
      const now = new Date();
      const diffMs = deadline.getTime() - now.getTime();
      return Math.max(0, Math.floor(diffMs / 1000));
    };

    setSecondsLeft(computeSecondsLeft());

    const countdownTimer = window.setInterval(() => {
      setSecondsLeft(computeSecondsLeft());
    }, 1000);

    return () => window.clearInterval(countdownTimer);
  }, [order?.status, order?.id, order?.payment_deadline_at]);

  useEffect(() => {
    if (!order || order.status !== 'awaiting_payment') return;

    // Редкий heartbeat, чтобы не дёргать API слишком часто.
    const pollTimer = window.setInterval(() => {
      loadOrder(order.id);
    }, 30000);

    return () => window.clearInterval(pollTimer);
  }, [order?.status, order?.id]);

  useEffect(() => {
    if (!order || order.status !== 'awaiting_payment' || !order.payment_deadline_at) return;

    // Точечное обновление в момент дедлайна (с небольшим буфером),
    // чтобы быстро увидеть переход в "Отменён" без частого polling.
    const deadlineMs = new Date(order.payment_deadline_at).getTime();
    const nowMs = Date.now();
    const delayMs = Math.max(0, deadlineMs - nowMs + 2000);

    const timeoutId = window.setTimeout(() => {
      loadOrder(order.id);
    }, delayMs);

    return () => window.clearTimeout(timeoutId);
  }, [order?.status, order?.id, order?.payment_deadline_at]);

  // Проверяем данные синхронизации из sessionStorage
  useEffect(() => {
    const syncDataStr = sessionStorage.getItem('checkout_sync_data');
    if (syncDataStr && order && user) {
      try {
        const data = JSON.parse(syncDataStr);
        // Проверяем, что это данные для текущего заказа
        if (data.orderId === order.id) {
          setSyncData({
            profileNeedsUpdate: data.profileNeedsUpdate,
            addressData: data.addressData,
            shippingLocationId: data.shippingLocationId,
          });
          setShowSyncModal(true);
          // Удаляем данные из sessionStorage
          sessionStorage.removeItem('checkout_sync_data');
        }
      } catch (e) {
        console.error('Failed to parse sync data:', e);
      }
    }
  }, [order, user]);

  const loadOrder = async (orderId: number) => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await api.orders.get(orderId);
      setOrder(response.order);
    } catch (err: any) {
      console.error('Failed to load order:', err);
      setError('Не удалось загрузить заказ');
    } finally {
      setIsLoading(false);
    }
  };

  /** Открыть страницу оплаты (эквайринг Райффайзен) для заказа с оплатой картой онлайн */
  const openPaymentPage = useCallback(async () => {
    if (!order?.id) return;
    const paymentTab = preparePaymentTab();
    setIsPaymentLoading(true);
    setPaymentError(null);
    try {
      const config = await api.orders.getPaymentConfig(order.id);
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
  }, [order?.id]);

  const canPayOnline =
    isOnlineCardPaymentMethod(order?.payment_method) && order?.status === 'awaiting_payment';

  const formatCountdown = (seconds: number | null) => {
    if (seconds === null) return '—';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${String(secs).padStart(2, '0')}`;
  };

  const shouldShowCountdown =
    order?.status === 'awaiting_payment' && secondsLeft !== null && secondsLeft > 0;

  // Авто-открытие оплаты после редиректа с чекаута (openPayment=1)
  useEffect(() => {
    if (!order || searchParams.get('openPayment') !== '1' || openedPaymentRef.current) return;
    if (!isOnlineCardPaymentMethod(order.payment_method) || order.status !== 'awaiting_payment') {
      setSearchParams((prev) => {
        const next = new URLSearchParams(prev);
        next.delete('openPayment');
        return next;
      });
      return;
    }
    openedPaymentRef.current = true;
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      next.delete('openPayment');
      return next;
    });
    openPaymentPage();
  }, [order, searchParams, setSearchParams, openPaymentPage]);

  const handleCancelOrder = async () => {
    if (!order) return;

    try {
      await api.orders.cancel(order.id);
      setShowCancelModal(false);
      await loadOrder(order.id);
    } catch (err: any) {
      console.error('Failed to cancel order:', err);
      const errorMessage = err?.data?.message || err?.message || 'Ошибка отмены заказа';
      alert(errorMessage);
    }
  };

  const handleRepeatOrder = async () => {
    if (!order) return;

    try {
      const response = await api.orders.repeat(order.id);
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

  const handleUpdateProfile = async () => {
    if (!order || !user) return;

    setIsUpdating(true);
    try {
      // Обновляем профиль данными из заказа
      await updateProfile({
        name: order.contact_name,
        phone: order.contact_phone,
      });

      // Если нужно сохранить адрес
      if (syncData?.addressData && syncData.shippingLocationId) {
        try {
          await api.addresses.create({
            title: 'Адрес доставки',
            city: syncData.addressData.city,
            street: syncData.addressData.street,
            house: syncData.addressData.house,
            apartment: syncData.addressData.apartment || undefined,
            entrance: syncData.addressData.entrance || undefined,
            shipping_location_id: syncData.shippingLocationId,
          });
        } catch (err) {
          console.error('Failed to save address:', err);
          // Не показываем ошибку пользователю, так как профиль уже обновлен
        }
      }

      setShowSyncModal(false);
      setSyncData(null);
    } catch (err: any) {
      console.error('Failed to update profile:', err);
      alert('Ошибка обновления профиля');
    } finally {
      setIsUpdating(false);
    }
  };

  const handleSkipSync = () => {
    setShowSyncModal(false);
    setSyncData(null);
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-6">
          <div className="text-center py-12">
            <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
            <p className="mt-4 text-gray-600">Загрузка заказа...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error || !order) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-6">
          <div className="bg-red-50 border border-red-200 rounded-2xl p-8 text-center">
            <p className="text-red-600">{error || 'Заказ не найден'}</p>
            <div className="mt-4 flex gap-3 justify-center">
              <button
                onClick={() => navigate('/orders')}
                className="bg-red-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors"
              >
                Вернуться к заказам
              </button>
              {error && (
                <button
                  onClick={() => id && loadOrder(parseInt(id))}
                  className="border border-gray-300 px-6 py-3 rounded-lg font-medium hover:bg-gray-50 transition-colors"
                >
                  Попробовать снова
                </button>
              )}
            </div>
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
          <Link to="/orders" className="text-gray-600 hover:text-red-600">
            Мои заказы
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <span className="text-gray-900">Заказ № {order.number}</span>
        </div>

        <div className="bg-white border border-gray-200 rounded-2xl p-8">
          <div className="flex items-start justify-between mb-6 pb-6 border-b border-gray-200">
            <div>
              <h1 className="text-3xl font-bold mb-2">Заказ № {order.number}</h1>
              <p className="text-gray-600">Дата заказа: {formatDate(order.created_at)}</p>
            </div>
            <OrderStatusBadge status={order.status} />
          </div>

          {order.status === 'awaiting_payment' && (
            <div className="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
              {shouldShowCountdown ? (
                <p className="font-medium">
                  Ожидаем оплату. До автоматической отмены: {formatCountdown(secondsLeft)}
                </p>
              ) : (
                <p className="font-medium">
                  Ожидаем оплату. Оплата не поступила вовремя — сейчас идёт автоматическая отмена и
                  возврат остатков.
                </p>
              )}
              <p className="text-sm mt-1">
                Если оплата не будет подтверждена в течение 10 минут, заказ будет переведён в
                «Отменён», а товары вернутся на склад.
              </p>
            </div>
          )}

          {order.status === 'cancelled' && order.cancelled_due_to_unpaid_timeout && (
            <div className="mb-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800">
              Заказ аннулирован автоматически из-за неоплаты. Остатки товаров возвращены на склад.
            </div>
          )}

          {/* Tracking */}
          {order.status_history && order.status_history.length > 0 && (
            <div className="mb-8">
              <h2 className="text-2xl font-bold mb-4">Отслеживание заказа</h2>
              <OrderTracking statusHistory={order.status_history} currentStatus={order.status} />
            </div>
          )}

          {/* Items */}
          {order.items && order.items.length > 0 && (
            <div className="mb-8">
              <h2 className="text-2xl font-bold mb-4">Состав заказа</h2>
              <OrderItemsList items={order.items} />
            </div>
          )}

          {/* Order Info */}
          <div className="mb-8">
            <h2 className="text-2xl font-bold mb-4">Информация о заказе</h2>
            <div className="bg-gray-50 rounded-lg p-6 space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <span className="text-gray-600 block mb-1">Контактное лицо:</span>
                  <p className="font-medium">{order.contact_name}</p>
                </div>
                <div>
                  <span className="text-gray-600 block mb-1">Телефон:</span>
                  <p className="font-medium">{order.contact_phone}</p>
                </div>
                <div>
                  <span className="text-gray-600 block mb-1">Email:</span>
                  <p className="font-medium">{order.contact_email}</p>
                </div>
                {order.payment_method_label && (
                  <div>
                    <span className="text-gray-600 block mb-1">Способ оплаты:</span>
                    <p className="font-medium">{order.payment_method_label}</p>
                  </div>
                )}
                {order.delivery_type && (
                  <div>
                    <span className="text-gray-600 block mb-1">Тип доставки:</span>
                    <p className="font-medium">
                      {order.delivery_type === 'delivery' ? 'Доставка' : 'Самовывоз'}
                    </p>
                  </div>
                )}
                {order.delivery_date && (
                  <div>
                    <span className="text-gray-600 block mb-1">Дата доставки:</span>
                    <p className="font-medium">{formatDate(order.delivery_date)}</p>
                  </div>
                )}
                {order.delivery_time && (
                  <div>
                    <span className="text-gray-600 block mb-1">Время доставки:</span>
                    <p className="font-medium">{order.delivery_time}</p>
                  </div>
                )}
              </div>

              {order.address && (
                <div>
                  <span className="text-gray-600 block mb-1">Адрес доставки:</span>
                  <p className="font-medium">
                    {order.address.full_address ||
                      `${order.address.city || ''}, ${order.address.street || ''}, ${order.address.house || ''}`.trim()}
                    {order.address.apartment && `, кв. ${order.address.apartment}`}
                    {order.address.entrance && `, подъезд ${order.address.entrance}`}
                  </p>
                </div>
              )}

              {order.shipping_location && (
                <div>
                  <span className="text-gray-600 block mb-1">Локация доставки:</span>
                  <p className="font-medium">{order.shipping_location.name}</p>
                </div>
              )}

              {order.shipping_method && (
                <div>
                  <span className="text-gray-600 block mb-1">Метод доставки:</span>
                  <p className="font-medium">
                    {order.shipping_method.carrier?.name &&
                      `${order.shipping_method.carrier.name} — `}
                    {order.shipping_method.name}
                  </p>
                </div>
              )}

              {order.delivery_handling_type && (
                <div>
                  <span className="text-gray-600 block mb-1">Тип обработки доставки:</span>
                  <p className="font-medium">{order.delivery_handling_type.name}</p>
                  {order.delivery_floor && (
                    <p className="text-sm text-gray-500 mt-1">Этаж: {order.delivery_floor}</p>
                  )}
                </div>
              )}

              {order.shipping_method && (
                <>
                  {order.shipping_method.delivery_days_min ||
                  order.shipping_method.delivery_days_max ? (
                    <div>
                      <span className="text-gray-600 block mb-1">Срок доставки:</span>
                      <p className="font-medium">
                        {order.shipping_method.delivery_days_min &&
                        order.shipping_method.delivery_days_max
                          ? `${order.shipping_method.delivery_days_min}-${order.shipping_method.delivery_days_max} дн.`
                          : order.shipping_method.delivery_days_min
                            ? `от ${order.shipping_method.delivery_days_min} дн.`
                            : `до ${order.shipping_method.delivery_days_max} дн.`}
                      </p>
                    </div>
                  ) : null}
                  {order.shipping_method.free_delivery_threshold && (
                    <div>
                      <span className="text-gray-600 block mb-1">Порог бесплатной доставки:</span>
                      <p className="font-medium">
                        от {order.shipping_method.free_delivery_threshold.toLocaleString()} ₽
                      </p>
                    </div>
                  )}
                </>
              )}

              {order.requires_assembly && (
                <div>
                  <span className="text-gray-600 block mb-1">Сборка:</span>
                  <p className="font-medium">Требуется</p>
                </div>
              )}

              {order.additional_services && order.additional_services.length > 0 && (
                <div>
                  <span className="text-gray-600 block mb-2">Дополнительные услуги:</span>
                  <div className="space-y-2">
                    {order.additional_services.map((service) => (
                      <div
                        key={service.id}
                        className="flex items-center justify-between bg-white rounded-lg p-3 border border-gray-200"
                      >
                        <div className="flex items-center gap-3">
                          {service.icon && (
                            <Icon name={service.icon} className="size-[1em] text-xl text-red-600" />
                          )}
                          <span className="font-medium">{service.name}</span>
                        </div>
                        <span className="font-medium text-gray-700">
                          {service.price_type === 'from'
                            ? `от ${service.price?.toLocaleString() || 0} ₽`
                            : service.price_type === 'custom'
                              ? 'По договоренности'
                              : `${service.price?.toLocaleString() || 0} ₽`}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {order.comment && (
                <div>
                  <span className="text-gray-600 block mb-1">Комментарий:</span>
                  <p className="font-medium">{order.comment}</p>
                </div>
              )}
            </div>
          </div>

          {/* Total */}
          <div className="mb-8">
            <h2 className="text-2xl font-bold mb-4">Стоимость заказа</h2>
            <div className="bg-gray-50 rounded-lg p-6 space-y-2">
              <div className="flex justify-between">
                <span className="text-gray-600">Товары:</span>
                <span className="font-medium">{order.subtotal.toLocaleString()} ₽</span>
              </div>
              {order.delivery_cost > 0 && (
                <div className="flex justify-between">
                  <span className="text-gray-600">Доставка:</span>
                  <span className="font-medium">{order.delivery_cost.toLocaleString()} ₽</span>
                </div>
              )}
              {order.assembly_cost > 0 && (
                <div className="flex justify-between">
                  <span className="text-gray-600">Сборка:</span>
                  <span className="font-medium">{order.assembly_cost.toLocaleString()} ₽</span>
                </div>
              )}
              {order.additional_services && order.additional_services.length > 0 && (
                <>
                  {order.additional_services.map((service) => {
                    const servicePrice = service.price || 0;
                    if (servicePrice === 0 && service.price_type !== 'custom') return null;
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
              <div className="flex justify-between pt-4 border-t border-gray-300">
                <span className="text-xl font-bold">Итого:</span>
                <span className="text-2xl font-bold text-red-600">
                  {order.total.toLocaleString()} ₽
                </span>
              </div>
            </div>
          </div>

          {/* Actions: оплата, повтор, отмена — в один ряд, переносятся при адаптивности */}
          {paymentError && <p className="text-red-600 text-sm mb-2">{paymentError}</p>}
          <div className="flex flex-wrap gap-4">
            {canPayOnline && (
              <button
                type="button"
                onClick={openPaymentPage}
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
            {order.can_be_cancelled && (
              <button
                onClick={() => setShowCancelModal(true)}
                className="px-6 py-3 border border-red-600 text-red-600 rounded-lg font-medium hover:bg-red-50 transition-colors whitespace-nowrap"
              >
                Отменить заказ
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Sync Profile Modal */}
      {showSyncModal && syncData && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl p-8 max-w-md w-full">
            <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <Info className="size-[1em] text-3xl text-blue-600" />
            </div>
            <h3 className="text-2xl font-bold text-center mb-2">Обновить профиль?</h3>
            <p className="text-gray-600 text-center mb-6">
              {syncData.profileNeedsUpdate && syncData.addressData
                ? 'Данные в заказе отличаются от данных в вашем профиле. Хотите обновить профиль и сохранить адрес доставки?'
                : syncData.profileNeedsUpdate
                  ? 'Данные в заказе отличаются от данных в вашем профиле. Хотите обновить профиль?'
                  : 'Хотите сохранить адрес доставки в ваш профиль?'}
            </p>
            {syncData.profileNeedsUpdate && (
              <div className="bg-gray-50 rounded-lg p-4 mb-4 text-sm">
                <p className="font-medium mb-2">Изменения:</p>
                {user?.name !== order.contact_name && (
                  <p className="text-gray-600">
                    Имя: {user?.name} → {order.contact_name}
                  </p>
                )}
                {user?.phone !== order.contact_phone && (
                  <p className="text-gray-600">
                    Телефон: {user?.phone || 'не указан'} → {order.contact_phone}
                  </p>
                )}
              </div>
            )}
            <div className="flex gap-3">
              <button
                onClick={handleSkipSync}
                disabled={isUpdating}
                className="flex-1 px-6 py-3 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors whitespace-nowrap disabled:opacity-50"
              >
                Пропустить
              </button>
              <button
                onClick={handleUpdateProfile}
                disabled={isUpdating}
                className="flex-1 px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap disabled:opacity-50"
              >
                {isUpdating ? 'Обновление...' : 'Обновить'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Cancel Order Modal */}
      {showCancelModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl p-8 max-w-md w-full">
            <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <TriangleAlert className="size-[1em] text-3xl text-red-600" />
            </div>
            <h3 className="text-2xl font-bold text-center mb-2">Отменить заказ?</h3>
            <p className="text-gray-600 text-center mb-6">
              Вы уверены, что хотите отменить заказ №{order.number}? Это действие нельзя будет
              отменить.
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
