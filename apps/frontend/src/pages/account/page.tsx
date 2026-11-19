import { useState, useEffect, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { useCounters } from '../../hooks/useCounters';
import {
  api,
  type BonusTransaction,
  type WishlistItem,
  type NotificationSettings,
} from '../../lib/api';
import { runSessionRead, runSessionTask } from '../../lib/session-queue';

import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import Icon from '../../components/ui/icons/Icon';
import { canOrder } from '../../lib/availability';
import { useAccountSection, type AccountSection } from './useAccountSection';
import { useAccountData } from './useAccountData';
import { AccountDataError } from './AccountDataError';
import AccountProfileCard from './AccountProfileCard';
import AccountEmailSettings from './AccountEmailSettings';
import AccountNavigation from './AccountNavigation';
import AccountLastOrder from './AccountLastOrder';
import AccountProfileForm from './AccountProfileForm';
import AccountAddresses from './AccountAddresses';
import addressChevron from './assets/address-chevron.svg';
import {
  Bell,
  Calendar,
  ChevronRight,
  CircleCheck,
  ClipboardList,
  Gift,
  Heart,
  ImageIcon,
  Lock,
  LogOut,
  MapPin,
  Save,
  Settings,
  ShoppingBag,
  Star,
  TriangleAlert,
  User,
  UserPlus,
} from 'lucide-react';

const menuItems = [
  { id: 'profile', label: 'Профиль', icon: User },
  { id: 'orders', label: 'Заказы', icon: ShoppingBag },
  { id: 'favorites', label: 'Избранное', icon: Heart },
  { id: 'addresses', label: 'Адреса доставки', icon: MapPin },
  { id: 'bonuses', label: 'Бонусы', icon: Gift },
  { id: 'settings', label: 'Настройки', icon: Settings },
];

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
}

function getStatusColor(status: string): string {
  const statusMap: Record<string, string> = {
    Доставлен: 'green',
    'В пути': 'blue',
    Отправлен: 'blue',
    Собран: 'yellow',
    Принят: 'blue',
    Новый: 'blue',
    Обрабатывается: 'blue',
    Отменен: 'red',
  };
  return statusMap[status] || 'gray';
}

