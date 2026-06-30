import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { useCounters } from '../../hooks/useCounters';
import { api, type Order, type Address, type BonusTransaction, type WishlistItem, type Product, type ShippingLocation, type NotificationSettings } from '../../lib/api';
import PhoneInput from '../../components/ui/PhoneInput';

const menuItems = [
  { id: 'profile', label: 'Профиль', icon: 'ri-user-line' },
  { id: 'orders', label: 'Заказы', icon: 'ri-shopping-bag-line' },
  { id: 'favorites', label: 'Избранное', icon: 'ri-heart-line' },
  { id: 'addresses', label: 'Адреса доставки', icon: 'ri-map-pin-line' },
  { id: 'bonuses', label: 'Бонусы', icon: 'ri-gift-line' },
  { id: 'settings', label: 'Настройки', icon: 'ri-settings-line' }
];

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
}

function getStatusColor(status: string): string {
  const statusMap: Record<string, string> = {
    'Доставлен': 'green',
    'В пути': 'blue',
    'Отправлен': 'blue',
    'Собран': 'yellow',
    'Принят': 'blue',
    'Новый': 'blue',
    'Обрабатывается': 'blue',
    'Отменен': 'red',
  };
  return statusMap[status] || 'gray';
}

export default function Account() {
  const navigate = useNavigate();
  const { user, logout, updateProfile } = useAuth();
  const { refreshWishlistCount } = useCounters();
  const [activeSection, setActiveSection] = useState('profile');
  
  // Состояния для данных
  const [orders, setOrders] = useState<Order[]>([]);
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [favorites, setFavorites] = useState<WishlistItem[]>([]);
  const [bonusBalance, setBonusBalance] = useState(0);
  const [bonusHistory, setBonusHistory] = useState<BonusTransaction[]>([]);
  
  // Состояния для загрузки
  const [isLoadingOrders, setIsLoadingOrders] = useState(false);
  const [isLoadingAddresses, setIsLoadingAddresses] = useState(false);
  const [isLoadingFavorites, setIsLoadingFavorites] = useState(false);
  const [isLoadingBonuses, setIsLoadingBonuses] = useState(false);
  
  // Состояния для форм
  const [profileForm, setProfileForm] = useState({ name: '', phone: '' });
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  const [profileError, setProfileError] = useState<string | null>(null);
  const [profileSuccess, setProfileSuccess] = useState(false);
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

  // Состояния для адресов
  const [showAddressModal, setShowAddressModal] = useState(false);
  const [editingAddress, setEditingAddress] = useState<Address | null>(null);
  const [addressForm, setAddressForm] = useState({
    title: '',
    city: '',
    street: '',
    house: '',
    apartment: '',
    entrance: '',
    shipping_location_id: null as number | null,
  });
  const [shippingLocations, setShippingLocations] = useState<ShippingLocation[]>([]);
  const [isSavingAddress, setIsSavingAddress] = useState(false);
  const [addressError, setAddressError] = useState<string | null>(null);
  const [addressToDelete, setAddressToDelete] = useState<Address | null>(null);
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  // Состояния для настроек уведомлений
  const [notificationSettings, setNotificationSettings] = useState<NotificationSettings>({
    email_promotions: true,
    sms_order_notifications: true,
    push_notifications: false,
    new_product_notifications: true,
  });
  const [isLoadingNotifications, setIsLoadingNotifications] = useState(false);
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

  // Загрузка данных при смене секции
  useEffect(() => {
    if (activeSection === 'orders' && orders.length === 0) {
      loadOrders();
    } else if (activeSection === 'addresses') {
      if (addresses.length === 0) {
        loadAddresses();
      }
      if (shippingLocations.length === 0) {
        loadShippingLocations();
      }
    } else if (activeSection === 'favorites' && favorites.length === 0) {
      loadFavorites();
    } else if (activeSection === 'bonuses') {
      loadBonuses();
    } else if (activeSection === 'settings') {
      loadNotificationSettings();
    }
  }, [activeSection]);

  // Инициализация формы профиля
  useEffect(() => {
    if (user) {
      setProfileForm({
        name: user.name || '',
        phone: user.phone || '',
      });
    }
  }, [user]);

  const loadOrders = async () => {
    setIsLoadingOrders(true);
    try {
      const response = await api.orders.list();
      setOrders(response.data || []);
    } catch (error) {
      console.error('Failed to load orders:', error);
    } finally {
      setIsLoadingOrders(false);
    }
  };

  const loadAddresses = async () => {
    setIsLoadingAddresses(true);
    try {
      const response = await api.addresses.list();
      const addressesList = response.data || [];
      // Сортируем адреса: основной по умолчанию идет первым
      const sortedAddresses = [...addressesList].sort((a, b) => {
        if (a.is_default && !b.is_default) return -1;
        if (!a.is_default && b.is_default) return 1;
        return 0;
      });
      setAddresses(sortedAddresses);
    } catch (error) {
      console.error('Failed to load addresses:', error);
    } finally {
      setIsLoadingAddresses(false);
    }
  };

  const loadShippingLocations = async () => {
    try {
      const response = await api.shipping.getLocations({ type: 'locality' });
      setShippingLocations(response.data || []);
    } catch (error) {
      console.error('Failed to load shipping locations:', error);
    }
  };

  const loadFavorites = async () => {
    setIsLoadingFavorites(true);
    try {
      const response = await api.wishlist.list();
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

  const loadNotificationSettings = async () => {
    setIsLoadingNotifications(true);
    try {
      const response = await api.auth.getNotificationSettings();
      setNotificationSettings(response.settings);
    } catch (error) {
      console.error('Failed to load notification settings:', error);
    } finally {
      setIsLoadingNotifications(false);
    }
  };

  const handleNotificationChange = async (key: keyof NotificationSettings, value: boolean) => {
    const updatedSettings = { ...notificationSettings, [key]: value };
    setNotificationSettings(updatedSettings);
    setNotificationError(null);
    setNotificationSuccess(false);
    
    setIsSavingNotifications(true);
    try {
      await api.auth.updateNotificationSettings(updatedSettings);
      setNotificationSuccess(true);
      setTimeout(() => setNotificationSuccess(false), 3000);
    } catch (error: any) {
      const errorMessage = error?.data?.message || error?.message || 'Ошибка обновления настроек';
      setNotificationError(errorMessage);
      // Откатываем изменение при ошибке
      setNotificationSettings(notificationSettings);
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
      await api.auth.changePassword({
        current_password: passwordForm.current_password,
        password: passwordForm.password,
        password_confirmation: passwordForm.password_confirmation,
      });
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
      await logout();
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

  const handleProfileSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setProfileError(null);
    setProfileSuccess(false);
    
    // Валидация
    if (!profileForm.name.trim()) {
      setProfileError('Имя обязательно для заполнения');
      return;
    }
    
    if (profileForm.phone && !/^[\d\s\-\+\(\)]+$/.test(profileForm.phone)) {
      setProfileError('Неверный формат телефона');
      return;
    }
    
    setIsSavingProfile(true);

    try {
      await updateProfile({
        name: profileForm.name.trim(),
        phone: profileForm.phone?.trim() || undefined,
      });
      setProfileSuccess(true);
      setLastUpdated(new Date());
      setTimeout(() => setProfileSuccess(false), 3000);
    } catch (error: any) {
      const errorMessage = error?.data?.message || error?.message || 'Ошибка обновления профиля';
      setProfileError(errorMessage);
    } finally {
      setIsSavingProfile(false);
    }
  };

  const handleDeleteAddress = async () => {
    if (!addressToDelete) return;
    
    try {
      await api.addresses.delete(addressToDelete.id);
      setAddresses(addresses.filter(addr => addr.id !== addressToDelete.id));
      setAddressToDelete(null);
    } catch (error: any) {
      console.error('Failed to delete address:', error);
      alert(error?.data?.message || 'Ошибка удаления адреса');
    }
  };

  const handleSetDefaultAddress = async (id: number) => {
    try {
      await api.addresses.setDefault(id);
      await loadAddresses();
    } catch (error: any) {
      console.error('Failed to set default address:', error);
      alert(error?.data?.message || 'Ошибка установки адреса по умолчанию');
    }
  };

  const handleOpenAddAddress = () => {
    setEditingAddress(null);
    setAddressForm({
      title: '',
      city: '',
      street: '',
      house: '',
      apartment: '',
      entrance: '',
      shipping_location_id: null,
    });
    setAddressError(null);
    setShowAddressModal(true);
  };

  const handleOpenEditAddress = (address: Address) => {
    setEditingAddress(address);
    setAddressForm({
      title: address.title || '',
      city: address.city || '',
      street: address.street || '',
      house: address.house || '',
      apartment: address.apartment || '',
      entrance: address.entrance || '',
      shipping_location_id: address.shipping_location_id || null,
    });
    setAddressError(null);
    setShowAddressModal(true);
  };

  const handleCloseAddressModal = () => {
    setShowAddressModal(false);
    setEditingAddress(null);
    setAddressForm({
      title: '',
      city: '',
      street: '',
      house: '',
      apartment: '',
      entrance: '',
      shipping_location_id: null,
    });
    setAddressError(null);
  };

  const handleAddressSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setAddressError(null);

    // Валидация
    if (!addressForm.city.trim()) {
      setAddressError('Город обязателен для заполнения');
      return;
    }
    if (!addressForm.street.trim()) {
      setAddressError('Улица обязательна для заполнения');
      return;
    }
    if (!addressForm.house.trim()) {
      setAddressError('Дом обязателен для заполнения');
      return;
    }

    setIsSavingAddress(true);

    try {
      const addressData: any = {
        title: addressForm.title.trim() || undefined,
        city: addressForm.city.trim(),
        street: addressForm.street.trim(),
        house: addressForm.house.trim(),
        apartment: addressForm.apartment.trim() || undefined,
        entrance: addressForm.entrance.trim() || undefined,
        shipping_location_id: addressForm.shipping_location_id || undefined,
      };

      if (editingAddress) {
        await api.addresses.update(editingAddress.id, addressData);
      } else {
        await api.addresses.create(addressData);
      }

      await loadAddresses();
      handleCloseAddressModal();
    } catch (error: any) {
      const errorMessage = error?.data?.message || error?.message || 'Ошибка сохранения адреса';
      setAddressError(errorMessage);
    } finally {
      setIsSavingAddress(false);
    }
  };

  const handleRepeatOrder = async (orderId: number) => {
    try {
      const response = await api.orders.repeat(orderId) as {
        message: string;
        added_items: Array<{ product_id: number; product_name: string; quantity: number; was_adjusted: boolean }>;
        skipped_items: Array<{ product_id: number; product_name?: string; requested_quantity: number; reason: string }>;
        errors: Array<{ product_id: number; product_name: string; error: string }>;
        added_count: number;
        skipped_count: number;
      };
      const message = response?.message || 'Товары добавлены в корзину';
      
      // Показываем информативное сообщение
      if (response?.skipped_count && response.skipped_count > 0) {
        alert(`${message}\n\nОбратите внимание: некоторые товары не были добавлены, так как они недоступны.`);
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
      await api.wishlist.remove(productId);
      setFavorites(favorites.filter(item => item.product_id !== productId));
      // Небольшая задержка, чтобы дать время API обновиться
      await new Promise(resolve => setTimeout(resolve, 100));
      await refreshWishlistCount();
    } catch (error) {
      console.error('Failed to remove favorite:', error);
    }
  };

  if (!user) {
    return null;
  }

  const userInitial = user.name.charAt(0).toUpperCase();

  return (
    <div className="min-h-screen bg-white">

      <div className="max-w-7xl mx-auto px-4 py-6">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-sm mb-2">
          <Link to="/" className="text-gray-600 hover:text-red-600">Главная</Link>
          <i className="ri-arrow-right-s-line text-gray-400"></i>
          <span className="text-gray-900">Личный кабинет</span>
        </div>

        <h1 className="text-4xl font-bold mb-8">Личный кабинет</h1>

        <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
          {/* Sidebar */}
          <div className="lg:col-span-1">
            <div className="bg-white border border-gray-200 rounded-2xl p-6 sticky top-4">
              {/* User Info */}
              <div className="text-center mb-6 pb-6 border-b border-gray-200">
                <div className="w-20 h-20 bg-gradient-to-r from-red-600 to-yellow-500 rounded-full flex items-center justify-center mx-auto mb-3">
                  <span className="text-3xl font-bold text-white">{userInitial}</span>
                </div>
                <h3 className="font-bold text-lg">{user.name.toUpperCase()}</h3>
                <p className="text-sm text-gray-600">{user.email}</p>
                {orders.length > 0 && (
                  <div className="mt-4 pt-4 border-t border-gray-200">
                    <div className="text-xs text-gray-500 mb-1">Последний заказ</div>
                    <Link
                      to={`/orders/${orders[0].id}`}
                      className="text-sm text-red-600 hover:text-red-700 font-medium"
                    >
                      Заказ {orders[0].number || `#${orders[0].id}`} →
                    </Link>
                  </div>
                )}
              </div>

              {/* Menu */}
              <nav className="space-y-2">
                {menuItems.map((item) => (
                  <button
                    key={item.id}
                    onClick={() => setActiveSection(item.id)}
                    className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg font-medium transition-colors cursor-pointer whitespace-nowrap ${
                      activeSection === item.id
                        ? 'bg-red-50 text-red-600'
                        : 'text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    <i className={`${item.icon} text-xl`}></i>
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
                      <i className="ri-logout-box-line text-xl"></i>
                      Выйти
                    </>
                  )}
                </button>
              </nav>
            </div>
          </div>

          {/* Content */}
          <div className="lg:col-span-3">
            {/* Profile */}
            {activeSection === 'profile' && (
              <div className="bg-white border border-gray-200 rounded-2xl p-8">
                <div className="flex items-center justify-between mb-6">
                  <div className="flex items-center gap-3">
                    <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                      <i className="ri-user-line text-2xl text-red-600"></i>
                    </div>
                    <h2 className="text-2xl font-bold">Личные данные</h2>
                  </div>
                  {lastUpdated && (
                    <span className="text-sm text-gray-500">
                      Обновлено: {lastUpdated.toLocaleString('ru-RU')}
                    </span>
                  )}
                </div>
                
                {profileError && (
                  <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                    <i className="ri-error-warning-line text-xl text-red-600 mt-0.5"></i>
                    <p className="text-red-600 text-sm flex-1">{profileError}</p>
                  </div>
                )}
                
                {profileSuccess && (
                  <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg flex items-start gap-3">
                    <i className="ri-checkbox-circle-line text-xl text-green-600 mt-0.5"></i>
                    <p className="text-green-600 text-sm flex-1">Профиль успешно обновлен</p>
                  </div>
                )}

                <form onSubmit={handleProfileSubmit} className="space-y-6">
                  <div>
                    <label className="block text-sm font-medium mb-2">
                      Имя *
                      <span className="text-gray-500 font-normal ml-2">Как к вам обращаться</span>
                    </label>
                    <input
                      type="text"
                      value={profileForm.name}
                      onChange={(e) => setProfileForm({ ...profileForm, name: e.target.value })}
                      required
                      placeholder="Введите ваше имя"
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-2">
                      Email *
                      <span className="text-gray-500 font-normal ml-2">Используется для входа</span>
                    </label>
                    <input
                      type="email"
                      value={user.email}
                      disabled
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm bg-gray-50 text-gray-500 cursor-not-allowed"
                    />
                    <p className="text-xs text-gray-500 mt-1">Email нельзя изменить</p>
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-2">
                      Телефон
                      <span className="text-gray-500 font-normal ml-2">Для связи по заказам</span>
                    </label>
                    <PhoneInput
                      type="tel"
                      value={profileForm.phone}
                      onChange={(e) => setProfileForm({ ...profileForm, phone: e.target.value })}
                      placeholder="+7 (999) 123-45-67"
                      pattern="[\d\s\-\+\(\)]+"
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100"
                    />
                    <p className="text-xs text-gray-500 mt-1">Укажите номер телефона для связи по заказам</p>
                  </div>
                  <div className="flex items-center gap-4 pt-2">
                    <button
                      type="submit"
                      disabled={isSavingProfile}
                      className="bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                      {isSavingProfile ? (
                        <>
                          <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                          <span>Сохранение...</span>
                        </>
                      ) : (
                        <>
                          <i className="ri-save-line"></i>
                          <span>Сохранить изменения</span>
                        </>
                      )}
                    </button>
                    {profileForm.name !== (user.name || '') || profileForm.phone !== (user.phone || '') ? (
                      <span className="text-sm text-gray-500">Есть несохраненные изменения</span>
                    ) : null}
                  </div>
                </form>
              </div>
            )}

            {/* Orders */}
            {activeSection === 'orders' && (
              <div>
                <div className="flex items-center justify-between mb-6">
                  <div className="flex items-center gap-3">
                    <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                      <i className="ri-shopping-bag-line text-2xl text-red-600"></i>
                    </div>
                    <div>
                      <h2 className="text-2xl font-bold">Мои заказы</h2>
                      {orders.length > 0 && (
                        <p className="text-sm text-gray-500 mt-1">
                          Всего заказов: {orders.length} • 
                          Сумма покупок: {orders.reduce((sum, order) => sum + order.total, 0).toLocaleString()} ₽
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
                ) : orders.length === 0 ? (
                  <div className="bg-white border border-gray-200 rounded-2xl p-8 text-center">
                    <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <i className="ri-shopping-bag-line text-4xl text-gray-400"></i>
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
                      const productImage = firstItem?.product?.image || firstItem?.product?.thumbnail;
                      return (
                        <div key={order.id} className="bg-white border border-gray-200 rounded-2xl p-6 hover:shadow-md transition-shadow">
                          <div className="flex items-start justify-between mb-4">
                            <div className="flex-1">
                              <div className="flex items-center gap-3 mb-2">
                                <i className="ri-file-list-3-line text-xl text-red-600"></i>
                                <h3 className="font-bold text-lg">Заказ {order.number || `#${order.id}`}</h3>
                              </div>
                              <p className="text-sm text-gray-600 flex items-center gap-2">
                                <i className="ri-calendar-line"></i>
                                {formatDate(order.created_at)}
                              </p>
                            </div>
                            <span className={`px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap bg-${statusColor}-100 text-${statusColor}-700`}>
                              {order.status_label || order.status}
                            </span>
                          </div>
                          {firstItem && (
                            <div className="flex items-center gap-4 mb-4">
                              {productImage ? (
                                <img src={productImage} alt={firstItem.product?.name || 'Товар'} className="w-20 h-16 object-cover object-top rounded-lg" />
                              ) : (
                                <div className="w-20 h-16 flex items-center justify-center rounded-lg">
                                  <i className="ri-image-line text-3xl text-gray-400"></i>
                                </div>
                              )}
                              <div className="flex-1">
                                <p className="text-gray-700">Товаров: {order.items?.length || 0}</p>
                                <p className="text-2xl font-bold text-red-600">{order.total.toLocaleString()} ₽</p>
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
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" data-product-shop>
                    {favorites.map((item) => {
                      const product = item.product;
                      return (
                        <div key={item.id} className="bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-lg transition-shadow">
                          <div className="relative h-48">
                            {product.image ? (
                              <img src={product.image} alt={product.name} className="w-full h-full object-cover object-top" />
                            ) : (
                              <div className="w-full h-full flex items-center justify-center">
                                <i className="ri-image-line text-3xl text-gray-400"></i>
                              </div>
                            )}
                            <button
                              onClick={() => handleRemoveFavorite(product.id)}
                              className="absolute top-4 right-4 w-10 h-10 bg-red-600 rounded-full flex items-center justify-center shadow-md hover:bg-red-700 cursor-pointer"
                            >
                              <i className="ri-heart-fill text-xl text-white"></i>
                            </button>
                            {!product.in_stock && (
                              <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                                <span className="bg-white px-4 py-2 rounded-lg font-medium">Нет в наличии</span>
                              </div>
                            )}
                          </div>
                          <div className="p-4">
                            <h3 className="font-semibold mb-3">{product.name}</h3>
                            <div className="flex items-center gap-2 mb-4">
                              <span className="text-xl font-bold text-red-600">{product.price.toLocaleString()} ₽</span>
                              {product.old_price && (
                                <span className="text-sm text-gray-400 line-through">{product.old_price.toLocaleString()} ₽</span>
                              )}
                            </div>
                            <div className="flex gap-2">
                              <Link
                                to={`/product/${product.slug}`}
                                className="flex-1 bg-red-600 text-white py-3 rounded-lg font-medium hover:bg-red-700 transition-colors disabled:bg-gray-300 whitespace-nowrap text-center"
                              >
                                {product.in_stock ? 'В корзину' : 'Недоступно'}
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

            {/* Addresses */}
            {activeSection === 'addresses' && (
              <div>
                <div className="flex items-center justify-between mb-6">
                  <div className="flex items-center gap-3">
                    <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                      <i className="ri-map-pin-line text-2xl text-red-600"></i>
                    </div>
                    <h2 className="text-2xl font-bold">Адреса доставки</h2>
                  </div>
                  <button 
                    onClick={handleOpenAddAddress}
                    className="bg-red-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap flex items-center gap-2"
                  >
                    <i className="ri-add-line"></i>
                    <span>Добавить адрес</span>
                  </button>
                </div>
                {isLoadingAddresses ? (
                  <div className="text-center py-12">
                    <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
                    <p className="mt-4 text-gray-600">Загрузка адресов...</p>
                  </div>
                ) : addresses.length === 0 ? (
                  <div className="bg-white border border-gray-200 rounded-2xl p-8 text-center">
                    <p className="text-gray-600">У вас пока нет адресов доставки</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {addresses.map((addr) => (
                      <div 
                        key={addr.id} 
                        className={`bg-white border-2 rounded-2xl p-6 hover:shadow-md transition-all ${
                          addr.is_default 
                            ? 'border-green-300 bg-green-50/30' 
                            : 'border-gray-200'
                        }`}
                      >
                        <div className="flex items-start justify-between mb-3">
                          <div className="flex-1">
                            <div className="flex items-center gap-3 mb-2">
                              <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
                                addr.is_default ? 'bg-green-100' : 'bg-red-100'
                              }`}>
                                <i className={`ri-map-pin-2-line text-xl ${
                                  addr.is_default ? 'text-green-600' : 'text-red-600'
                                }`}></i>
                              </div>
                              <div className="flex-1">
                                {addr.title && <h3 className="font-bold text-lg">{addr.title}</h3>}
                                {!addr.title && addr.is_default && (
                                  <h3 className="font-bold text-lg">Адрес по умолчанию</h3>
                                )}
                              </div>
                            </div>
                            <div className="space-y-1">
                              {addr.shipping_location && (
                                <p className="text-sm font-medium text-red-600 flex items-center gap-1">
                                  <i className="ri-map-pin-3-line"></i>
                                  {addr.shipping_location.name}
                                </p>
                              )}
                              <p className="text-gray-700 text-sm">{addr.full_address || addr.address}</p>
                              <p className="text-xs text-gray-500 flex items-center gap-1">
                                <i className="ri-calendar-line"></i>
                                Добавлен: {new Date(addr.created_at || '').toLocaleDateString('ru-RU')}
                              </p>
                            </div>
                          </div>
                          {addr.is_default && (
                            <span className="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-medium whitespace-nowrap flex items-center gap-1 shadow-sm">
                              <i className="ri-check-line"></i>
                              Основной
                            </span>
                          )}
                        </div>
                        <div className="flex gap-3 pt-3 border-t border-gray-100">
                          <button 
                            onClick={() => handleOpenEditAddress(addr)}
                            className="text-red-600 hover:text-red-700 hover:underline flex items-center gap-1 transition-colors"
                          >
                            <i className="ri-edit-line"></i>
                            Редактировать
                          </button>
                          <button
                            onClick={() => setAddressToDelete(addr)}
                            className="text-gray-600 hover:text-red-600 hover:underline flex items-center gap-1 transition-colors"
                          >
                            <i className="ri-delete-bin-line"></i>
                            Удалить
                          </button>
                          {!addr.is_default && (
                            <button
                              onClick={() => handleSetDefaultAddress(addr.id)}
                              className="text-green-600 hover:text-green-700 hover:underline flex items-center gap-1 transition-colors"
                            >
                              <i className="ri-star-line"></i>
                              Сделать основным
                            </button>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

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
                          <i className="ri-gift-line text-4xl"></i>
                        </div>
                      </div>
                      <p className="opacity-90">1 бонус = 1 рубль при оплате заказа</p>
                    </div>

                    <div className="bg-white border border-gray-200 rounded-2xl p-8 mb-8">
                      <h3 className="text-xl font-bold mb-4">Как получить бонусы</h3>
                      <div className="space-y-4">
                        <div className="flex items-start gap-4">
                          <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i className="ri-shopping-bag-line text-red-600 text-xl"></i>
                          </div>
                          <div>
                            <h4 className="font-bold mb-1">За покупки</h4>
                            <p className="text-gray-600">Получайте 5% от суммы каждого заказа</p>
                          </div>
                        </div>
                        <div className="flex items-start gap-4">
                          <div className="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i className="ri-star-line text-yellow-600 text-xl"></i>
                          </div>
                          <div>
                            <h4 className="font-bold mb-1">За отзывы</h4>
                            <p className="text-gray-600">100 бонусов за каждый отзыв с фото</p>
                          </div>
                        </div>
                        <div className="flex items-start gap-4">
                          <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i className="ri-user-add-line text-green-600 text-xl"></i>
                          </div>
                          <div>
                            <h4 className="font-bold mb-1">За друзей</h4>
                            <p className="text-gray-600">500 бонусов за каждого приведенного друга</p>
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
                              <div key={item.id} className="flex items-center justify-between py-3 border-b border-gray-200 last:border-0">
                                <div>
                                  <p className="font-medium">{item.description}</p>
                                  <p className="text-sm text-gray-500">{formatDate(item.created_at)}</p>
                                </div>
                                <span className={`font-bold text-${color}-600`}>
                                  {sign}{item.amount.toLocaleString()} ₽
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
                <div className="bg-white border border-gray-200 rounded-2xl p-8">
                  <div className="flex items-center gap-3 mb-6">
                    <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                      <i className="ri-notification-line text-2xl text-red-600"></i>
                    </div>
                    <h2 className="text-2xl font-bold">Настройки уведомлений</h2>
                  </div>

                  {isLoadingNotifications ? (
                    <div className="text-center py-8">
                      <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-red-600"></div>
                      <p className="mt-4 text-gray-600 text-sm">Загрузка настроек...</p>
                    </div>
                  ) : (
                    <>
                      {notificationError && (
                        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                          <i className="ri-error-warning-line text-xl text-red-600 mt-0.5"></i>
                          <p className="text-red-600 text-sm flex-1">{notificationError}</p>
                        </div>
                      )}

                      {notificationSuccess && (
                        <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg flex items-start gap-3">
                          <i className="ri-checkbox-circle-line text-xl text-green-600 mt-0.5"></i>
                          <p className="text-green-600 text-sm flex-1">Настройки уведомлений обновлены</p>
                        </div>
                      )}

                      <div className="space-y-4">
                        {[
                          { key: 'email_promotions' as keyof NotificationSettings, label: 'Email-рассылка с акциями' },
                          { key: 'sms_order_notifications' as keyof NotificationSettings, label: 'SMS-уведомления о заказах' },
                          { key: 'push_notifications' as keyof NotificationSettings, label: 'Push-уведомления' },
                          { key: 'new_product_notifications' as keyof NotificationSettings, label: 'Уведомления о новинках' }
                        ].map((item) => (
                          <label key={item.key} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors">
                            <span className="font-medium">{item.label}</span>
                            <div className="relative">
                              <input
                                type="checkbox"
                                checked={notificationSettings[item.key]}
                                onChange={(e) => handleNotificationChange(item.key, e.target.checked)}
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
                      <i className="ri-lock-password-line text-2xl text-red-600"></i>
                    </div>
                    <h2 className="text-2xl font-bold">Изменить пароль</h2>
                  </div>

                  {passwordError && (
                    <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                      <i className="ri-error-warning-line text-xl text-red-600 mt-0.5"></i>
                      <p className="text-red-600 text-sm flex-1">{passwordError}</p>
                    </div>
                  )}

                  {passwordSuccess && (
                    <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg flex items-start gap-3">
                      <i className="ri-checkbox-circle-line text-xl text-green-600 mt-0.5"></i>
                      <p className="text-green-600 text-sm flex-1">Пароль успешно изменен</p>
                    </div>
                  )}

                  <form onSubmit={handlePasswordSubmit} className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium mb-2">Текущий пароль</label>
                      <input
                        type="password"
                        value={passwordForm.current_password}
                        onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })}
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
                        onChange={(e) => setPasswordForm({ ...passwordForm, password: e.target.value })}
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
                        onChange={(e) => setPasswordForm({ ...passwordForm, password_confirmation: e.target.value })}
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
                          <i className="ri-save-line"></i>
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

      {/* Address Modal */}
      {showAddressModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl p-8 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-2xl font-bold">
                {editingAddress ? 'Редактировать адрес' : 'Добавить адрес'}
              </h3>
              <button
                onClick={handleCloseAddressModal}
                className="text-gray-400 hover:text-gray-600 transition-colors"
              >
                <i className="ri-close-line text-2xl"></i>
              </button>
            </div>

            {addressError && (
              <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                <i className="ri-error-warning-line text-xl text-red-600 mt-0.5"></i>
                <p className="text-red-600 text-sm flex-1">{addressError}</p>
              </div>
            )}

            <form onSubmit={handleAddressSubmit} className="space-y-6">
              <div>
                <label className="block text-sm font-medium mb-2">
                  Название адреса
                  <span className="text-gray-500 font-normal ml-2">(необязательно)</span>
                </label>
                <input
                  type="text"
                  value={addressForm.title}
                  onChange={(e) => setAddressForm({ ...addressForm, title: e.target.value })}
                  placeholder="Дом, Офис, Квартира"
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100"
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Локация доставки</label>
                <select
                  value={addressForm.shipping_location_id || ''}
                  onChange={(e) => setAddressForm({ ...addressForm, shipping_location_id: e.target.value ? parseInt(e.target.value) : null })}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100"
                >
                  <option value="">Выберите локацию</option>
                  {shippingLocations.map((location) => (
                    <option key={location.id} value={location.id}>
                      {location.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Город *</label>
                <input
                  type="text"
                  required
                  value={addressForm.city}
                  onChange={(e) => setAddressForm({ ...addressForm, city: e.target.value })}
                  placeholder="Москва"
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100"
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Улица *</label>
                <input
                  type="text"
                  required
                  value={addressForm.street}
                  onChange={(e) => setAddressForm({ ...addressForm, street: e.target.value })}
                  placeholder="ул. Ленина"
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100"
                />
              </div>

              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-2">Дом *</label>
                  <input
                    type="text"
                    required
                    value={addressForm.house}
                    onChange={(e) => setAddressForm({ ...addressForm, house: e.target.value })}
                    placeholder="1"
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-2">Квартира</label>
                  <input
                    type="text"
                    value={addressForm.apartment}
                    onChange={(e) => setAddressForm({ ...addressForm, apartment: e.target.value })}
                    placeholder="10"
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-2">Подъезд</label>
                  <input
                    type="text"
                    value={addressForm.entrance}
                    onChange={(e) => setAddressForm({ ...addressForm, entrance: e.target.value })}
                    placeholder="2"
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100"
                  />
                </div>
              </div>

              <div className="flex gap-4 pt-4">
                <button
                  type="button"
                  onClick={handleCloseAddressModal}
                  disabled={isSavingAddress}
                  className="flex-1 px-6 py-3 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors whitespace-nowrap disabled:opacity-50"
                >
                  Отмена
                </button>
                <button
                  type="submit"
                  disabled={isSavingAddress}
                  className="flex-1 px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                >
                  {isSavingAddress ? (
                    <>
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                      <span>Сохранение...</span>
                    </>
                  ) : (
                    <>
                      <i className="ri-save-line"></i>
                      <span>{editingAddress ? 'Сохранить изменения' : 'Добавить адрес'}</span>
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete Address Confirmation Modal */}
      {addressToDelete && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4">
          <div className="bg-white rounded-2xl p-8 max-w-md w-full">
            <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="ri-error-warning-line text-3xl text-red-600"></i>
            </div>
            <h3 className="text-2xl font-bold text-center mb-2">Удалить адрес?</h3>
            <p className="text-gray-600 text-center mb-6">
              Вы уверены, что хотите удалить адрес "{addressToDelete.title || addressToDelete.full_address || 'без названия'}"? 
              Это действие нельзя будет отменить.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setAddressToDelete(null)}
                className="flex-1 px-6 py-3 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors whitespace-nowrap"
              >
                Отмена
              </button>
              <button
                onClick={handleDeleteAddress}
                className="flex-1 px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap"
              >
                Удалить
              </button>
            </div>
          </div>
        </div>
      )}

    </div>
  );
}
