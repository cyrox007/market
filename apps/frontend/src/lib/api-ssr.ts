/**
 * SSR API Client - обертка для работы API на сервере
 * Передает cookies из Express request в Laravel API
 */

import type { IncomingMessage } from 'http'

/** Браузер использует относительный /api/v1; SSR ходит на upstream напрямую с cookies клиента */
function resolveSsrApiBaseUrl(): string {
  const configured =
    process.env.API_SSR_BASE_URL ||
    import.meta.env.VITE_API_BASE_URL ||
    process.env.VITE_API_BASE_URL ||
    'http://localhost:8000/api/v1'

  if (configured.startsWith('http')) {
    return configured.endsWith('/') ? configured.slice(0, -1) : configured
  }

  const upstream = process.env.API_SSR_BASE_URL || 'https://demo1.site.zone/api/v1'
  return upstream.endsWith('/') ? upstream.slice(0, -1) : upstream
}

const API_BASE_URL = resolveSsrApiBaseUrl()

// Таймаут запросов к API при SSR (мс).
// В деве держим короткий таймаут, чтобы фронтенд не «висел», если API не запущен.
const SSR_API_TIMEOUT_MS = process.env.SSR_API_TIMEOUT_MS
  ? Number(process.env.SSR_API_TIMEOUT_MS)
  : 5000

/**
 * Создает fetch функцию для SSR, которая передает cookies из запроса
 */