export default function Account() {
  const navigate = useNavigate();
  const { user, logout, updateProfile } = useAuth();
  const { refreshWishlistCount, wishlistCount, compareCount, isLoading: isLoadingCounters, error: countersError, hasLoaded: hasCounters, refreshAllCounts } = useCounters();
  const { section: activeSection, selectSection: setActiveSection } = useAccountSection();
  const headingRef = useRef<HTMLHeadingElement>(null);
  const authenticatedUserId = user?.id;
  useEffect(() => {
    if (activeSection === 'settings' && authenticatedUserId) headingRef.current?.focus();
  }, [activeSection, authenticatedUserId]);
  const {
    orders, hasOrders, isLoadingOrders, ordersError, reloadOrders,
    addresses, hasAddresses, isLoadingAddresses, addressesError, reloadAddresses: loadAddresses,
    notificationSettings, isLoadingNotifications, notificationsError, reloadNotifications,
    saveNotifications,
  } = useAccountData(user?.id, activeSection);

  // Состояния для данных
  const [favorites, setFavorites] = useState<WishlistItem[]>([]);
  const [bonusBalance, setBonusBalance] = useState(0);
  const [bonusHistory, setBonusHistory] = useState<BonusTransaction[]>([]);

  // Состояния для загрузки
  const [isLoadingFavorites, setIsLoadingFavorites] = useState(false);
  const [isLoadingBonuses, setIsLoadingBonuses] = useState(false);

  const [isLoggingOut, setIsLoggingOut] = useState(false);

  // Состояния для настроек уведомлений
  const [isSavingNotifications, setIsSavingNotifications] = useState(false);
  const [notificationError, setNotificationError] = useState<string | null>(null);
  const [notificationSuccess, setNotificationSuccess] = useState(false);

  // Состояния для смены пароля
  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [isChangingPassword, setIsChangingPassword] = useState(false);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [passwordSuccess, setPasswordSuccess] = useState(false);

  usePageSeo({
    title: buildTitle('Личный кабинет'),
    description: 'Личный кабинет покупателя в интернет-магазине Светофор-Мебель.',
    image: '/logo.png',
    robots: 'noindex, follow',
    open_graph_title: buildTitle('Личный кабинет'),
    locale: 'ru_RU',
  });

  // Загрузка данных при смене секции
  useEffect(() => {
    if (activeSection === 'favorites' && favorites.length === 0) {
      loadFavorites();
    } else if (activeSection === 'bonuses') {
      loadBonuses();
    }
  }, [activeSection]);

  const loadFavorites = async () => {
    setIsLoadingFavorites(true);
    try {
      const response = await runSessionRead(() => api.wishlist.list());
      setFavorites(response.data || []);
    } catch (error) {
      console.error('Failed to load favorites:', error);
    } finally {
      setIsLoadingFavorites(false);
    }
  };

  const loadBonuses = async () => {
    setIsLoadingBonuses(true);
    try {
      const [balanceResponse, historyResponse] = await Promise.all([
        api.bonuses.balance(),
        api.bonuses.history(),
      ]);
      setBonusBalance(balanceResponse.balance || 0);
      setBonusHistory(historyResponse.data || []);
    } catch (error) {
      console.error('Failed to load bonuses:', error);
    } finally {
      setIsLoadingBonuses(false);
    }
  };

  const handleNotificationChange = async (key: keyof NotificationSettings, value: boolean) => {
    if (!notificationSettings || isSavingNotifications) return;
    setNotificationError(null);
    setNotificationSuccess(false);

    setIsSavingNotifications(true);
    try {
      await saveNotifications({ [key]: value });
      setNotificationSuccess(true);
      setTimeout(() => setNotificationSuccess(false), 3000);
    } catch (error: any) {
      const errorMessage = error?.data?.message || error?.message || 'Ошибка обновления настроек';
      setNotificationError(errorMessage);
    } finally {
      setIsSavingNotifications(false);
    }
  };

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setPasswordError(null);
    setPasswordSuccess(false);

    // Валидация
    if (!passwordForm.current_password.trim()) {
      setPasswordError('Введите текущий пароль');
      return;
    }

    if (!passwordForm.password.trim()) {
      setPasswordError('Введите новый пароль');
      return;
    }

    if (passwordForm.password.length < 8) {
      setPasswordError('Пароль должен содержать минимум 8 символов');
      return;
    }

    if (passwordForm.password !== passwordForm.password_confirmation) {
      setPasswordError('Пароли не совпадают');
      return;
    }

    setIsChangingPassword(true);

    try {
      await runSessionTask(() => api.auth.changePassword({
        current_password: passwordForm.current_password,
        password: passwordForm.password,
        password_confirmation: passwordForm.password_confirmation,
      }));
      setPasswordSuccess(true);
      setPasswordForm({
        current_password: '',
        password: '',
        password_confirmation: '',
      });
      setTimeout(() => setPasswordSuccess(false), 3000);
    } catch (error: any) {
      const errorMessage = error?.data?.message || error?.message || 'Ошибка смены пароля';
      setPasswordError(errorMessage);
    } finally {
      setIsChangingPassword(false);
    }
  };

  const handleLogout = async () => {
    if (isLoggingOut) return;
    setIsLoggingOut(true);
    let didNavigate = false;
    try {
      await runSessionTask(() => logout());
      navigate('/login');
      didNavigate = true;
    } catch (error) {
      console.error('Failed to logout:', error);
      navigate('/login');
      didNavigate = true;
    } finally {
      if (!didNavigate) setIsLoggingOut(false);
    }
  };

  const handleRepeatOrder = async (orderId: number) => {
    try {
      const response = (await api.orders.repeat(orderId)) as {
        message: string;
        added_items: Array<{
          product_id: number;
          product_name: string;
          quantity: number;
          was_adjusted: boolean;
        }>;
        skipped_items: Array<{
          product_id: number;
          product_name?: string;
          requested_quantity: number;
          reason: string;
        }>;
        errors: Array<{ product_id: number; product_name: string; error: string }>;
        added_count: number;
        skipped_count: number;
      };
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
    } catch (error: any) {
      console.error('Failed to repeat order:', error);
      const errorMessage = error?.data?.message || error?.message || 'Ошибка повторения заказа';
      alert(errorMessage);
    }
  };

  const handleRemoveFavorite = async (productId: number) => {
    try {
      await runSessionTask(() => api.wishlist.remove(productId));
      setFavorites(favorites.filter((item) => item.product_id !== productId));
      // Небольшая задержка, чтобы дать время API обновиться
      await new Promise((resolve) => setTimeout(resolve, 100));
      await refreshWishlistCount();
    } catch (error) {
      console.error('Failed to remove favorite:', error);
    }
  };

  if (!user) {
    return null;
  }

  const userInitial = user.name.charAt(0).toUpperCase();
  const fullWidth = activeSection === 'profile' || activeSection === 'addresses';

  return (
    <div className="min-h-screen bg-white">
      <div className={fullWidth
        ? 'mx-auto w-full max-w-[1440px] px-[100px] pb-16 pt-6 max-md:px-6 max-vsm:px-4'
        : 'max-w-7xl mx-auto px-4 py-6'}>
        {/* Breadcrumbs */}
        <nav aria-label="Хлебные крошки" className={fullWidth ? 'mb-6 flex items-center gap-2 text-14 text-ink' : 'flex items-center gap-2 text-sm mb-2'}>
          <Link to="/" className={fullWidth ? 'hover:text-brand-green' : 'text-gray-600 hover:text-red-600'}>
            Главная
          </Link>
          {activeSection === 'addresses' ? <img src={addressChevron} alt="" width={16} height={16} /> : <ChevronRight className="size-[1em] text-gray-400" />}
          {activeSection === 'addresses' ? <><button type="button" onClick={()=>setActiveSection('profile')} className="hover:text-brand-green">Профиль</button><img src={addressChevron} alt="" width={16} height={16} /><span className="text-ink/50" aria-current="page">Адреса доставки</span></> : activeSection === 'profile' ? <>
            <Link to="/account" className="hover:text-brand-green max-md:hidden">Личный кабинет</Link>
            <ChevronRight className="size-4 max-md:hidden" aria-hidden="true" />
            <span className="text-ink/50" aria-current="page">Профиль</span>
          </> : <span className="text-gray-900">Личный кабинет</span>}
        </nav>

        {activeSection !== 'addresses' && <h1 ref={headingRef} tabIndex={-1} className={activeSection === 'profile' ? 'mb-6 font-display text-36 font-bold tracking-[-0.02em] text-ink max-vsm:text-28' : 'text-4xl font-bold mb-8'}>
          <span className={activeSection === 'profile' ? 'max-md:hidden' : undefined}>{activeSection === 'settings' ? 'Настройки профиля' : 'Личный кабинет'}</span>
          {activeSection === 'profile' && <span className="md:hidden">Профиль</span>}
        </h1>}

        {activeSection === 'orders' && ordersError && (
          <AccountDataError label="Не удалось загрузить заказы" onRetry={reloadOrders} />
        )}
        {activeSection === 'profile' && addressesError && (
          <AccountDataError label="Не удалось загрузить адреса" onRetry={loadAddresses} />
        )}
        {activeSection === 'settings' && notificationsError && (
          <AccountDataError label="Не удалось загрузить настройки уведомлений" onRetry={reloadNotifications} />
        )}
        {activeSection === 'profile' && countersError && (
          <AccountDataError label="Не удалось загрузить счетчики избранного и сравнения" onRetry={refreshAllCounts} />
        )}

        {activeSection === 'profile' && (
          <div className="mb-6">
            <AccountProfileCard user={user}
              newsletter={{ enabled: notificationSettings?.email_promotions, isLoading: isLoadingNotifications, error: notificationsError, onRetry: reloadNotifications }}
              onEdit={() => setActiveSection('settings')} />
          </div>
        )}

        {activeSection === 'profile' && <>
          <AccountNavigation
            orders={{ items: orders, hasData: hasOrders, isLoading: isLoadingOrders, error: ordersError }}
            addresses={{ count: addresses.length, hasData: hasAddresses, isLoading: isLoadingAddresses, error: addressesError }}
            counters={{ wishlistCount, compareCount, hasData: hasCounters, isLoading: isLoadingCounters, error: countersError }}
            />
          <AccountLastOrder orders={orders} isLoading={isLoadingOrders} error={ordersError} onRetry={reloadOrders} />
        </>}

        <div className={fullWidth ? 'space-y-6' : 'grid grid-cols-1 lg:grid-cols-4 gap-8'}>
          {/* Sidebar */}
          {!fullWidth && <div className="lg:col-span-1">
            <div className="bg-white border border-gray-200 rounded-2xl p-6 sticky top-4">
              {/* User Info */}
              <div className="text-center mb-6 pb-6 border-b border-gray-200">
                <div className="w-20 h-20 bg-gradient-to-r from-red-600 to-yellow-500 rounded-full flex items-center justify-center mx-auto mb-3">
                  <span className="text-3xl font-bold text-white">{userInitial}</span>
                </div>
                <h3 className="font-bold text-lg">{user.name.toUpperCase()}</h3>
                <p className="text-sm text-gray-600">{user.email}</p>
              </div>
                {orders.length > 0 && (
                  <div className="mb-4 pt-4 border-t border-gray-200">
                    <div className="text-xs text-gray-500 mb-1">Последний заказ</div>
                    <Link
                      to={`/orders/${orders[0].id}`}
                      className="text-sm text-red-600 hover:text-red-700 font-medium"
                    >
                      Заказ {orders[0].number || `#${orders[0].id}`} →
                    </Link>
                  </div>
                )}
              {/* Menu */}
              <nav className="space-y-2">
                {menuItems.map((item) => (
                  <button
                    key={item.id}
                    onClick={() => setActiveSection(item.id as AccountSection)}
                    className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg font-medium transition-colors cursor-pointer whitespace-nowrap ${
                      activeSection === item.id
                        ? 'bg-red-50 text-red-600'
                        : 'text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    <Icon name={item.icon} className="size-[1em] text-xl" />
                    {item.label}
                  </button>
                ))}
                <button
                  type="button"
                  onClick={handleLogout}
                  disabled={isLoggingOut}
                  className="w-full flex items-center gap-3 px-4 py-3 rounded-lg font-medium text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer whitespace-nowrap disabled:opacity-60 disabled:cursor-not-allowed"
                >
                  {isLoggingOut ? (
                    <>
                      <div className="w-5 h-5 border-2 border-gray-300 border-t-red-600 rounded-full animate-spin" />
                      Выход...
                    </>
                  ) : (
                    <>
                      <LogOut className="size-[1em] text-xl" />
                      Выйти
                    </>
                  )}
                </button>
              </nav>
            </div>
          </div>}

          {/* Content */}
          <div className={fullWidth ? 'min-w-0' : 'lg:col-span-3'}>
            {/* Orders */}
            {activeSection === 'orders' && (
              <div>
                <div className="flex items-center justify-between mb-6">
                  <div className="flex items-center gap-3">
                    <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                      <ShoppingBag className="size-[1em] text-2xl text-red-600" />
                    </div>
                    <div>
                      <h2 className="text-2xl font-bold">Мои заказы</h2>
                      {orders.length > 0 && (
                        <p className="text-sm text-gray-500 mt-1">
                          Всего заказов: {orders.length} • Сумма покупок:{' '}
                          {orders.reduce((sum, order) => sum + order.total, 0).toLocaleString()} ₽
                        </p>
                      )}
                    </div>
                  </div>
                </div>
                {isLoadingOrders ? (
                  <div className="text-center py-12">
                    <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
                    <p className="mt-4 text-gray-600">Загрузка заказов...</p>
                  </div>
                ) : ordersError && orders.length === 0 ? null : orders.length === 0 ? (
                  <div className="bg-white border border-gray-200 rounded-2xl p-8 text-center">
                    <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <ShoppingBag className="size-[1em] text-4xl text-gray-400" />
                    </div>
                    <p className="text-gray-600 text-lg mb-2">У вас пока нет заказов</p>
                    <Link
                      to="/catalog"
                      className="inline-block text-red-600 hover:text-red-700 font-medium"
                    >
                      Перейти в каталог →
                    </Link>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {orders.map((order) => {
                      const statusColor = getStatusColor(order.status_label || order.status);
                      const firstItem = order.items?.[0];
                      const productImage =
                        firstItem?.product?.image || firstItem?.product?.thumbnail;
                      return (
                        <div
                          key={order.id}
                          className="bg-white border border-gray-200 rounded-2xl p-6 hover:shadow-md transition-shadow"
                        >
                          <div className="flex items-start justify-between mb-4">
                            <div className="flex-1">
                              <div className="flex items-center gap-3 mb-2">
                                <ClipboardList className="size-[1em] text-xl text-red-600" />
                                <h3 className="font-bold text-lg">
                                  Заказ {order.number || `#${order.id}`}
                                </h3>
                              </div>
                              <p className="text-sm text-gray-600 flex items-center gap-2">
                                <Calendar className="size-[1em]" />
                                {formatDate(order.created_at)}
                              </p>
                            </div>
                            <span
                              className={`px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap bg-${statusColor}-100 text-${statusColor}-700`}
                            >
                              {order.status_label || order.status}
                            </span>
                          </div>
                          {firstItem && (
                            <div className="flex items-center gap-4 mb-4">
                              {productImage ? (
                                <img
                                  src={productImage}
                                  alt={firstItem.product?.name || 'Товар'}
                                  className="w-20 h-16 object-cover object-top rounded-lg"
                                />
                              ) : (
                                <div className="w-20 h-16 flex items-center justify-center rounded-lg">
                                  <ImageIcon className="size-[1em] text-3xl text-gray-400" />
                                </div>
                              )}
                              <div className="flex-1">
                                <p className="text-gray-700">Товаров: {order.items?.length || 0}</p>
                                <p className="text-2xl font-bold text-red-600">
                                  {order.total.toLocaleString()} ₽
                                </p>
                              </div>
                            </div>
                          )}
                          <div className="flex gap-3">
                            <Link
                              to={`/orders/${order.id}`}
                              className="flex-1 bg-red-600 text-white py-3 rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap text-center"
                            >
                              Подробнее
                            </Link>
                            <button
                              onClick={() => handleRepeatOrder(order.id)}
                              className="px-6 py-3 border border-gray-300 rounded-lg font-medium hover:border-red-600 transition-colors whitespace-nowrap"
                            >
                              Повторить заказ
                            </button>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            )}

            {/* Favorites */}
            {activeSection === 'favorites' && (
              <div>
                <h2 className="text-2xl font-bold mb-6">Избранное</h2>
                {isLoadingFavorites ? (
                  <div className="text-center py-12">
                    <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
                    <p className="mt-4 text-gray-600">Загрузка избранного...</p>
                  </div>
                ) : favorites.length === 0 ? (
                  <div className="bg-white border border-gray-200 rounded-2xl p-8 text-center">
                    <p className="text-gray-600">У вас пока нет избранных товаров</p>
                  </div>
                ) : (
                  <div
                    className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
                    data-product-shop
                  >
                    {favorites.map((item) => {
                      const product = item.product;
                      return (
                        <div
                          key={item.id}
                          className="bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-lg transition-shadow"
                        >
                          <div className="relative h-48">
                            {product.image ? (
                              <img
                                src={product.image}
                                alt={product.name}
                                className="w-full h-full object-cover object-top"
                              />
                            ) : (
                              <div className="w-full h-full flex items-center justify-center">
                                <ImageIcon className="size-[1em] text-3xl text-gray-400" />
                              </div>
                            )}
                            <button
                              onClick={() => handleRemoveFavorite(product.id)}
                              className="absolute top-4 right-4 w-10 h-10 bg-red-600 rounded-full flex items-center justify-center shadow-md hover:bg-red-700 cursor-pointer"
                            >
                              <Heart
                                className="size-[1em] text-xl text-white"
                                fill="currentColor"
                              />
                            </button>
                            {!canOrder(product) && (
                              <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                                <span className="bg-white px-4 py-2 rounded-lg font-medium">
                                  Нет в наличии
                                </span>
                              </div>
                            )}
                          </div>
                          <div className="p-4">
                            <h3 className="font-semibold mb-3">{product.name}</h3>
                            <div className="flex items-center gap-2 mb-4">
                              <span className="text-xl font-bold text-red-600">
                                {product.price.toLocaleString()} ₽
                              </span>
                              {product.old_price && (
                                <span className="text-sm text-gray-400 line-through">
                                  {product.old_price.toLocaleString()} ₽
                                </span>
                              )}
                            </div>
                            <div className="flex gap-2">
                              <Link
                                to={`/product/${product.slug}`}
                                className="flex-1 bg-red-600 text-white py-3 rounded-lg font-medium hover:bg-red-700 transition-colors disabled:bg-gray-300 whitespace-nowrap text-center"
                              >
                                {canOrder(product) ? 'В корзину' : 'Недоступно'}
                              </Link>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            )}

            {activeSection === 'addresses' && <AccountAddresses addresses={addresses} isLoading={isLoadingAddresses} error={addressesError} onReload={loadAddresses} />}

            {/* Bonuses */}
            {activeSection === 'bonuses' && (
              <div>
                <h2 className="text-2xl font-bold mb-6">Бонусная программа</h2>
                {isLoadingBonuses ? (
                  <div className="text-center py-12">
                    <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
                    <p className="mt-4 text-gray-600">Загрузка бонусов...</p>
                  </div>
                ) : (
                  <>
                    <div className="bg-gradient-to-r from-red-600 to-yellow-500 text-white rounded-2xl p-8 mb-8">
                      <div className="flex items-center justify-between mb-6">
                        <div>
                          <p className="text-lg opacity-90 mb-2">Ваш баланс</p>
                          <p className="text-5xl font-bold">{bonusBalance.toLocaleString()} ₽</p>
                        </div>
                        <div className="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center">
                          <Gift className="size-[1em] text-4xl" />
                        </div>
                      </div>
                      <p className="opacity-90">1 бонус = 1 рубль при оплате заказа</p>
                    </div>

                    <div className="bg-white border border-gray-200 rounded-2xl p-8 mb-8">
                      <h3 className="text-xl font-bold mb-4">Как получить бонусы</h3>
                      <div className="space-y-4">
                        <div className="flex items-start gap-4">
                          <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <ShoppingBag className="size-[1em] text-red-600 text-xl" />
                          </div>
                          <div>
                            <h4 className="font-bold mb-1">За покупки</h4>
                            <p className="text-gray-600">Получайте 5% от суммы каждого заказа</p>
                          </div>
                        </div>
                        <div className="flex items-start gap-4">
                          <div className="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <Star className="size-[1em] text-yellow-600 text-xl" />
                          </div>
                          <div>
                            <h4 className="font-bold mb-1">За отзывы</h4>
                            <p className="text-gray-600">100 бонусов за каждый отзыв с фото</p>
                          </div>
                        </div>
                        <div className="flex items-start gap-4">
                          <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <UserPlus className="size-[1em] text-green-600 text-xl" />
                          </div>
                          <div>
                            <h4 className="font-bold mb-1">За друзей</h4>
                            <p className="text-gray-600">
                              500 бонусов за каждого приведенного друга
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div className="bg-white border border-gray-200 rounded-2xl p-8">
                      <h3 className="text-xl font-bold mb-4">История начислений</h3>
                      {bonusHistory.length === 0 ? (
                        <p className="text-gray-600">История пуста</p>
                      ) : (
                        <div className="space-y-4">
                          {bonusHistory.map((item) => {
                            const isPositive = item.amount > 0;
                            const color = isPositive ? 'green' : 'red';
                            const sign = isPositive ? '+' : '';
                            return (
                              <div
                                key={item.id}
                                className="flex items-center justify-between py-3 border-b border-gray-200 last:border-0"
                              >
                                <div>
                                  <p className="font-medium">{item.description}</p>
                                  <p className="text-sm text-gray-500">
                                    {formatDate(item.created_at)}
                                  </p>
                                </div>
                                <span className={`font-bold text-${color}-600`}>
                                  {sign}
                                  {item.amount.toLocaleString()} ₽
                                </span>
                              </div>
                            );
                          })}
                        </div>
                      )}
                    </div>
                  </>
                )}
              </div>
            )}

            {/* Settings */}
            {activeSection === 'settings' && (
              <div className="space-y-6">
                <AccountProfileForm key={user.id} user={user} onSave={(data) => runSessionTask(() => updateProfile(data))} />
                <AccountEmailSettings userId={user.id} email={user.email} />
                <div className="bg-white border border-gray-200 rounded-2xl p-8">
                  <div className="flex items-center gap-3 mb-6">
                    <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                      <Bell className="size-[1em] text-2xl text-red-600" />
                    </div>
                    <h2 className="text-2xl font-bold">Настройки уведомлений</h2>
                  </div>

                  {isLoadingNotifications ? (
                    <div className="text-center py-8">
                      <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-red-600"></div>
                      <p className="mt-4 text-gray-600 text-sm">Загрузка настроек...</p>
                    </div>
                  ) : !notificationSettings ? null : (
                    <>
                      {notificationError && (
                        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                          <TriangleAlert className="size-[1em] text-xl text-red-600 mt-0.5" />
                          <p className="text-red-600 text-sm flex-1">{notificationError}</p>
                        </div>
                      )}

                      {notificationSuccess && (
                        <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg flex items-start gap-3">
                          <CircleCheck className="size-[1em] text-xl text-green-600 mt-0.5" />
                          <p className="text-green-600 text-sm flex-1">
                            Настройки уведомлений обновлены
                          </p>
                        </div>
                      )}

                      <div className="space-y-4">
                        {[
                          {
                            key: 'email_promotions' as keyof NotificationSettings,
                            label: 'Акции и скидки на email',
                          },
                          {
                            key: 'email_order_notifications' as keyof NotificationSettings,
                            label: 'Статус заказа на email',
                          },
                        ].map((item) => (
                          <label
                            key={item.key}
                            className="flex items-center justify-between p-4 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors"
                          >
                            <span className="font-medium">{item.label}
                              {item.key === 'email_promotions' && <span className="block text-12 font-normal text-ink-secondary">Не чаще одного раза в неделю</span>}
                            </span>
                            <div className="relative">
                              <input
                                type="checkbox"
                                checked={notificationSettings[item.key] ?? false}
                                onChange={(e) =>
                                  handleNotificationChange(item.key, e.target.checked)
                                }
                                disabled={isSavingNotifications}
                                className="w-5 h-5 text-red-600 rounded focus:ring-red-500 focus:ring-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                              />
                              {isSavingNotifications && (
                                <div className="absolute inset-0 flex items-center justify-center">
                                  <div className="w-4 h-4 border-2 border-red-600 border-t-transparent rounded-full animate-spin"></div>
                                </div>
                              )}
                            </div>
                          </label>
                        ))}
                      </div>
                    </>
                  )}
                </div>

                <div className="bg-white border border-gray-200 rounded-2xl p-8">
                  <div className="flex items-center gap-3 mb-6">
                    <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                      <Lock className="size-[1em] text-2xl text-red-600" />
                    </div>
                    <h2 className="text-2xl font-bold">Изменить пароль</h2>
                  </div>

                  {passwordError && (
                    <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                      <TriangleAlert className="size-[1em] text-xl text-red-600 mt-0.5" />
                      <p className="text-red-600 text-sm flex-1">{passwordError}</p>
                    </div>
                  )}

                  {passwordSuccess && (
                    <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg flex items-start gap-3">
                      <CircleCheck className="size-[1em] text-xl text-green-600 mt-0.5" />
                      <p className="text-green-600 text-sm flex-1">Пароль успешно изменен</p>
                    </div>
                  )}

                  <form onSubmit={handlePasswordSubmit} className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium mb-2">Текущий пароль</label>
                      <input
                        type="password"
                        value={passwordForm.current_password}
                        onChange={(e) =>
                          setPasswordForm({ ...passwordForm, current_password: e.target.value })
                        }
                        required
                        disabled={isChangingPassword}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100 disabled:bg-gray-50 disabled:cursor-not-allowed"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2">Новый пароль</label>
                      <input
                        type="password"
                        value={passwordForm.password}
                        onChange={(e) =>
                          setPasswordForm({ ...passwordForm, password: e.target.value })
                        }
                        required
                        minLength={8}
                        disabled={isChangingPassword}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100 disabled:bg-gray-50 disabled:cursor-not-allowed"
                      />
                      <p className="text-xs text-gray-500 mt-1">Минимум 8 символов</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2">Подтвердите пароль</label>
                      <input
                        type="password"
                        value={passwordForm.password_confirmation}
                        onChange={(e) =>
                          setPasswordForm({
                            ...passwordForm,
                            password_confirmation: e.target.value,
                          })
                        }
                        required
                        disabled={isChangingPassword}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100 disabled:bg-gray-50 disabled:cursor-not-allowed"
                      />
                    </div>
                    <button
                      type="submit"
                      disabled={isChangingPassword}
                      className="bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                      {isChangingPassword ? (
                        <>
                          <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                          <span>Изменение...</span>
                        </>
                      ) : (
                        <>
                          <Save className="size-[1em]" />
                          <span>Изменить пароль</span>
                        </>
                      )}
                    </button>
                  </form>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

    </div>
  );
}
