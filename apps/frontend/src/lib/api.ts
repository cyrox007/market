const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1';

async function fetchAPI<T>(endpoint: string, options?: RequestInit): Promise<T> {
	// Убеждаемся, что endpoint начинается с /, а API_BASE_URL не заканчивается на /
	const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
	const cleanBaseUrl = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL;
	const url = endpoint.startsWith('http') ? endpoint : `${cleanBaseUrl}${cleanEndpoint}`;

	const response = await fetch(url, {
		...options,
		headers: {
			'Content-Type': 'application/json',
			'Accept': 'application/json',
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
		error.data = errorData;
		throw error;
	}

	return response.json();
}

export const api = {
	shipping: {
		getLocations: (params?: { type?: string; parent_id?: number; search?: string }) => {
			const query = new URLSearchParams();
			if (params?.type) query.append('type', params.type);
			if (params?.parent_id) query.append('parent_id', params.parent_id.toString());
			if (params?.search) query.append('search', params.search);
			return fetchAPI(`/shipping/locations?${query.toString()}`);
		},
		getLocationTree: (params?: { parent_id?: number; max_depth?: number }) => {
			const query = new URLSearchParams();
			if (params?.parent_id) query.append('parent_id', params.parent_id.toString());
			if (params?.max_depth) query.append('max_depth', params.max_depth.toString());
			return fetchAPI(`/shipping/locations/tree?${query.toString()}`);
		},
		getLocationInfo: (locationId: number) => fetchAPI(`/shipping/locations/${locationId}`),
		getDeliveryHandlingTypes: (params?: { location_id?: number }) => {
			const query = new URLSearchParams();
			if (params?.location_id) query.append('location_id', params.location_id.toString());
			return fetchAPI(`/shipping/delivery-handling-types?${query.toString()}`);
		},
		calculateShipping: (data: {
			location_id: number;
			order_amount?: number;
			delivery_handling_type_id?: number;
			floor?: number;
			order_weight?: number;
			order_volume?: number;
			requires_assembly?: boolean;
		}) => fetchAPI('/shipping/calculate', {
			method: 'POST',
			body: JSON.stringify(data),
		}),
		getCarriers: () => fetchAPI('/shipping/carriers'),
		getShippingMethods: (params: { location_id: number; order_amount?: number }) => {
			const query = new URLSearchParams();
			query.append('location_id', params.location_id.toString());
			if (params.order_amount) query.append('order_amount', params.order_amount.toString());
			return fetchAPI(`/shipping/shipping-methods?${query.toString()}`);
		},
		calculateShippingMethod: (data: {
			shipping_method_id: number;
			location_id: number;
			order_amount?: number;
			delivery_handling_type_id?: number;
			floor?: number;
		}) => fetchAPI('/shipping/shipping-methods/calculate', {
			method: 'POST',
			body: JSON.stringify(data),
		}),
		getAdditionalServices: (params: { location_id: number }) => {
			const query = new URLSearchParams();
			query.append('location_id', params.location_id.toString());
			return fetchAPI<{ data: AdditionalService[] }>(`/shipping/additional-services?${query.toString()}`);
		},
	},
	cart: {
		get: (params?: { region_id?: number }) => {
			const query = new URLSearchParams();
			if (params?.region_id) query.append('region_id', params.region_id.toString());
			return fetchAPI<Cart>(`/cart?${query.toString()}`);
		},
		add: (data: { product_id: number; quantity?: number; color?: string; size?: string }) => fetchAPI<{ item: CartItem; message: string; requested_quantity: number; added_quantity: number; was_adjusted: boolean }>('/cart', {
			method: 'POST',
			body: JSON.stringify(data),
		}),
		update: (itemId: number, data: { quantity: number; color?: string; size?: string }) => fetchAPI<{ item: CartItem; message: string; requested_quantity: number; updated_quantity: number; was_adjusted: boolean }>(`/cart/${itemId}`, {
			method: 'PUT',
			body: JSON.stringify(data),
		}),
		remove: (itemId: number) => fetchAPI<{ message: string }>(`/cart/${itemId}`, {
			method: 'DELETE',
		}),
		clear: () => fetchAPI<{ message: string }>('/cart', {
			method: 'DELETE',
		}),
		count: () => fetchAPI<{ count: number }>('/cart/count'),
	},
	orders: {
		list: () => fetchAPI<{ data: Order[] }>('/orders'),
		get: (id: number) => fetchAPI<{ order: Order }>(`/orders/${id}`),
		create: (data: any) => fetchAPI('/orders', {
			method: 'POST',
			body: JSON.stringify(data),
		}),
		getPaymentConfig: (id: number) =>
			fetchAPI<{
				amount: number;
				order_number: string;
				success_url: string;
				fail_url: string;
				gateway_client_config:
				| { publicId: string; url: string; useSdk?: boolean }
				| { payformUrl: string; useEcomApi: true };
			}>(`/orders/${id}/payment-config`),
		cancel: (id: number, data?: { comment?: string }) => fetchAPI<{ order: Order; message: string }>(`/orders/${id}/cancel`, {
			method: 'POST',
			body: data ? JSON.stringify(data) : undefined,
		}),
		repeat: (id: number) => fetchAPI<{
			message: string;
			added_items: Array<{ product_id: number; product_name: string; quantity: number; was_adjusted: boolean }>;
			skipped_items: Array<{ product_id: number; product_name?: string; requested_quantity: number; reason: string }>;
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
		create: (data: AddressData) => fetchAPI<{ address: Address }>('/addresses', {
			method: 'POST',
			body: JSON.stringify(data),
		}),
		update: (id: number, data: AddressData) => fetchAPI<{ address: Address }>(`/addresses/${id}`, {
			method: 'PUT',
			body: JSON.stringify(data),
		}),
		delete: (id: number) => fetchAPI<{ message: string }>(`/addresses/${id}`, {
			method: 'DELETE',
		}),
		setDefault: (id: number) => fetchAPI<{ message: string }>(`/addresses/${id}/set-default`, {
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
		sendMessage: (id: number, message: string) => fetchAPI<{ message: ChatMessage }>(`/chats/${id}/messages`, {
			method: 'POST',
			body: JSON.stringify({ message }),
		}),
		createOrGet: (orderId: number) => fetchAPI<{ chat: Chat }>(`/chats/orders/${orderId}/create`, {
			method: 'POST',
		}),
	},
	sliders: {
		list: () => fetchAPI<{ data: Slider[] }>('/sliders'),
		get: (slug: string) => fetchAPI<{ slider: Slider }>(`/sliders/${slug}`),
	},
	categories: {
		list: () => fetchAPI<{ data: Category[] }>('/categories'),
		get: (slug: string) => fetchAPI<{ category: Category }>(`/categories/${slug}`),
		tree: () => fetchAPI<{ tree: Category[] }>('/categories/tree'),
	},
	products: {
		list: (params?: ProductListParams) => {
			const query = new URLSearchParams();
			if (params?.category_id) query.append('category_id', params.category_id.toString());
			if (params?.category_slug) query.append('category_slug', params.category_slug);
			if (params?.price_min !== undefined) query.append('price_min', params.price_min.toString());
			if (params?.price_max !== undefined) query.append('price_max', params.price_max.toString());
			if (params?.search) query.append('search', params.search);
			// Явно передаём сортировку — бэкенд и кэш используют sort_by/sort_order
			if (params?.sort_by != null) query.append('sort_by', params.sort_by);
			if (params?.sort_order != null) query.append('sort_order', params.sort_order);
			if (params?.per_page) query.append('per_page', params.per_page.toString());
			if (params?.page) query.append('page', params.page.toString());

			// Цвета (передаем как строку через запятую для совместимости)
			if (params?.colors && params.colors.length) {
				query.append('colors', params.colors.join(','));
			}

			// Размеры
			if (params?.sizes && params.sizes.length) {
				params.sizes.forEach((size) => query.append('sizes[]', size));
			}

			// Характеристики — передаём только при непустом наборе (иначе каталог без фильтра = все товары)
			if (params?.attributes && Object.keys(params.attributes).length > 0) {
				Object.entries(params.attributes).forEach(([attrSlug, values]) => {
					if (values?.length) {
						values.forEach((val) => query.append(`attributes[${attrSlug}][]`, val));
					}
				});
			}

			// Регион для применения правил
			if (params?.region_id) {
				query.append('region_id', params.region_id.toString());
			}

			return fetchAPI<ProductListResponse>(`/products?${query.toString()}`);
		},
		get: (slug: string, params?: { color?: string; size?: string; region_id?: number }) => {
			const query = new URLSearchParams();
			if (params?.color) query.append('color', params.color);
			if (params?.size) query.append('size', params.size);
			if (params?.region_id) query.append('region_id', params.region_id.toString());
			const suffix = query.toString() ? `?${query.toString()}` : '';
			return fetchAPI<ProductPagePayload>(`/products/${slug}${suffix}`);
		},
		featured: () => fetchAPI<{ data: Product[] }>('/products/featured'),
		new: () => fetchAPI<{ data: Product[] }>('/products/new'),
		sale: () => fetchAPI<{ data: Product[] }>('/products/sale'),
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
		search: (query: string, params?: { per_page?: number; page?: number; sort_by?: string; sort_order?: 'asc' | 'desc'; region_id?: number }) => {
			const searchParams = new URLSearchParams();
			searchParams.append('q', query);
			if (params?.per_page) searchParams.append('per_page', params.per_page.toString());
			if (params?.page) searchParams.append('page', params.page.toString());
			if (params?.sort_by) searchParams.append('sort_by', params.sort_by);
			if (params?.sort_order) searchParams.append('sort_order', params.sort_order);
			if (params?.region_id) searchParams.append('region_id', params.region_id.toString());
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
			return fetchAPI<{ data: Article[]; current_page: number; last_page: number; per_page: number; total: number }>(`/articles?${query.toString()}`);
		},
		get: (slug: string) => fetchAPI<{ article: Article }>(`/articles/${slug}`),
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
	regions: {
		list: () => fetchAPI<{ data: ShippingLocation[] }>('/regions'),
		tree: () => fetchAPI<{ data: ShippingLocationTree[] }>('/regions/tree'),
		detect: (params?: { city?: string }) => {
			const query = new URLSearchParams();
			if (params?.city) query.append('city', params.city);
			// IP определяется автоматически на бэкенде из запроса
			return fetchAPI<{ region: ShippingLocation | null; source: string }>(`/regions/detect?${query.toString()}`);
		},
	},
	wishlist: {
		list: () => fetchAPI<{ data: WishlistItem[] }>('/wishlist'),
		add: (productId: number) => fetchAPI<{ message: string }>(`/wishlist/${productId}`, {
			method: 'POST',
		}),
		remove: (productId: number) => fetchAPI<{ message: string }>(`/wishlist/${productId}`, {
			method: 'DELETE',
		}),
		toggle: (productId: number) => fetchAPI<{ in_wishlist: boolean; message: string }>(`/wishlist/${productId}/toggle`, {
			method: 'POST',
		}),
		count: () => fetchAPI<{ count: number }>('/wishlist/count'),
	},
	compare: {
		list: () => fetchAPI<{ products: Product[]; count: number }>('/compare'),
		add: (productId: number) => fetchAPI<{ message: string; count: number }>(`/compare/${productId}`, {
			method: 'POST',
		}),
		remove: (productId: number) => fetchAPI<{ message: string; count: number }>(`/compare/${productId}`, {
			method: 'DELETE',
		}),
		clear: () => fetchAPI<{ message: string }>('/compare', {
			method: 'DELETE',
		}),
		count: () => fetchAPI<{ count: number }>('/compare/count'),
	},
	reviews: {
		list: (productId: number) => fetchAPI<{ data: Review[] }>(`/products/${productId}/reviews`),
		create: (productId: number, data: { name: string; email?: string; rating: number; comment: string }) => fetchAPI<{ message: string; review: Review }>(`/products/${productId}/reviews`, {
			method: 'POST',
			body: JSON.stringify(data),
		}),
	},
	about: {
		get: () => fetchAPI<{ about: AboutPage }>('/about'),
	},
	stockSettings: {
		get: () => fetchAPI<{ stock_settings: { stock_low_max: number; stock_medium_max: number; stock_high_max: number; show_exact_above: number } }>('/stock-settings'),
	},
	interiorIdeas: {
		list: () => fetchAPI<{ data: InteriorIdea[] }>('/interior-ideas'),
	},
	newsletter: {
		subscribe: (email: string) => fetchAPI<{ message: string }>('/newsletter/subscribe', {
			method: 'POST',
			body: JSON.stringify({ email }),
		}),
	},
	auth: {
		login: (email: string, password: string) => fetchAPI<{ user: User }>('/auth/login', {
			method: 'POST',
			body: JSON.stringify({ email, password }),
		}),
		register: (data: { name: string; email: string; password: string; password_confirmation: string; phone?: string }) => fetchAPI<{ user: User }>('/auth/register', {
			method: 'POST',
			body: JSON.stringify(data),
		}),
		logout: () => fetchAPI<{ message: string }>('/auth/logout', {
			method: 'POST',
		}),
		me: () => fetchAPI<{ user: User }>('/auth/me'),
		updateProfile: (data: { name?: string; phone?: string }) => fetchAPI<{ user: User }>('/auth/profile', {
			method: 'PUT',
			body: JSON.stringify(data),
		}),
		changePassword: (data: { current_password: string; password: string; password_confirmation: string }) => fetchAPI<{ message: string }>('/auth/password', {
			method: 'PUT',
			body: JSON.stringify(data),
		}),
		forgotPassword: (email: string) =>
			fetchAPI<{ message: string }>('/auth/password/forgot', {
				method: 'POST',
				body: JSON.stringify({ email }),
			}),
		resetPassword: (data: { email: string; token: string; password: string; password_confirmation: string }) =>
			fetchAPI<{ message: string }>('/auth/password/reset', {
				method: 'POST',
				body: JSON.stringify(data),
			}),
		getNotificationSettings: () => fetchAPI<{ settings: NotificationSettings }>('/auth/notification-settings'),
		updateNotificationSettings: (data: NotificationSettings) => fetchAPI<{ message: string; settings: NotificationSettings }>('/auth/notification-settings', {
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
	canonical_url: string | null;
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
}

export interface Product {
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
	rating: number;
	reviews_count: number;
	is_variable?: boolean;
	is_variant?: boolean;
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
	slug?: string | null;
	name?: string | null;
	code?: string | null;
	count?: number;
}

export interface VariationAttributeOption {
	attribute_slug: string;
	attribute_name?: string | null;
	values?: VariationAttributeValue[];
}

export interface SelectedVariationItem {
	attribute_slug: string;
	attribute_name?: string | null;
	value_slug: string;
	value_name?: string | null;
}

export interface ProductVariant {
	id: number;
	sku: string;
	price: number;
	old_price?: number | null;
	stock: number;
	stock_label?: string | null;
	in_stock: boolean;
	color?: { name: string; slug: string; code?: string | null } | null;
	size?: { name: string; slug: string; value: string } | null;
	images: string[];
	variation_attributes?: SelectedVariationItem[];
	description?: string | null;
	excerpt?: string | null;
	specifications?: { name: string; value: string; slug: string }[] | Record<string, string> | null;
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

export interface ProductDetail extends Product {
	images: string[];
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
}

export interface ProductListResponse {
	data: Product[];
	current_page: number;
	last_page: number;
	per_page: number;
	total: number;
	meta?: {
		filters?: FiltersMeta;
	};
}

export interface ProductListParams {
	category_id?: number;
	category_slug?: string;
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
	id: number;
	number: string;
	status: string;
	status_label: string;
	subtotal: number;
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
	payment_deadline_at?: string | null;
	payment_seconds_left?: number | null;
	cancelled_due_to_unpaid_timeout?: boolean;
}

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
	sort_order: number;
}

export interface Address {
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
