import type { DimensionKey } from './catalog-query';
import { checkoutIdempotencyKey } from './checkout-idempotency';
import { guestOrderHeaders, saveGuestOrderToken } from './guest-order-access';
import { retryWithoutDirectory, toProductListSearch } from './product-list-search';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1';

const API_TIMEOUT_MS = 20_000;

// Очередь сессии и вход не обрываем: сервер доработает запрос и запишет сессию поверх следующего
const NO_TIMEOUT_PREFIXES = ['/cart', '/wishlist', '/compare', '/counters', '/orders', '/auth'];

const needsTimeout = (endpoint: string) =>
  !NO_TIMEOUT_PREFIXES.some(
    (prefix) =>
      endpoint === prefix || endpoint.startsWith(`${prefix}/`) || endpoint.startsWith(`${prefix}?`),
  );

async function fetchAPI<T>(endpoint: string, options?: RequestInit): Promise<T> {
  // Убеждаемся, что endpoint начинается с /, а API_BASE_URL не заканчивается на /
  const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
  const cleanBaseUrl = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL;
  const url = endpoint.startsWith('http') ? endpoint : `${cleanBaseUrl}${cleanEndpoint}`;

  const controller = !options?.signal && needsTimeout(cleanEndpoint) ? new AbortController() : null;
  const timeoutId = controller ? setTimeout(() => controller.abort(), API_TIMEOUT_MS) : undefined;
  try {
    return await request<T>(url, { ...options, signal: controller?.signal ?? options?.signal });
  } catch (err) {
    if (controller?.signal.aborted) {
      throw Object.assign(new Error(`API Timeout after ${API_TIMEOUT_MS}ms`), { status: 504 });
    }
    throw err;
  } finally {
    clearTimeout(timeoutId);
  }
}

async function request<T>(url: string, options: RequestInit): Promise<T> {
  const response = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(typeof window !== 'undefined' && /^\d+$/.test(new URLSearchParams(window.location.search).get('offer') || '')
        ? { 'X-Private-Price-Context': new URLSearchParams(window.location.search).get('offer')! } : {}),
      ...options?.headers,
    },
    credentials: 'include', // Для поддержки Sanctum cookie-based auth
  });

  if (!response.ok) {
    let errorMessage = response.statusText;
    let errorData: any = null;

    try {
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        errorData = await response.json();
        errorMessage = errorData.message || errorData.error || errorMessage;
      } else {
        const errorText = await response.text().catch(() => response.statusText);
        errorMessage = errorText || errorMessage;
      }
    } catch {
      // Если не удалось распарсить, используем статус текст
    }

    const error = new Error(`API Error: ${response.status} ${errorMessage}`) as any;
    error.status = response.status;
    error.retryAfter = Math.max(
      1,
      Number(response.headers.get('Retry-After')) || Number(errorData?.retry_after) || 60,
    );
    error.data = errorData;
    throw error;
  }

  return response.json();
}

/** Местоположение покупателя: регион цен и пункт справочника для складов (market-docs/44) */
export interface LocationParams {
  region_id?: number;
  directory_external_id?: string;
}

const locationSearch = (params?: LocationParams, query = new URLSearchParams()) => {
  if (params?.region_id) query.append('region_id', params.region_id.toString());
  if (params?.directory_external_id)
    query.append('directory_external_id', params.directory_external_id);
  return query.toString() ? `?${query}` : '';
};

const productList = (endpoint: string, params?: LocationParams) =>
  retryWithoutDirectory((p: LocationParams) =>
    fetchAPI<{ data: Product[] }>(`${endpoint}${locationSearch(p)}`),
  )(params ?? {});

export interface CustomerLocality {
  externalId: string;
  name: string;
  label: string;
  source: string;
  kladrCode?: string | null;
  /** Отдаёт GET /localities/{id}; в поиске справочника нет */
  regionExternalId?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  coordinatePrecision?: string | null;
}

/** Ответ `POST /shipping/quote` — то, что нужно странице товара (market-docs/frontend-fulfillment-contract.md) */
export interface ReadyRange {
  days_min: number;
  days_max: number;
  date_min?: string;
  date_max?: string;
  /** «Сегодня» / «Завтра» — бэк ставит только при точном сроке 0 / 1 */
  label?: string | null;
}

export interface ShippingQuote {
  /** false — доставки нет, самовывоз при этом может быть */
  available: boolean;
  selected: { delivery: ReadyRange } | null;
  options: { delivery: ReadyRange }[];
  pickup: { ready: ReadyRange }[];
}

/** Регион справочника адресов (ГАР) — первый шаг выбора пункта */
export interface DirectoryRegion {
  externalId: string;
  name: string;
  label: string;
}

