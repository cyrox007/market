import { SvetoforFurnitureAPIClient } from "./svetoforFurnitureAPI.ts";

// Схема обязательна для k6 URL (иначе "Invalid scheme"); по умолчанию http
const rawBase = __ENV.K6_BASE_URL || "http://localhost:8000";
const baseUrl =
  rawBase.startsWith("http://") || rawBase.startsWith("https://")
    ? rawBase
    : `http://${rawBase}`;
const svetoforFurnitureAPIClient = new SvetoforFurnitureAPIClient({ baseUrl });

export default function () {
  let registerBody,
    loginBody,
    updateProfileBody,
    changePasswordBody,
    updateNotificationSettingsBody,
    slug,
    params,
    id,
    addToCartBody,
    itemId,
    updateCartItemBody;

  /**
   * Регистрация нового пользователя
   */
  registerBody = {
    name: "Иван Иванов",
    email: "user@example.com",
    password: "password123",
    password_confirmation: "password123",
    phone: "+7 (999) 123-45-67",
  };

  const registerResponseData =
    svetoforFurnitureAPIClient.register(registerBody);

  /**
   * Вход в систему
   */
  loginBody = {
    email: "user@example.com",
    password: "password123",
  };

  const loginResponseData = svetoforFurnitureAPIClient.login(loginBody);

  /**
   * Выход из системы
   */

  const logoutResponseData = svetoforFurnitureAPIClient.logout();

  /**
   * Получить информацию о текущем пользователе
   */

  const meResponseData = svetoforFurnitureAPIClient.me();

  /**
   * Обновить профиль пользователя
   */
  updateProfileBody = {
    name: "Иван Иванов",
    phone: "+7 (999) 123-45-67",
  };

  const updateProfileResponseData =
    svetoforFurnitureAPIClient.updateProfile(updateProfileBody);

  /**
   * Изменить пароль
   */
  changePasswordBody = {
    current_password: "oldpassword123",
    password: "newpassword123",
    password_confirmation: "newpassword123",
  };

  const changePasswordResponseData =
    svetoforFurnitureAPIClient.changePassword(changePasswordBody);

  /**
   * Получить настройки уведомлений
   */

  const getNotificationSettingsResponseData =
    svetoforFurnitureAPIClient.getNotificationSettings();

  /**
   * Обновить настройки уведомлений
   */
  updateNotificationSettingsBody = {
    email_promotions: "true",
    sms_order_notifications: "true",
    push_notifications: "false",
    new_product_notifications: "true",
  };

  const updateNotificationSettingsResponseData =
    svetoforFurnitureAPIClient.updateNotificationSettings(
      updateNotificationSettingsBody,
    );

  /**
   * Получить список товаров
   */

  const getProductsResponseData = svetoforFurnitureAPIClient.getProducts();

  /**
   * Получить детальную информацию о товаре
   */
  slug = "heartfelt";

  const getProductResponseData = svetoforFurnitureAPIClient.getProduct(slug);

  /**
   * Получить рекомендуемые товары
   */

  const getFeaturedProductsResponseData =
    svetoforFurnitureAPIClient.getFeaturedProducts();

  /**
   * Получить новые товары
   */

  const getNewProductsResponseData =
    svetoforFurnitureAPIClient.getNewProducts();

  /**
   * Получить товары со скидкой
   */

  const getSaleProductsResponseData =
    svetoforFurnitureAPIClient.getSaleProducts();

  /**
   * Поиск товаров
   */
  params = {
    q: "huzzah",
  };

  const searchProductsResponseData =
    svetoforFurnitureAPIClient.searchProducts(params);

  /**
   * Получить товары из подборки
   */
  slug = "unscramble";

  const getProductCollectionResponseData =
    svetoforFurnitureAPIClient.getProductCollection(slug);

  /**
   * Получить похожие товары
   */
  id = 5196458434080073;

  const getRelatedProductsResponseData =
    svetoforFurnitureAPIClient.getRelatedProducts(id);

  /**
   * Получить содержимое корзины
   */

  const getCartResponseData = svetoforFurnitureAPIClient.getCart();

  /**
   * Добавить товар в корзину
   */
  addToCartBody = {
    product_id: "1",
    quantity: "1",
    color: "красный",
    size: "180x90",
  };

  const addToCartResponseData =
    svetoforFurnitureAPIClient.addToCart(addToCartBody);

  /**
   * Очистить корзину
   */

  const clearCartResponseData = svetoforFurnitureAPIClient.clearCart();

  /**
   * Получить количество товаров в корзине
   */

  const getCartCountResponseData = svetoforFurnitureAPIClient.getCartCount();

  /**
   * Обновить товар в корзине
   */
  itemId = 3556134587024605;
  updateCartItemBody = {
    quantity: "2",
    color: "красный",
    size: "180x90",
  };

  const updateCartItemResponseData = svetoforFurnitureAPIClient.updateCartItem(
    itemId,
    updateCartItemBody,
  );

  /**
   * Удалить товар из корзины
   */
  itemId = 8738510663223184;

  const removeCartItemResponseData =
    svetoforFurnitureAPIClient.removeCartItem(itemId);
}
