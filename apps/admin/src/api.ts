export class AuthRequiredError extends Error {
  constructor() {
    super('Требуется вход в административную панель')
  }
}

type RequestOptions = RequestInit & {
  csrfToken?: string | null
}

async function request<T>(url: string, options: RequestOptions = {}): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')

  if (options.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  if (options.csrfToken) {
    headers.set('X-CSRF-TOKEN', options.csrfToken)
  }

  const response = await fetch(url, {
    ...options,
    headers,
    credentials: 'same-origin',
    redirect: 'follow',
  })

  const contentType = response.headers.get('content-type') ?? ''

  if (!contentType.includes('application/json')) {
    if (response.url.includes('/admin_sv/login') || response.status === 401) {
      throw new AuthRequiredError()
    }

    const text = await response.text()
    throw new Error(text || `Backend вернул неожиданный ответ (${response.status})`)
  }

  const payload = await response.json()

  if (!response.ok) {
    const message =
      payload?.message
      ?? Object.values(payload?.errors ?? {}).flat().join('\n')
      ?? `Ошибка backend (${response.status})`

    throw new Error(String(message))
  }

  return payload as T
}

export type SessionInfo = {
  user: {
    id: number
    name: string
    email: string
  }
  csrf_token: string
  permissions: Record<string, {
    view: boolean
    update: boolean
  }>
}

export type CategoryNode = {
  id: number
  name: string
  slug: string
  parent_id: number | null
  products_count?: number
  children?: CategoryNode[]
}

export type ProductSummary = {
  id: number
  name: string
  slug: string
  sku: string
  gtin: string | null
  state: string
  price: number
  original_price: number | null
  stock: number
  variants_count: number
  categories: Array<{
    id: number
    name: string
    slug: string
  }>
}

export type ProductDetails = ProductSummary & {
  description: string | null
  priority: number
  is_variable: boolean
  attributes: Array<{
    id: number
    name: string
    slug: string
    value_id: number | null
    value: string | null
    custom_value: string | null
    is_multiple: boolean
    is_use_in_variations: boolean
  }>
  variation_attribute_ids: number[]
  variants: Array<{
    id: number
    name: string
    sku: string
    state: string
    price: number
    stock: number
  }>
  warehouse_stocks: Array<{
    warehouse_id: number
    warehouse_name: string | null
    quantity: number
  }>
}

export type AttributeDefinition = {
  id: number
  name: string
  slug: string
  type: string
  is_filterable: boolean
  is_required: boolean
  is_use_in_variations: boolean
  allow_custom_value: boolean
  is_multiple: boolean
  sort_order: number
  products_count: number
  values: Array<{
    id: number
    value: string
    slug: string
    sort_order: number
  }>
}

export type AdminOrder = {
  id: number
  number: string
  status: string
  status_label: string
  total: number
  contact_name: string | null
  contact_phone: string | null
  contact_email: string | null
  created_at: string | null
  shipping_location: {
    id: number
    name: string
  } | null
  shipping_method: {
    id: number
    name: string
  } | null
  items: Array<{
    id: number
    product_id: number | null
    name: string
    quantity: number
    price: number
    total: number
  }>
}

export type StoreRecord = {
  id: number
  name: string
  slug: string
  address: string | null
  city: string | null
  phone: string | null
  hours: string | null
  coordinates: string | null
  is_active: boolean
  priority: number
  region: {
    id: number
    name: string
  } | null
}

export type WarehouseRecord = {
  id: number
  external_id: string
  name: string
  is_active: boolean
  product_stocks_count: number
  shipping_locations_count: number
  shipping_locations: Array<{
    id: number
    name: string
  }>
}

export type LocationRecord = {
  id: number
  parent_id: number | null
  name: string
  slug: string
  code: string | null
  type: string
  location_type: string | null
  is_active: boolean
  delivery_price: number | null
  free_delivery_threshold: number | null
  delivery_days_min: number | null
  delivery_days_max: number | null
  parent: {
    id: number
    name: string
  } | null
}

export const backendApi = {
  session: () => request<SessionInfo>('/admin_sv/api/session'),

  categoryTree: () => request<{ tree: CategoryNode[] }>('/api/v1/categories/tree'),
  roomTree: () => request<{ tree: CategoryNode[] }>('/api/v1/rooms/tree'),

  products: (params: {
    search?: string
    categoryId?: number | null
    roomId?: number | null
    page?: number
  } = {}) => {
    const query = new URLSearchParams()

    if (params.search) query.set('search', params.search)
    if (params.categoryId) query.set('category_id', String(params.categoryId))
    if (params.roomId) query.set('room_id', String(params.roomId))
    if (params.page) query.set('page', String(params.page))

    const suffix = query.toString() ? `?${query.toString()}` : ''

    return request<{
      data: ProductSummary[]
      meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
      }
    }>(`/admin_sv/api/products${suffix}`)
  },


  product: (id: number) =>
    request<{ product: ProductDetails }>(`/admin_sv/api/products/${id}`),

  updateProduct: (
    id: number,
    data: {
      name: string
      sku: string
      gtin: string | null
      description: string | null
      state: string
      priority: number
      price: number
      original_price: number | null
    },
    csrfToken: string,
  ) =>
    request<{ message: string; product: ProductDetails }>(
      `/admin_sv/api/products/${id}`,
      {
        method: 'PUT',
        body: JSON.stringify(data),
        csrfToken,
      },
    ),

  attributes: () =>
    request<{ data: AttributeDefinition[] }>('/admin_sv/api/attributes'),

  updateAttribute: (
    id: number,
    data: Omit<AttributeDefinition, 'id' | 'products_count' | 'values'>,
    csrfToken: string,
  ) =>
    request<{ message: string; attribute: AttributeDefinition }>(
      `/admin_sv/api/attributes/${id}`,
      {
        method: 'PUT',
        body: JSON.stringify(data),
        csrfToken,
      },
    ),

  orders: () =>
    request<{ data: AdminOrder[] }>('/admin_sv/api/orders'),

  stores: () =>
    request<{ data: StoreRecord[] }>('/admin_sv/api/stores'),

  locations: () =>
    request<{ data: LocationRecord[] }>('/admin_sv/api/locations'),

  warehouses: () =>
    request<{ data: WarehouseRecord[] }>('/admin_sv/api/warehouses'),
}