export const api = {
  /** Справочник адресов: регион, затем пункт внутри него (бэкендер, 21.10) */
  addressDirectory: {
    options: (level: 'localities' | 'streets' | 'buildings', parentExternalId: string | null, q: string, signal?: AbortSignal) => {
      const query = new URLSearchParams({ q, limit: '50' });
      if (parentExternalId) query.set('parentExternalId', parentExternalId);
      return fetchAPI<{ data: Array<{ externalId: string; label: string; name: string }> }>(`/address-directory/${level}?${query}`, { signal });
    },
    regions: (signal?: AbortSignal) =>
      fetchAPI<{ data: DirectoryRegion[] }>('/localities/regions', { signal }),
    localities: (
      params: { parentExternalId: string; q?: string; limit?: number },
      signal?: AbortSignal,
    ) => {
      const query = new URLSearchParams({ parentExternalId: params.parentExternalId });
      if (params.q) query.append('q', params.q);
      if (params.limit) query.append('limit', String(params.limit));
      return fetchAPI<{ data: CustomerLocality[] }>(`/address-directory/localities?${query}`, {
        signal,
      });
    },
  },
  localities: {
    search: (q: string, signal?: AbortSignal) =>
      fetchAPI<{ data: CustomerLocality[] }>(`/localities?q=${encodeURIComponent(q)}`, { signal }),
    detect: (latitude: number, longitude: number, signal?: AbortSignal) =>
      fetchAPI<{ data: CustomerLocality[] }>('/localities/detect', {
        method: 'POST',
        body: JSON.stringify({ latitude, longitude }),
        signal,
      }),
    get: (id: string) =>
      fetchAPI<{ data: CustomerLocality; shippingLocation: ShippingLocation | null }>(
        `/localities/${encodeURIComponent(id)}`,
      ),
  },
  shipping: {
    /**
     * Расчёт по складам: доставка и самовывоз одним ответом
     * (market-docs/frontend-fulfillment-contract.md). Карточке товара нужны сроки
     * (`ShippingQuote`), оформлению — стоимость и услуги (`WarehouseQuote`),
     * поэтому форма ответа задаётся на месте вызова.
     */
    quote: <T = WarehouseQuote>(data: unknown) =>
      fetchAPI<T>('/shipping/quote', { method: 'POST', body: JSON.stringify(data) }),
    pickupOptions: (items: Array<{ product_id: number; quantity: number }>) =>
      fetchAPI<{ pickup: PickupOption[] }>('/shipping/pickup-options', {
        method: 'POST',
        body: JSON.stringify({ items }),
      }),
  },
  cart: {
    get: (params?: { region_id?: number }) => {
      const query = new URLSearchParams();
      if (params?.region_id) query.append('region_id', params.region_id.toString());
      return fetchAPI<Cart>(`/cart?${query.toString()}`);
    },
    add: (data: {
      product_id: number;
      quantity?: number;
      region_id?: number;
      color?: string;
      size?: string;
    }) =>
      fetchAPI<{
        item: CartItem;
        message: string;
        requested_quantity: number;
        added_quantity: number;
        was_adjusted: boolean;
      }>('/cart', {
        method: 'POST',
        body: JSON.stringify(data),
      }),
    update: (
      itemId: number,
      data: { quantity: number; region_id?: number; color?: string; size?: string },
    ) =>
      fetchAPI<{
        item: CartItem;
        message: string;
        requested_quantity: number;
        updated_quantity: number;
        was_adjusted: boolean;
      }>(`/cart/${itemId}`, {
        method: 'PUT',
        body: JSON.stringify(data),
      }),
    remove: (itemId: number) =>
      fetchAPI<{ message: string }>(`/cart/${itemId}`, {
        method: 'DELETE',
      }),
    clear: () =>
      fetchAPI<{ message: string }>('/cart', {
        method: 'DELETE',
      }),
    count: () => fetchAPI<{ count: number }>('/cart/count'),
  },
  orders: {
    prepare: (product_id: number, quantity: number) => fetchAPI<{ items: Array<{ product_id: number; quantity: number }>; subtotal: number }>('/orders/one-click', { method: 'POST', body: JSON.stringify({ product_id, quantity }) }),
    prepareBundle: (bundle_id: number, primary_sku_id: number, quantity: number) => fetchAPI<{
      items: Array<{ product_id: number; quantity: number }>;
      subtotal: number;
      priced_components: Array<{ product_id: number; name: string; sku: string; quantity: number; unit_price: number; total: number }>;
    }>('/orders/one-click', { method: 'POST', body: JSON.stringify({ bundle_id, primary_sku_id, quantity }) }),
    list: () => fetchAPI<{ data: Order[] }>('/orders'),
    get: (id: number) => fetchAPI<{ order: Order }>(`/order-access/${id}`, { headers: guestOrderHeaders(id) }),
    create: async (data: any) => {
      const body = JSON.stringify(data);
      const response = await fetchAPI<{ order?: Order; guest_order_token?: string }>('/orders', {
        method: 'POST',
        headers: { 'Idempotency-Key': await checkoutIdempotencyKey(body) },
        body,
      });
      if (response.order && response.guest_order_token) saveGuestOrderToken(response.order.id, response.guest_order_token);
      return response;
    },
    getPaymentConfig: (id: number) =>
      fetchAPI<{
        amount: number;
        order_number: string;
        success_url: string;
        fail_url: string;
        gateway_client_config:
          | { publicId: string; url: string; useSdk?: boolean }
          | { payformUrl: string; useEcomApi: true };
      }>(`/orders/${id}/payment-config`, { headers: guestOrderHeaders(id) }),
    cancel: (id: number, data?: { comment?: string }) =>
      fetchAPI<{ order: Order; message: string }>(`/order-access/${id}/cancel`, {
        method: 'POST',
        headers: guestOrderHeaders(id),
        body: data ? JSON.stringify({ reason: data.comment }) : undefined,
      }),
    requestCancellation: (id: number, reason: string) => fetchAPI(`/order-access/${id}/cancellation-request`, {
      method: 'POST', headers: guestOrderHeaders(id), body: JSON.stringify({ reason }),
    }),
    repeat: (id: number) =>
      fetchAPI<{
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
      }>(`/orders/${id}/repeat`, {
        method: 'POST',
      }),
  },
  paymentMethods: {
    list: (params?: { region_id?: number }) => {
      const query = new URLSearchParams();
      if (params?.region_id) query.append('region_id', params.region_id.toString());
      return fetchAPI<{ data: PaymentMethod[] }>(`/payment-methods?${query.toString()}`);
    },
  },
  addresses: {
    list: () => fetchAPI<{ data: Address[] }>('/addresses'),
    get: (id: number) => fetchAPI<{ address: Address }>(`/addresses/${id}`),
    create: (data: AddressData) =>
      fetchAPI<{ address: Address }>('/addresses', {
        method: 'POST',
        body: JSON.stringify(data),
      }),
    update: (id: number, data: AddressData) =>
      fetchAPI<{ address: Address }>(`/addresses/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
      }),
    delete: (id: number) =>
      fetchAPI<{ message: string }>(`/addresses/${id}`, {
        method: 'DELETE',
      }),
    setDefault: (id: number) =>
      fetchAPI<{ message: string }>(`/addresses/${id}/set-default`, {
        method: 'POST',
      }),
  },
  bonuses: {
    balance: () => fetchAPI<{ balance: number }>('/bonuses/balance'),
    history: () => fetchAPI<{ data: BonusTransaction[] }>('/bonuses/history'),
  },
  chats: {
    list: () => fetchAPI<{ data: Chat[] }>('/chats'),
    get: (id: number) => fetchAPI<{ chat: Chat }>(`/chats/${id}`),
    sendMessage: (id: number, message: string) =>
      fetchAPI<{ message: ChatMessage }>(`/chats/${id}/messages`, {
        method: 'POST',
        body: JSON.stringify({ message }),
      }),
    createOrGet: (orderId: number) =>
      fetchAPI<{ chat: Chat }>(`/chats/orders/${orderId}/create`, {
        method: 'POST',
      }),
  },
  sliders: {
    list: () => fetchAPI<{ data: Slider[] }>('/sliders'),
    get: (slug: string) => fetchAPI<{ slider: Slider }>(`/sliders/${slug}`),
    /** Вся главная одним запросом: верхний блок по позициям и нижний баннер */
    home: () => fetchAPI<{ data: HomeSliders }>('/sliders/home'),
  },
  categories: {
    list: () => fetchAPI<{ data: Category[] }>('/categories'),
    get: (slug: string) => fetchAPI<{ category: Category }>(`/categories/${slug}`),
    tree: () => fetchAPI<{ tree: Category[] }>('/categories/tree'),
  },
  // Комнаты — вторая таксономия; формат ответов совпадает с categories.
  rooms: {
    list: () => fetchAPI<{ data: Category[] }>('/rooms'),
    get: (slug: string) => fetchAPI<{ category: Category }>(`/rooms/${slug}`),
    tree: () => fetchAPI<{ tree: Category[] }>('/rooms/tree'),
  },
  products: {
    /** Справочник адресов ответил 503 — повтор без пункта (market-docs/15) */
    list: (params: ProductListParams = {}) =>
      retryWithoutDirectory((p) =>
        fetchAPI<ProductListResponse>(`/products?${toProductListSearch(p)}`),
      )(params),
    /** Только число карточек — те же фильтры, без товаров и meta.filters */
    count: (params: ProductListParams = {}) =>
      retryWithoutDirectory((p) =>
        fetchAPI<{ total: number }>(`/products/count?${toProductListSearch(p)}`),
      )(params),
    get: (slug: string, params?: { color?: string; size?: string } & LocationParams) =>
      retryWithoutDirectory((p: typeof params = {}) => {
        const query = new URLSearchParams();
        if (p.color) query.append('color', p.color);
        if (p.size) query.append('size', p.size);
        return fetchAPI<ProductPagePayload>(`/products/${slug}${locationSearch(p, query)}`);
      })(params ?? {}),
    featured: (params?: LocationParams) => productList('/products/featured', params),
    new: (params?: LocationParams) => productList('/products/new', params),
    sale: (params?: LocationParams) => productList('/products/sale', params),
    collection: (slug: string, params?: { region_id?: number }) => {
      const query = new URLSearchParams();
      if (params?.region_id) query.append('region_id', params.region_id.toString());
      const suffix = query.toString() ? `?${query.toString()}` : '';
      return fetchAPI<{ data: Product[]; collection?: { slug: string; name: string } }>(
        `/products/collections/${slug}${suffix}`,
      );
    },
    related: (id: number, params?: { region_id?: number }) => {
      const query = new URLSearchParams();
      if (params?.region_id) query.append('region_id', params.region_id.toString());
      const suffix = query.toString() ? `?${query.toString()}` : '';
      return fetchAPI<{ data: Product[] }>(`/products/${id}/related${suffix}`);
    },
    bundle: (id: number, params?: { region_id?: number }) => {
      const query = new URLSearchParams();
      if (params?.region_id) query.append('region_id', params.region_id.toString());
      const suffix = query.toString() ? `?${query.toString()}` : '';
      return fetchAPI<{ data: Product[] }>(`/products/${id}/bundle${suffix}`);
    },
    search: (
      query: string,
      params?: {
        per_page?: number;
        page?: number;
        sort_by?: string;
        sort_order?: 'asc' | 'desc';
        region_id?: number;
        directory_external_id?: string;
      },
    ) => {
      const searchParams = new URLSearchParams();
      searchParams.append('q', query);
      if (params?.per_page) searchParams.append('per_page', params.per_page.toString());
      if (params?.page) searchParams.append('page', params.page.toString());
      if (params?.sort_by) searchParams.append('sort_by', params.sort_by);
      if (params?.sort_order) searchParams.append('sort_order', params.sort_order);
      if (params?.region_id) searchParams.append('region_id', params.region_id.toString());
      if (params?.directory_external_id)
        searchParams.append('directory_external_id', params.directory_external_id);
      return fetchAPI<{
        data: Product[];
        meta: {
          current_page: number;
          last_page: number;
          per_page: number;
          total: number;
        };
        links?: any;
      }>(`/products/search?${searchParams.toString()}`);
    },
  },
  articles: {
    list: (params?: {
      category_id?: number;
      category_slug?: string;
      published?: boolean;
      sort_by?: string;
      sort_order?: 'asc' | 'desc';
      per_page?: number;
    }) => {
      const query = new URLSearchParams();
      if (params?.category_id) query.append('category_id', params.category_id.toString());
      if (params?.category_slug) query.append('category_slug', params.category_slug);
      if (params?.published !== undefined) query.append('published', params.published.toString());
      if (params?.sort_by) query.append('sort_by', params.sort_by);
      if (params?.sort_order) query.append('sort_order', params.sort_order);
      if (params?.per_page) query.append('per_page', params.per_page.toString());
      return fetchAPI<{
        data: Article[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
      }>(`/articles?${query.toString()}`);
    },
    get: (slug: string) => fetchAPI<{ article: Article }>(`/articles/${slug}`),
  },
  contact: {
    get: () => fetchAPI<{ contact: ContactInfo }>('/contact'),
  },
  stores: {
    list: (params?: { city?: string }) => {
      const query = new URLSearchParams();
      if (params?.city && params.city !== 'Все города') query.append('city', params.city);
      return fetchAPI<{ data: Store[] }>(`/stores?${query.toString()}`);
    },
    get: (slug: string) => fetchAPI<{ store: Store }>(`/stores/${slug}`),
    cities: () => fetchAPI<{ cities: string[] }>('/stores/cities'),
  },
  wishlist: {
    list: () => fetchAPI<{ data: WishlistItem[] }>('/wishlist'),
    add: (productId: number) =>
      fetchAPI<{ message: string }>(`/wishlist/${productId}`, {
        method: 'POST',
      }),
    remove: (productId: number) =>
      fetchAPI<{ message: string }>(`/wishlist/${productId}`, {
        method: 'DELETE',
      }),
    toggle: (productId: number) =>
      fetchAPI<{ in_wishlist: boolean; message: string }>(`/wishlist/${productId}/toggle`, {
        method: 'POST',
      }),
    count: () => fetchAPI<{ count: number }>('/wishlist/count'),
  },
  compare: {
    list: () => fetchAPI<{ products: Product[]; count: number }>('/compare'),
    add: (productId: number) =>
      fetchAPI<{ message: string; count: number }>(`/compare/${productId}`, {
        method: 'POST',
      }),
    remove: (productId: number) =>
      fetchAPI<{ message: string; count: number }>(`/compare/${productId}`, {
        method: 'DELETE',
      }),
    clear: () =>
      fetchAPI<{ message: string }>('/compare', {
        method: 'DELETE',
      }),
    count: () => fetchAPI<{ count: number }>('/compare/count'),
  },
  // Счётчики шапки одним запросом (корзина + избранное + сравнение).
  counters: {
    all: () => fetchAPI<{ cart: number; wishlist: number; compare: number }>('/counters'),
  },
  reviews: {
    list: (productId: number) => fetchAPI<{ data: Review[] }>(`/products/${productId}/reviews`),
    create: (
      productId: number,
      data: { name: string; email?: string; rating: number; comment: string },
    ) =>
      fetchAPI<{ message: string; review: Review }>(`/products/${productId}/reviews`, {
        method: 'POST',
        body: JSON.stringify(data),
      }),
  },
  about: {
    get: () => fetchAPI<{ about: AboutPage }>('/about'),
  },
  stockSettings: {
    get: () =>
      fetchAPI<{
        stock_settings: {
          stock_low_max: number;
          stock_medium_max: number;
          stock_high_max: number;
          show_exact_above: number;
        };
      }>('/stock-settings'),
  },
  interiorIdeas: {
    list: () => fetchAPI<{ data: InteriorIdea[] }>('/interior-ideas'),
  },
  newsletter: {
    subscribe: (email: string) =>
      fetchAPI<{ message: string }>('/newsletter/subscribe', {
        method: 'POST',
        body: JSON.stringify({ email }),
      }),
  },
  auth: {
    login: (email: string, password: string) =>
      fetchAPI<{ user: User }>('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      }),
    register: (data: {
      name: string;
      email: string;
      password: string;
      password_confirmation: string;
      phone?: string;
    }) =>
      fetchAPI<{ user: User; authenticated?: boolean }>('/auth/register', {
        method: 'POST',
        body: JSON.stringify(data),
      }),
    logout: () =>
      fetchAPI<{ message: string }>('/auth/logout', {
        method: 'POST',
      }),
    me: () => fetchAPI<{ user: User }>('/auth/me'),
    updateProfile: (data: { name?: string; phone?: string }) =>
      fetchAPI<{ user: User }>('/auth/profile', {
        method: 'PUT',
        body: JSON.stringify(data),
      }),
    changePassword: (data: {
      current_password: string;
      password: string;
      password_confirmation: string;
    }) =>
      fetchAPI<{ message: string }>('/auth/password', {
        method: 'PUT',
        body: JSON.stringify(data),
      }),
    forgotPassword: (email: string) =>
      fetchAPI<{ message: string }>('/auth/password/forgot', {
        method: 'POST',
        body: JSON.stringify({ email }),
      }),
    resetPassword: (data: {
      email: string;
      token: string;
      password: string;
      password_confirmation: string;
    }) =>
      fetchAPI<{ message: string }>('/auth/password/reset', {
        method: 'POST',
        body: JSON.stringify(data),
      }),
    getNotificationSettings: () =>
      fetchAPI<{ settings: NotificationSettings }>('/auth/notification-settings'),
    updateNotificationSettings: (data: NotificationSettings) =>
      fetchAPI<{ message: string; settings: NotificationSettings }>('/auth/notification-settings', {
        method: 'PUT',
        body: JSON.stringify(data),
      }),
  },
};

/** Единый формат SEO с бэкенда (товар, категория, страница и т.д.) */
export interface SeoMeta {
  title: string | null;
  description: string | null;
  image: string | null;
  /** Необязателен: страницы его не задают, applySeoMeta подставляет текущий адрес */
  canonical_url?: string | null;
  robots: string | null;
  open_graph_title: string | null;
  locale: string | null;
}

export interface Slider {
  id: number;
  title: string;
  description: string | null;
  link: string | null;
  button_text: string | null;
  badge_text: string | null;
  badge_link: string | null;
  badge_icon: string | null;
  image: string | null;
  image_thumb: string | null;
  image_hd: string | null;
  image_fullhd: string | null;
  slug: string;
  full_path: string | null;
  seo: SeoMeta | null;
  /** Поля ниже пришли с разделением главной на блоки — services/backend/docs/HOME_SLIDERS.md */
  placement?: 'top' | 'bottom';
  slot?: 'main' | 'right_top' | 'right_bottom' | null;
  badge_tone?: 'red' | 'yellow' | 'green' | null;
  image_mobile?: string | null;
  image_mobile_thumb?: string | null;
  image_mobile_hd?: string | null;
  image_mobile_fullhd?: string | null;
  priority?: number;
}

/** Ответ `/sliders/home`. Правые карточки — одиночные объекты, а не массивы, и могут отсутствовать */
export interface HomeSliders {
  top: {
    main: Slider[];
    right_top: Slider | null;
    right_bottom: Slider | null;
  };
  bottom: Slider[];
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  image_thumb: string | null;
  image_hd: string | null;
  image_fullhd: string | null;
  icon: string | null;
  seo: SeoMeta | null;
  products_count: number;
  parent_id: number | null;
  children?: Category[];
  parent?: Category;
  full_path: string | null;
  full_path_array?: string[];
  /** Заголовок блока «Популярные» из админки; пусто — «Популярные товары» */
  popular_title?: string | null;
}

export interface WarehouseStockInfo {
  nearest_stock?: number | null;
  nearest_warehouse_name?: string | null;
  stocks?: Array<{ warehouse_id: number; warehouse_name: string; quantity: number }>;
}
export interface Product extends WarehouseStockInfo {
  is_visible_in_region?: boolean;
  id: number;
  name: string;
  slug: string;
  sku: string;
  price: number;
  old_price: number | null;
  discount_percent: number | null;
  image: string | null;
  thumbnail: string | null;
  in_stock: boolean;
  stock: number;
  stock_label?: string | null;
  backorder?: boolean;
  /** false — остаток бэку неизвестен, число не показываем */
  stock_known?: boolean;
  /** Можно ли заказать по складам региона — решает бэк, флаг backorder сам по себе нет */
  available_to_order?: boolean;
  rating: number;
  reviews_count: number;
  is_variable?: boolean;
  is_variant?: boolean;
  is_sellable_bundle?: boolean;
  /** Простой комплект: `{id, sku, name, quantity}`; вариативный — состав выбранного предложения с ценами */
  sellable_bundle_components?: BundleComponent[];
  first_available_variant_id?: number | null;
  variation_attributes?: VariationAttributeOption[];
  colors?: ColorOption[];
  specifications?: Record<string, any> | null;
  category: {
    id: number;
    name: string;
    slug: string;
  } | null;
  categories?: {
    id: number;
    name: string;
    slug: string;
  }[];
  //excerpt: string | null;
  delivery_days?: number | null;
  seo?: SeoMeta | null;
}

export interface VariationAttributeValue {
  id?: string | null;
  slug?: string | null;
  name?: string | null;
  code?: string | null;
  count?: number;
}

export interface VariationAttributeOption {
  attribute_slug: string;
  attribute_name?: string | null;
  type?: string | null;
  values?: VariationAttributeValue[];
}

export interface SelectedVariationItem {
  attribute_slug: string;
  attribute_name?: string | null;
  value_slug: string;
  value_name?: string | null;
}

export interface ProductVariant extends WarehouseStockInfo {
  id: number;
  sku: string;
  /** У предложения своя страница: каталог ведёт на неё (market-docs/catalog-individual-sku-api.md) */
  slug?: string | null;
  price: number;
  old_price?: number | null;
  stock: number;
  stock_label?: string | null;
  in_stock: boolean;
  /** false — остаток бэку неизвестен, число не показываем */
  stock_known?: boolean;
  available_to_order?: boolean;
  color?: { name: string; slug: string; code?: string | null } | null;
  colors?: { id: number; value: string; slug: string; color_code: string | null }[];
  size?: { name: string; slug: string; value: string } | null;
  images: string[];
  variation_attributes?: SelectedVariationItem[];
  description?: string | null;
  excerpt?: string | null;
  specifications?: { name: string; value: string; slug: string }[] | Record<string, string> | null;
  backorder?: string | null;
  /** Предложение комплекта: false — состав не настроен, цена 0, продать нельзя */
  is_bundle_configured?: boolean;
  bundle_components?: BundleComponent[];
}

/** Реальный SKU в составе комплекта (market-docs/66) */
export interface BundleComponent {
  id?: number;
  product_id?: number;
  sku: string;
  name: string;
  quantity: number;
  unit_price?: number;
  total?: number;
}

export interface ProductFeatureBlock {
  id: number;
  title: string;
  subtitle?: string | null;
  icon?: string | null;
  icon_image?: string | null;
  icon_color: string;
  bg_color: string;
}

export interface ProductDeliveryBlock {
  id: number;
  title: string;
  description?: string | null;
  icon?: string | null;
  icon_image?: string | null;
  icon_color: string;
  bg_color: string;
}

export interface WarehouseStock {
  warehouse_id: number;
  warehouse_name: string;
  quantity: number;
  distance_km: number | null;
}

export interface ProductDocument {
  id: number;
  title: string;
  url: string;
  file_name: string;
  mime_type: string;
  extension: string;
  size: number;
}

export interface ProductDetail extends Product {
  images: string[];
  /** false — остаток бэку неизвестен, число не показываем */
  stock_known?: boolean;
  /** destination — остаток для региона покупателя, global — по всем складам */
  stock_scope?: 'destination' | 'global';
  available_to_order?: boolean;
  nearest_stock?: number | null;
  nearest_warehouse_name?: string | null;
  nearest_distance_km?: number | null;
  stocks?: WarehouseStock[];
  documents?: ProductDocument[];
  image_hd?: string | null;
  colors?: ColorOption[];
  sizes?: SizeOption[];
  selected_color?: ColorOption | null;
  selected_size?: SizeOption | null;
  parent_id?: number | null;
  description: string | null;
  stock_label?: string | null;
  specifications: Record<string, any> | null;
  related_products?: Product[];
  backorder?: boolean;
  is_variable?: boolean;
  is_variant?: boolean;
  variation_attributes?: VariationAttributeOption[];
  variants?: ProductVariant[]; // ВАЖНО: Все вариации для сравнения на фронтенде
  selected_variation?: SelectedVariationItem[];
  delivery_days?: number | null;
  feature_blocks?: ProductFeatureBlock[];
  delivery_blocks?: ProductDeliveryBlock[];
}

export interface VariantInfo {
  available_sizes_for_color: Array<{ id: number; name: string; slug: string; value: string }>;
  available_colors_for_size: Array<{ id: number; name: string; slug: string; code: string | null }>;
}

export interface ProductPagePayload {
  product: ProductDetail;
  variant_info?: VariantInfo | null;
  bundle?: { data: Product[] };
  related?: { data: Product[] };
}

export interface ColorOption {
  id?: number | null;
  name?: string | null;
  slug?: string | null;
  code?: string | null;
  /** Количество товаров с этим цветом в категории; 0 — опция недоступна (показать серой) */
  count?: number;
}

export interface SizeOption {
  id?: number | null;
  name?: string | null;
  slug?: string | null;
  value?: string | null;
  /** Количество товаров с этим размером в категории; 0 — опция недоступна (показать серой) */
  count?: number;
}

export interface VariantInfo {
  available_sizes_for_color: Array<{ id: number; name: string; slug: string; value: string }>;
  available_colors_for_size: Array<{ id: number; name: string; slug: string; code: string | null }>;
}

export interface FiltersMeta {
  price: { min: number; max: number };
  colors: ColorOption[];
  sizes: SizeOption[];
  attributes: {
    id: number;
    name: string;
    slug: string;
    values: {
      id: number;
      name: string;
      slug: string;
      code?: string | null;
      /** Количество товаров с этим значением в категории; 0 — опция недоступна (показать серой) */
      count?: number;
    }[];
  }[];
  manufacturers?: { id: number; name: string; slug: string; count?: number }[];
  /** Миллиметры; нет данных — null (market-docs/44, ответ 3 от 14.10); читается в toolbar/availability */
  dimensions?: Partial<
    Record<DimensionKey, { min: number | null; max: number | null; unit?: 'mm' }>
  >;
  /** Есть ли товары по акции без учёта самого «По акции»; has_on_sale — старое имя */
  has_sale?: boolean;
}

export interface ProductListResponse {
  data: Product[];
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    filters?: FiltersMeta;
  };
}

export interface ProductListParams {
  category_id?: number;
  category_slug?: string;
  room_slug?: string;
  price_min?: number;
  price_max?: number;
  search?: string;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
  colors?: string[];
  sizes?: string[];
  attributes?: Record<string, string[]>;
  region_id?: number;
  /** Пункт справочника адресов — покрытие складов и «по расстоянию» */
  directory_external_id?: string;
  manufacturer_slug?: string;
  /** Ниже — ждут бэкенд (market-docs/44, вопросы 1–3, 6) */
  manufacturers?: string[];
  category_slugs?: string[];
  on_sale?: boolean;
  width_min?: number;
  width_max?: number;
  depth_min?: number;
  depth_max?: number;
  height_min?: number;
  height_max?: number;
}

export interface Article {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  content: string | null;
  image: string | null;
  published_at: string | null;
  category: {
    id: number;
    name: string;
    slug: string;
  } | null;
  author: {
    id: number;
    name: string;
  } | null;
  full_path?: string | null;
  seo?: SeoMeta | null;
}

/** `GET /contact` — настройки «Контакты» из админки; поля, которые не заполнены, — пустые массивы и null */
export interface ContactInfo {
  company_name: string | null;
  address: string | null;
  phones: { phone?: string | null }[];
  emails: { email?: string | null }[];
  social_networks: { name?: string | null; url?: string | null }[];
  map_embed: string | null;
  working_hours: { day?: string | null; hours?: string | null }[];
}

export interface Store {
  id: number;
  name: string;
  slug: string;
  address: string;
  city: string;
  phone: string;
  hours: string | null;
  coordinates: string | null;
  latitude: number | null;
  longitude: number | null;
  coordinates_array: {
    lat: number;
    lng: number;
  } | null;
  yandex_map: string | null;
  description: string | null;
  image: string | null;
  image_thumb: string | null;
  image_hd: string | null;
  image_fullhd: string | null;
  full_path: string | null;
  seo: SeoMeta | null;
}

export interface CartItem {
  price_source?: 'private_link' | 'catalog';
  id: number;
  product_id: number;
  name: string;
  slug: string | null; // Slug товара для ссылки
  price: number;
  quantity: number;
  total: number;
  image: string | null;
  sku: string | null;
  is_variant?: boolean;
  variation_attributes?: SelectedVariationItem[];
  color?: {
    name: string;
    slug: string;
    code?: string | null;
  } | null;
  size?: {
    name: string;
    slug: string;
    value?: string | null;
  } | null;
}

export interface Cart {
  items: CartItem[];
  subtotal: number;
  item_count: number;
  is_empty: boolean;
}

export interface WishlistItem {
  id: number;
  product_id: number;
  product: Product;
  created_at: string;
}

export interface Review {
  id: number;
  name: string;
  email?: string | null;
  rating: number;
  comment: string;
  created_at: string;
  is_approved?: boolean;
}

export interface TeamMember {
  id: number;
  name: string;
  position: string;
  image?: string | null;
  image_thumb?: string | null;
  image_hd?: string | null;
  image_fullhd?: string | null;
  priority?: number;
}

export interface Advantage {
  id: number;
  title: string;
  description?: string | null;
  icon?: string | null;
  color?: string | null;
  priority?: number;
}

export interface AboutPage {
  id: number;
  hero_title?: string | null;
  hero_description?: string | null;
  hero_image?: string | null;
  hero_image_thumb?: string | null;
  hero_image_hd?: string | null;
  hero_image_fullhd?: string | null;
  story_title?: string | null;
  story_content?: string | null;
  story_images?: Array<{
    url: string;
    thumb?: string;
    hd?: string;
    fullhd?: string;
  }>;
  statistics?: {
    years?: number;
    stores?: number;
    clients?: number;
    products?: number;
  } | null;
  team_members?: TeamMember[];
  advantages?: Advantage[];
  full_path?: string | null;
  seo?: SeoMeta | null;
}

export interface InteriorIdeaHotspot {
  id: number;
  x: number;
  y: number;
  product: {
    id: number;
    name: string;
    price: number;
    image: string | null;
    image_thumb: string | null;
    slug: string;
    full_path: string | null;
    is_variable?: boolean;
    is_variant?: boolean;
    in_stock?: boolean;
    first_available_variant_id?: number | null;
    thumbnail?: string | null;
    sku?: string;
  } | null;
}

export interface InteriorIdea {
  id: number;
  title?: string | null;
  image?: string | null;
  image_thumb?: string | null;
  image_main?: string | null;
  hotspots?: InteriorIdeaHotspot[];
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
}

export interface NotificationSettings {
  email_promotions: boolean;
  sms_order_notifications: boolean;
  push_notifications: boolean;
  new_product_notifications: boolean;
}

export interface Order {
  fulfillment?: { warehouse_id?: number; store_id?: number | null; name?: string; warehouse_name?: string; address?: string | null; address_directory?: { label?: string | null } | null } | null;
  id: number;
  number: string;
  status: string;
  status_label: string;
  subtotal: number;
  bundle_snapshot?: {
    bundle_id: number;
    primary_sku_id: number;
    bundle_quantity: number;
    components: Array<{ product_id: number; quantity: number; quantity_per_bundle: number; role: string; unit_price: number }>;
  } | null;
  total: number;
  delivery_cost: number;
  assembly_cost: number;
  payment_method: string;
  payment_method_label?: string | null;
  delivery_type: string;
  delivery_date?: string | null;
  delivery_time?: string | null;
  comment?: string | null;
  contact_name: string;
  contact_phone: string;
  contact_email: string;
  address?: Address | null;
  shipping_location?: ShippingLocation | null;
  shipping_method?: ShippingMethod | null;
  delivery_handling_type?: DeliveryHandlingType | null;
  delivery_floor?: number | null;
  requires_assembly: boolean;
  additional_services?: AdditionalService[];
  items?: OrderItem[];
  status_history?: OrderStatusHistory[];
  created_at: string;
  can_be_cancelled: boolean;
  can_pay?: boolean;
  cooling_until?: string | null;
  server_time?: string;
  payment_status?: 'paid' | 'unpaid';
  can_request_cancellation?: boolean;
  cancellation_request?: { status: string; reason: string } | null;
  refund_task?: { status: string; amount: number } | null;
  payment_deadline_at?: string | null;
  payment_seconds_left?: number | null;
  cancelled_due_to_unpaid_timeout?: boolean;
}

export interface PickupOption { warehouse_id: number; store_id: number | null; name: string; address: string | null }
export interface WarehouseOption {
  warehouse_id: number; warehouse_name: string; delivery_cost: number; assembly_cost: number;
  total_fulfillment_cost: number; service_options: {
    assembly: { available: boolean; required: boolean; price: number | null };
    handling: Array<{ id: number; name: string; requires_floor: boolean; max_floor?: number }>;
    additional: Array<{ id: number; name: string; price: number }>;
  };
}
export interface WarehouseQuote { available: boolean; subtotal: number; selected: WarehouseOption | null; options: WarehouseOption[] }

export interface OrderItem {
  id: number;
  product_id: number;
  product?: Product | null;
  quantity: number;
  price: number;
  total: number;
}

export interface ShippingLocation {
  id: number;
  name: string;
  slug?: string;
  type: string;
  parent_id?: number | null;
  code?: string;
  location_type?: string;
  postal_code?: string | null;
  pickup_enabled?: boolean | null;
  effective_pickup_enabled?: boolean;
  /** Текст для блока самовывоза на checkout */
  pickup_notice?: string | null;
}

export interface ShippingLocationTree extends ShippingLocation {
  children?: ShippingLocationTree[];
}

export interface ShippingMethod {
  id: number;
  name: string;
  base_price?: number;
  price?: number;
  free_delivery_threshold?: number | null;
  delivery_days_min?: number | null;
  delivery_days_max?: number | null;
  /** sort_order с бэкенда: меньше — выше в списке */
  priority?: number;
  /** carrier | location_fallback */
  source?: string;
  carrier?: {
    id: number;
    name: string;
    code?: string;
  } | null;
}

export interface DeliveryHandlingType {
  id: number;
  name: string;
  slug: string;
  code: string;
  description?: string;
  requires_floor: boolean;
  max_floor?: number;
  requires_elevator: boolean;
  base_price?: number;
  example_prices?: Record<number, number>;
}

export interface AdditionalService {
  id: number;
  name: string;
  code: string;
  description?: string;
  icon?: string;
  price_type: 'fixed' | 'from' | 'custom';
  price?: number;
  base_price?: number;
}

export interface OrderStatusHistory {
  id: number;
  status: string;
  status_label: string;
  comment?: string | null;
  user?: User | null;
  created_at: string;
}

export interface PaymentMethod {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  icon?: string | null;
  is_active: boolean;
  is_coming_soon?: boolean;
  selectable?: boolean;
  availability?: 'available' | 'coming_soon';
  availability_label?: string | null;
  sort_order: number;
}

export interface Address {
  directory_external_id?: string | null;
  id: number;
  title?: string | null;
  city?: string | null;
  street?: string | null;
  house?: string | null;
  apartment?: string | null;
  entrance?: string | null;
  is_default: boolean;
  full_address?: string | null;
  shipping_location_id?: number | null;
  shipping_location?: {
    id: number;
    name: string;
  } | null;
  created_at?: string;
  updated_at?: string;
}

export interface AddressData {
  directory_external_id?: string;
  title?: string;
  address?: string;
  city?: string;
  street?: string;
  house?: string;
  apartment?: string;
  entrance?: string;
  postal_code?: string;
  shipping_location_id?: number;
}

export interface BonusTransaction {
  id: number;
  type: string;
  amount: number;
  description: string;
  created_at: string;
}

export interface Chat {
  id: number;
  order_id?: number | null;
  order_number?: string | null;
  product_name?: string | null;
  last_message?: string | null;
  last_message_at?: string | null;
  unread_count?: number;
  created_at: string;
}

export interface ChatMessage {
  id: number;
  message: string;
  created_at: string;
}
