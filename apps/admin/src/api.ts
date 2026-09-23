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

  if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
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

  if (response.status === 401) {
    throw new AuthRequiredError()
  }

  const contentType = response.headers.get('content-type') ?? ''

  if (!contentType.includes('application/json')) {
    if (response.url.includes('/admin_sv/login')) {
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
    create?: boolean
    update: boolean
    delete?: boolean
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
  updated_at: string | null
}

export type ProductAttributeRow = {
  attribute_id: number
  attribute_value_id: number[]
  custom_value: string
}

export type ProductEditorAttributeOption = {
  id: number
  name: string
  slug: string
  type: string
  is_required: boolean
  is_filterable: boolean
  is_multiple: boolean
  is_use_in_variations: boolean
  allow_custom_value: boolean
  values: Array<{
    id: number
    value: string
    slug: string
    color_code: string | null
  }>
}

export type ProductEditorOptions = {
  attributes: ProductEditorAttributeOption[]
  variation_attributes: ProductEditorAttributeOption[]
  tax_categories: Array<{ id: number; name: string }>
  shipping_categories: Array<{ id: number; name: string }>
  warehouses: Array<{ id: number; name: string; external_id: string | null }>
  stock_settings: {
    warehouse_accounting_enabled: boolean
    fallback_to_first_warehouse: boolean
  }
}

export type ProductDetails = ProductSummary & {
  description: string | null
  priority: number
  is_variable: boolean
  category_ids: number[]
  stock: number
  backorder: boolean
  units_sold: number
  length: number | null
  width: number | null
  height: number | null
  weight: number | null
  tax_category_id: number | null
  shipping_category_id: number | null
  external_id: string | null
  warehouse_accounting_enabled: boolean
  media: Array<{
    id: number
    collection: 'images' | 'gallery'
    name: string
    file_name: string
    url: string
    thumb_url: string
    order: number
  }>
  attribute_rows: ProductAttributeRow[]
  attributes: Array<{
    id: number
    name: string
    slug: string
    type: string
    source: 'product' | 'variants'
    is_multiple: boolean
    is_use_in_variations: boolean
    values: Array<{
      value_id: number | null
      value: string
      color_code: string | null
    }>
  }>
  variation_attribute_ids: number[]
  variants: Array<{
    id: number
    name: string
    sku: string
    state: string
    price: number
    original_price: number | null
    stock: number
    backorder: boolean
    external_id: string | null
    attributes: ProductAttributeRow[]
    warehouse_stocks: Array<{
      warehouse_id: number
      warehouse_name: string | null
      quantity: number
    }>
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
    state?: string
    sort?: 'updated_desc' | 'updated_asc' | 'name_asc' | 'name_desc' | 'price_asc' | 'price_desc'
    page?: number
  } = {}) => {
    const query = new URLSearchParams()

    if (params.search) query.set('search', params.search)
    if (params.categoryId) query.set('category_id', String(params.categoryId))
    if (params.roomId) query.set('room_id', String(params.roomId))
    if (params.state) query.set('state', params.state)
    if (params.sort) query.set('sort', params.sort)
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

  productEditorOptions: (id: number) =>
    request<ProductEditorOptions>(`/admin_sv/api/products/${id}/editor-options`),

  updateProduct: (
    id: number,
    data: {
      name: string
      slug: string | null
      sku: string | null
      gtin: string | null
      description: string | null
      state: string
      priority: number
      price: number
      original_price: number | null
      category_ids: number[]
      stock: number | null
      backorder: boolean
      length: number | null
      width: number | null
      height: number | null
      weight: number | null
      tax_category_id: number | null
      shipping_category_id: number | null
      warehouse_stocks: Array<{
        warehouse_id: number
        quantity: number
      }>
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

  updateProductAttributes: (
    id: number,
    rows: ProductAttributeRow[],
    csrfToken: string,
  ) =>
    request<{ message: string; product: ProductDetails }>(
      `/admin_sv/api/products/${id}/attributes`,
      {
        method: 'PUT',
        body: JSON.stringify({ rows }),
        csrfToken,
      },
    ),

  createProductVariant: (
    productId: number,
    data: {
      name: string
      sku: string
      price: number
      original_price: number | null
      stock: number | null
      backorder: boolean
      state: string
      external_id: string | null
      warehouse_stocks: Array<{ warehouse_id: number; quantity: number }>
      attributes: ProductAttributeRow[]
    },
    csrfToken: string,
  ) =>
    request<{ message: string; product: ProductDetails }>(
      `/admin_sv/api/products/${productId}/variants`,
      {
        method: 'POST',
        body: JSON.stringify(data),
        csrfToken,
      },
    ),

  updateProductVariant: (
    productId: number,
    variantId: number,
    data: {
      name: string
      sku: string
      price: number
      original_price: number | null
      stock: number | null
      backorder: boolean
      state: string
      external_id: string | null
      warehouse_stocks: Array<{ warehouse_id: number; quantity: number }>
      attributes: ProductAttributeRow[]
    },
    csrfToken: string,
  ) =>
    request<{ message: string; product: ProductDetails }>(
      `/admin_sv/api/products/${productId}/variants/${variantId}`,
      {
        method: 'PUT',
        body: JSON.stringify(data),
        csrfToken,
      },
    ),

  deleteProductVariant: (
    productId: number,
    variantId: number,
    csrfToken: string,
  ) =>
    request<{ message: string; product: ProductDetails }>(
      `/admin_sv/api/products/${productId}/variants/${variantId}`,
      {
        method: 'DELETE',
        csrfToken,
      },
    ),

  uploadProductMedia: (
    id: number,
    collection: 'images' | 'gallery',
    file: File,
    csrfToken: string,
  ) => {
    const form = new FormData()
    form.append('collection', collection)
    form.append('file', file)

    return request<{ message: string; product: ProductDetails }>(
      `/admin_sv/api/products/${id}/media`,
      {
        method: 'POST',
        body: form,
        csrfToken,
      },
    )
  },

  deleteProductMedia: (
    id: number,
    mediaId: number,
    csrfToken: string,
  ) =>
    request<{ message: string; product: ProductDetails }>(
      `/admin_sv/api/products/${id}/media/${mediaId}`,
      {
        method: 'DELETE',
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