export function createSSRFetch(req: IncomingMessage) {
  return async function fetchAPI<T>(endpoint: string, options?: RequestInit): Promise<T> {
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`
    const cleanBaseUrl = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL
    const url = endpoint.startsWith('http') ? endpoint : `${cleanBaseUrl}${cleanEndpoint}`

    // Получаем cookies из запроса
    const cookieHeader = req.headers.cookie || ''

    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), SSR_API_TIMEOUT_MS)

    let response: Response
    try {
      response = await fetch(url, {
        ...options,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Cookie': cookieHeader, // Передаем cookies в Laravel API
          ...options?.headers,
        },
        signal: controller.signal,
      })
    } catch (err: any) {
      clearTimeout(timeoutId)

      // Явно помечаем таймауты, чтобы сервер мог их логировать.
      if (err?.name === 'AbortError') {
        const timeoutError = new Error(`API Timeout after ${SSR_API_TIMEOUT_MS}ms for ${url}`) as any
        timeoutError.status = 504
        throw timeoutError
      }

      throw err
    } finally {
      clearTimeout(timeoutId)
    }

    if (!response.ok) {
      let errorMessage = response.statusText
      let errorData: any = null

      try {
        const contentType = response.headers.get('content-type')
        if (contentType && contentType.includes('application/json')) {
          errorData = await response.json()
          errorMessage = errorData.message || errorData.error || errorMessage
        } else {
          const errorText = await response.text().catch(() => response.statusText)
          errorMessage = errorText || errorMessage
        }
      } catch {
        // Если не удалось распарсить, используем статус текст
      }

      const error = new Error(`API Error: ${response.status} ${errorMessage}`) as any
      error.status = response.status
      error.data = errorData
      throw error
    }

    return response.json()
  }
}

/**
 * Создает API клиент для SSR с передачей cookies
 * Использует тот же интерфейс, что и обычный api.ts
 */
export function createSSRApi(req: IncomingMessage) {
  const fetchAPI = createSSRFetch(req)

  return {
    auth: {
      me: () => fetchAPI<{ user: any }>('/auth/me'),
    },
    cart: {
      count: () => fetchAPI<{ count: number }>('/cart/count'),
    },
    wishlist: {
      count: () => fetchAPI<{ count: number }>('/wishlist/count'),
    },
    compare: {
      count: () => fetchAPI<{ count: number }>('/compare/count'),
    },
    products: {
      get: (slug: string, params?: { color?: string; size?: string; region_id?: number }) => {
        const query = new URLSearchParams()
        if (params?.color) query.append('color', params.color)
        if (params?.size) query.append('size', params.size)
        if (params?.region_id) query.append('region_id', params.region_id.toString())
        const suffix = query.toString() ? `?${query.toString()}` : ''
        return fetchAPI<{ product: any; variant_info?: any }>(`/products/${slug}${suffix}`)
      },
      list: (params?: {
        category_id?: number;
        category_slug?: string;
        price_min?: number;
        price_max?: number;
        search?: string;
        sort_by?: string;
        sort_order?: 'asc' | 'desc';
        page?: number;
        per_page?: number;
        colors?: string[];
        sizes?: string[];
        attributes?: Record<string, string[]>;
        region_id?: number;
      }) => {
        const query = new URLSearchParams()
        if (params?.category_id) query.append('category_id', params.category_id.toString())
        if (params?.category_slug) query.append('category_slug', params.category_slug)
        if (params?.price_min !== undefined) query.append('price_min', params.price_min.toString())
        if (params?.price_max !== undefined) query.append('price_max', params.price_max.toString())
        if (params?.search) query.append('search', params.search)
        if (params?.sort_by) query.append('sort_by', params.sort_by)
        if (params?.sort_order) query.append('sort_order', params.sort_order)
        if (params?.page) query.append('page', params.page.toString())
        if (params?.per_page) query.append('per_page', params.per_page.toString())
        if (params?.colors && params.colors.length > 0) query.append('colors', params.colors.join(','))
        if (params?.sizes && params.sizes.length > 0) query.append('sizes', params.sizes.join(','))
        if (params?.region_id) query.append('region_id', params.region_id.toString())
        if (params?.attributes) {
          Object.entries(params.attributes).forEach(([key, values]) => {
            if (values.length > 0) {
              values.forEach((value) => {
                query.append(`attributes[${key}][]`, value)
              })
            }
          })
        }
        return fetchAPI<{ data: any[]; meta: any }>(`/products?${query.toString()}`)
      },
      featured: (params?: { region_id?: number }) => {
        const query = new URLSearchParams()
        if (params?.region_id) query.append('region_id', params.region_id.toString())
        const suffix = query.toString() ? `?${query.toString()}` : ''
        return fetchAPI<{ data: any[] }>(`/products/featured${suffix}`)
      },
      new: (params?: { region_id?: number }) => {
        const query = new URLSearchParams()
        if (params?.region_id) query.append('region_id', params.region_id.toString())
        const suffix = query.toString() ? `?${query.toString()}` : ''
        return fetchAPI<{ data: any[] }>(`/products/new${suffix}`)
      },
      sale: (params?: { region_id?: number }) => {
        const query = new URLSearchParams()
        if (params?.region_id) query.append('region_id', params.region_id.toString())
        const suffix = query.toString() ? `?${query.toString()}` : ''
        return fetchAPI<{ data: any[] }>(`/products/sale${suffix}`)
      },
      search: (
        query: string,
        params?: { page?: number; per_page?: number; sort_by?: string; sort_order?: 'asc' | 'desc'; region_id?: number }
      ) => {
        const searchParams = new URLSearchParams()
        searchParams.append('q', query)
        if (params?.page) searchParams.append('page', params.page.toString())
        if (params?.per_page) searchParams.append('per_page', params.per_page.toString())
        if (params?.sort_by) searchParams.append('sort_by', params.sort_by)
        if (params?.sort_order) searchParams.append('sort_order', params.sort_order)
        if (params?.region_id) searchParams.append('region_id', params.region_id.toString())
        return fetchAPI<{ data: any[]; meta: any }>(`/products/search?${searchParams.toString()}`)
      },
      related: (productId: number, params?: { region_id?: number }) => {
        const query = new URLSearchParams()
        if (params?.region_id) query.append('region_id', params.region_id.toString())
        const suffix = query.toString() ? `?${query.toString()}` : ''
        return fetchAPI<{ data: any[] }>(`/products/${productId}/related${suffix}`)
      },
      bundle: (productId: number, params?: { region_id?: number }) => {
        const query = new URLSearchParams()
        if (params?.region_id) query.append('region_id', params.region_id.toString())
        const suffix = query.toString() ? `?${query.toString()}` : ''
        return fetchAPI<{ data: any[] }>(`/products/${productId}/bundle${suffix}`)
      },
    },
    categories: {
      list: () => fetchAPI<{ data: any[] }>('/categories'),
      get: (slug: string) => fetchAPI<{ category: any }>(`/categories/${slug}`),
      tree: () => fetchAPI<{ tree: any[] }>('/categories/tree'),
    },
    sliders: {
      list: () => fetchAPI<{ data: any[] }>('/sliders'),
      get: (slug: string) => fetchAPI<{ slider: any }>(`/sliders/${slug}`),
    },
    sets: {
      list: (params?: { page?: number; per_page?: number }) => {
        const query = new URLSearchParams()
        if (params?.page) query.append('page', params.page.toString())
        if (params?.per_page) query.append('per_page', params.per_page.toString())
        return fetchAPI<{ data: any[]; meta: any }>(`/sets?${query.toString()}`)
      },
      get: (id: number) => fetchAPI<{ set: any }>(`/sets/${id}`),
    },
    interiorIdeas: {
      list: (params?: { page?: number; per_page?: number }) => {
        const query = new URLSearchParams()
        if (params?.page) query.append('page', params.page.toString())
        if (params?.per_page) query.append('per_page', params.per_page.toString())
        return fetchAPI<{ data: any[]; meta: any }>(`/interior-ideas?${query.toString()}`)
      },
    },
    regions: {
      detect: (params?: { city?: string }) => {
        const query = new URLSearchParams()
        if (params?.city) query.append('city', params.city)
        return fetchAPI<{ region: any | null; source: string }>(`/regions/detect?${query.toString()}`)
      },
    },
  }
}
