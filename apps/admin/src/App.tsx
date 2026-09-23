import { Component, type ErrorInfo, type ReactNode, useEffect, useMemo, useState } from 'react'
import {
  AlertTriangle,
  ArrowLeft,
  ArrowUpDown,
  Bell,
  CheckCircle2,
  ChevronRight,
  CircleAlert,
  ClipboardList,
  ExternalLink,
  Filter,
  FolderTree,
  Loader2,
  MapPin,
  Menu,
  Moon,
  Package,
  Plus,
  RefreshCw,
  Search,
  SlidersHorizontal,
  Store,
  Sun,
  Trash2,
  UserRound,
  Warehouse,
  X,
} from 'lucide-react'
import {
  AuthRequiredError,
  backendApi,
  type AdminOrder,
  type AttributeDefinition,
  type CategoryNode,
  type LocationRecord,
  type ProductAttributeRow,
  type ProductDetails,
  type ProductEditorOptions,
  type ProductSummary,
  type SessionInfo,
  type StoreRecord,
  type WarehouseRecord,
} from './api'

type Theme = 'light' | 'dark'
type ModuleKey = 'products' | 'attributes' | 'orders' | 'stores' | 'locations' | 'warehouses'
type SectionKind = 'categories' | 'rooms'
type ProductTab = 'main' | 'description' | 'attributes' | 'variants' | 'inventory' | 'media' | 'seo' | 'links'

type TreeRow = {
  id: number
  slug: string
  name: string
  depth: number
  count: number
}

type ProductDraft = {
  name: string
  slug: string
  sku: string
  gtin: string
  description: string
  state: string
  priority: string
  price: string
  original_price: string
  category_ids: number[]
  stock: string
  backorder: boolean
  length: string
  width: string
  height: string
  weight: string
  tax_category_id: string
  shipping_category_id: string
  warehouse_stocks: Array<{
    warehouse_id: number
    quantity: string
  }>
}

type VariantDraft = {
  id: number | null
  name: string
  sku: string
  price: string
  original_price: string
  stock: string
  backorder: boolean
  state: string
  external_id: string
  warehouse_stocks: Array<{
    warehouse_id: number
    quantity: string
  }>
  attributes: ProductAttributeRow[]
}


function textValue(value: unknown, fallback = ''): string {
  if (value === null || value === undefined) {
    return fallback
  }

  if (typeof value === 'string') {
    return value
  }

  if (typeof value === 'number' || typeof value === 'boolean') {
    return String(value)
  }

  if (typeof value === 'object' && value !== null && 'value' in value) {
    return textValue((value as { value?: unknown }).value, fallback)
  }

  return fallback
}

class AdminErrorBoundary extends Component<
  { children: ReactNode },
  { error: Error | null }
> {
  state: { error: Error | null } = { error: null }

  static getDerivedStateFromError(error: Error) {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('Ошибка интерфейса новой админ-панели', error, info)
  }

  render() {
    if (this.state.error) {
      return (
        <div className="full-state">
          <CircleAlert size={30} />
          <strong>Ошибка интерфейса</strong>
          <span>{this.state.error.message}</span>
          <button
            className="btn primary"
            type="button"
            onClick={() => window.location.reload()}
          >
            Перезагрузить
          </button>
        </div>
      )
    }

    return this.props.children
  }
}

function initialTheme(): Theme {
  const saved = localStorage.getItem('sv-admin-theme')

  if (saved === 'light' || saved === 'dark') {
    return saved
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

function formatMoney(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return '—'
  }

  return new Intl.NumberFormat('ru-RU').format(value) + ' ₽'
}

function flattenTree(nodes: CategoryNode[], depth = 0): TreeRow[] {
  return nodes.flatMap((node) => [
    {
      id: node.id,
      slug: textValue(node.slug),
      name: textValue(node.name, 'Без названия'),
      depth,
      count: Number(node.products_count ?? 0),
    },
    ...flattenTree(node.children ?? [], depth + 1),
  ])
}

function stateLabel(state: unknown): string {
  const normalized = textValue(state, 'Неизвестно')

  return {
    active: 'Активен',
    draft: 'Черновик',
    inactive: 'Неактивен',
    unlisted: 'Скрыт',
    unavailable: 'Недоступен',
    retired: 'Снят с продажи',
  }[normalized] ?? normalized
}

function productDraft(product: ProductDetails): ProductDraft {
  return {
    name: textValue(product.name),
    slug: textValue(product.slug),
    sku: textValue(product.sku),
    gtin: textValue(product.gtin),
    description: textValue(product.description),
    state: textValue(product.state, 'draft'),
    priority: String(product.priority ?? 0),
    price: String(product.price ?? 0),
    original_price: product.original_price === null ? '' : String(product.original_price),
    category_ids: [...product.category_ids],
    stock: String(product.stock ?? 0),
    backorder: Boolean(product.backorder),
    length: product.length === null ? '' : String(product.length),
    width: product.width === null ? '' : String(product.width),
    height: product.height === null ? '' : String(product.height),
    weight: product.weight === null ? '' : String(product.weight),
    tax_category_id: product.tax_category_id === null ? '' : String(product.tax_category_id),
    shipping_category_id: product.shipping_category_id === null ? '' : String(product.shipping_category_id),
    warehouse_stocks: product.warehouse_stocks.map((row) => ({
      warehouse_id: row.warehouse_id,
      quantity: String(row.quantity),
    })),
  }
}

function variantDraft(
  product: ProductDetails,
  options: ProductEditorOptions,
  variant?: ProductDetails['variants'][number],
): VariantDraft {
  const existingRows = new Map(
    (variant?.attributes ?? []).map((row) => [row.attribute_id, row]),
  )

  return {
    id: variant?.id ?? null,
    name: variant?.name ?? product.name,
    sku: variant?.sku ?? '',
    price: String(variant?.price ?? product.price ?? 0),
    original_price: variant?.original_price === null || variant?.original_price === undefined
      ? ''
      : String(variant.original_price),
    stock: String(variant?.stock ?? 0),
    backorder: Boolean(variant?.backorder ?? false),
    state: variant?.state ?? 'active',
    external_id: variant?.external_id ?? '',
    warehouse_stocks: (variant?.warehouse_stocks ?? []).map((row) => ({
      warehouse_id: row.warehouse_id,
      quantity: String(row.quantity),
    })),
    attributes: options.variation_attributes.map((attribute) => {
      const current = existingRows.get(attribute.id)

      return {
        attribute_id: attribute.id,
        attribute_value_id: [...(current?.attribute_value_id ?? [])],
        custom_value: current?.custom_value ?? '',
      }
    }),
  }
}


function AdminApp() {
  const [theme, setTheme] = useState<Theme>(initialTheme)
  const [module, setModule] = useState<ModuleKey>('products')
  const [session, setSession] = useState<SessionInfo | null>(null)
  const [authRequired, setAuthRequired] = useState(false)
  const [sessionError, setSessionError] = useState<string | null>(null)
  const [sidebarOpen, setSidebarOpen] = useState(false)

  useEffect(() => {
    document.documentElement.dataset.theme = theme
    localStorage.setItem('sv-admin-theme', theme)
  }, [theme])

  useEffect(() => {
    if (!sidebarOpen) {
      return
    }

    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setSidebarOpen(false)
      }
    }

    window.addEventListener('keydown', closeOnEscape)

    return () => {
      window.removeEventListener('keydown', closeOnEscape)
    }
  }, [sidebarOpen])

  useEffect(() => {
    backendApi.session()
      .then((value) => {
        setSession(value)
        setAuthRequired(false)
      })
      .catch((error) => {
        if (error instanceof AuthRequiredError) {
          setAuthRequired(true)
          return
        }

        setSessionError(error instanceof Error ? error.message : String(error))
      })
  }, [])

  if (authRequired) {
    return (
      <LoginRequired theme={theme} setTheme={setTheme} />
    )
  }

  if (!session && !sessionError) {
    return <FullScreenLoading text="Подключаюсь к Laravel и проверяю административную сессию…" />
  }

  if (sessionError) {
    return <FullScreenError title="Backend недоступен" message={sessionError} />
  }

  if (!session) {
    return null
  }

  return (
    <div className="app">
      <Topbar
        theme={theme}
        setTheme={setTheme}
        session={session}
        sidebarOpen={sidebarOpen}
        onToggleSidebar={() => setSidebarOpen((value) => !value)}
      />

      <div className="frame">
        <button
          className={sidebarOpen ? 'sidebar-backdrop open' : 'sidebar-backdrop'}
          type="button"
          aria-label="Закрыть меню"
          onClick={() => setSidebarOpen(false)}
        />
        <Sidebar
          module={module}
          setModule={(value) => {
            setModule(value)
            setSidebarOpen(false)
          }}
          session={session}
          open={sidebarOpen}
          onClose={() => setSidebarOpen(false)}
        />

        <main className="workspace">
          <ModuleHeader module={module} />

          {module === 'products' && (
            <ProductsWorkspace session={session} />
          )}

          {module === 'attributes' && (
            <AttributesWorkspace session={session} />
          )}

          {module === 'orders' && (
            <OrdersWorkspace />
          )}

          {module === 'stores' && (
            <StoresWorkspace />
          )}

          {module === 'locations' && (
            <LocationsWorkspace />
          )}

          {module === 'warehouses' && (
            <WarehousesWorkspace />
          )}
        </main>
      </div>
    </div>
  )
}

export function App() {
  return (
    <AdminErrorBoundary>
      <AdminApp />
    </AdminErrorBoundary>
  )
}

function LoginRequired({
  theme,
  setTheme,
}: {
  theme: Theme
  setTheme: (value: Theme) => void
}) {
  return (
    <div className="login-state">
      <div className="login-card">
        <span className="brand-mark"><Package size={20} /></span>
        <span className="eyebrow">Новая админ-панель</span>
        <h1>Нужна административная сессия</h1>
        <p>
          Прототип теперь работает с реальным Laravel backend. Войдите через Filament,
          открытый через этот же локальный адрес, чтобы браузер получил сессионную cookie.
        </p>
        <div className="login-actions">
          <a className="btn primary" href="/admin_sv/login">Войти в админку</a>
          <button
            className="btn ghost"
            type="button"
            onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
          >
            {theme === 'light' ? <Moon size={16} /> : <Sun size={16} />}
            Тема
          </button>
        </div>
        <small>
          После входа вернитесь в <strong>/admin-ui/</strong>.
        </small>
      </div>
    </div>
  )
}

function Topbar({
  theme,
  setTheme,
  session,
  sidebarOpen,
  onToggleSidebar,
}: {
  theme: Theme
  setTheme: (value: Theme) => void
  session: SessionInfo
  sidebarOpen: boolean
  onToggleSidebar: () => void
}) {
  return (
    <header className="topbar">
      <div className="topbar-start">
        <button
          className="mobile-nav-toggle"
          type="button"
          aria-label={sidebarOpen ? 'Закрыть меню' : 'Открыть меню'}
          aria-expanded={sidebarOpen}
          onClick={onToggleSidebar}
        >
          {sidebarOpen ? <X size={18} /> : <Menu size={18} />}
        </button>

        <div className="brand">
          <span className="brand-mark"><Package size={16} /></span>
          <div>
            <strong>Светофор Мебели</strong>
            <small>React-админка · Laravel backend</small>
          </div>
        </div>
      </div>

      <div className="top-tools">
        <span className="live-badge"><i /> Backend подключён</span>

        <a className="icon-btn" href="/admin_sv" aria-label="Открыть Filament">
          <ExternalLink size={17} />
        </a>

        <button
          className="icon-btn"
          type="button"
          aria-label="Переключить тему"
          onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
        >
          {theme === 'light' ? <Moon size={17} /> : <Sun size={17} />}
        </button>

        <button className="icon-btn notify" type="button" aria-label="Уведомления">
          <Bell size={17} />
          <i />
        </button>

        <div className="user-btn user-static">
          <span><UserRound size={15} /></span>
          <div>
            <strong>{session.user.name}</strong>
            <small>{session.user.email}</small>
          </div>
        </div>
      </div>
    </header>
  )
}

const nav: Array<{
  title: string
  items: Array<[ModuleKey, string, typeof Package, keyof SessionInfo['permissions'] | null]>
}> = [
  {
    title: 'Каталог',
    items: [
      ['products', 'Товары и разделы', Package, 'products'],
      ['attributes', 'Характеристики', SlidersHorizontal, 'attributes'],
    ],
  },
  {
    title: 'Продажи',
    items: [
      ['orders', 'Заказы', ClipboardList, 'orders'],
      ['stores', 'Магазины', Store, 'stores'],
    ],
  },
  {
    title: 'Логистика',
    items: [
      ['locations', 'Локации', MapPin, 'shipping_locations'],
      ['warehouses', 'Склады', Warehouse, 'warehouses'],
    ],
  },
]

function Sidebar({
  module,
  setModule,
  session,
  open,
  onClose,
}: {
  module: ModuleKey
  setModule: (value: ModuleKey) => void
  session: SessionInfo
  open: boolean
  onClose: () => void
}) {
  return (
    <aside className={open ? 'sidebar open' : 'sidebar'}>
      <div className="sidebar-mobile-head">
        <strong>Разделы</strong>
        <button type="button" aria-label="Закрыть меню" onClick={onClose}>
          <X size={18} />
        </button>
      </div>
      <nav>
        {nav.map((group) => (
          <div className="nav-group" key={group.title}>
            <div className="nav-title">{group.title}</div>

            {group.items.map(([key, label, Icon, permissionKey]) => {
              const allowed = permissionKey === null || session.permissions[permissionKey]?.view

              return (
                <button
                  className={module === key ? 'nav-link active' : 'nav-link'}
                  type="button"
                  onClick={() => allowed && setModule(key)}
                  disabled={!allowed}
                  key={key}
                >
                  <Icon size={17} />
                  <span>{label}</span>
                  {!allowed && <em>нет доступа</em>}
                </button>
              )
            })}
          </div>
        ))}
      </nav>

    </aside>
  )
}

const headers: Record<ModuleKey, [string, string, string]> = {
  products: [
    '',
    'Товары и разделы',
    'Реальные категории, комнаты и товары из текущей базы. Изменения основной карточки сохраняются в Laravel.',
  ],
  attributes: [
    'Справочник каталога',
    'Характеристики товаров',
    'Реальные свойства и значения. Настройки свойства сохраняются через AttributePolicy.',
  ],
  orders: [
    'Продажи',
    'Заказы',
    'Реальные заказы доступны только для чтения. Опасные действия пока не подключаем из-за замечаний аудита.',
  ],
  stores: [
    'Продажи',
    'Магазины',
    'Реальные точки продаж из базы.',
  ],
  locations: [
    'Логистика',
    'Локации',
    'Реальная география доставки и текущие тарифные поля.',
  ],
  warehouses: [
    'Логистика',
    'Склады',
    'Реальные склады из БД. Для ресурса добавлена отдельная WarehousePolicy и разрешения.',
  ],
}

function ModuleHeader({ module }: { module: ModuleKey }) {
  const [eyebrow, title, text] = headers[module]

  return (
    <header className="page-head">
      <div>
        {eyebrow && <span className="eyebrow">{eyebrow}</span>}
        <h1>{title}</h1>
        <p>{text}</p>
      </div>
    </header>
  )
}

function ProductsWorkspace({ session }: { session: SessionInfo }) {
  const [kind, setKind] = useState<SectionKind>('categories')
  const [categories, setCategories] = useState<CategoryNode[]>([])
  const [rooms, setRooms] = useState<CategoryNode[]>([])
  const [treeLoading, setTreeLoading] = useState(true)
  const [treeError, setTreeError] = useState<string | null>(null)
  const [selectedSection, setSelectedSection] = useState<TreeRow | null>(null)
  const [products, setProducts] = useState<ProductSummary[]>([])
  const [productsTotal, setProductsTotal] = useState(0)
  const [productsPage, setProductsPage] = useState(1)
  const [productsLastPage, setProductsLastPage] = useState(1)
  const [productsLoading, setProductsLoading] = useState(true)
  const [productsError, setProductsError] = useState<string | null>(null)
  const [editingProductId, setEditingProductId] = useState<number | null>(null)
  const [search, setSearch] = useState('')
  const [stateFilter, setStateFilter] = useState('')
  const [sort, setSort] = useState<
    'updated_desc' | 'updated_asc' | 'name_asc' | 'name_desc' | 'price_asc' | 'price_desc'
  >('updated_desc')

  const tree = useMemo(
    () => flattenTree(kind === 'categories' ? categories : rooms),
    [kind, categories, rooms],
  )

  useEffect(() => {
    Promise.all([backendApi.categoryTree(), backendApi.roomTree()])
      .then(([categoryResponse, roomResponse]) => {
        setCategories(categoryResponse.tree)
        setRooms(roomResponse.tree)
        setTreeLoading(false)
      })
      .catch((error) => {
        setTreeError(error instanceof Error ? error.message : String(error))
        setTreeLoading(false)
      })
  }, [])

  useEffect(() => {
    let cancelled = false

    setProductsLoading(true)
    setProductsError(null)

    const timer = window.setTimeout(() => {
      backendApi.products({
        search: search.trim() || undefined,
        categoryId: kind === 'categories' ? selectedSection?.id ?? null : null,
        roomId: kind === 'rooms' ? selectedSection?.id ?? null : null,
        state: stateFilter || undefined,
        sort,
        page: productsPage,
      })
        .then((response) => {
          if (cancelled) return

          setProducts(response.data)
          setProductsTotal(response.meta?.total ?? response.data.length)
          setProductsLastPage(response.meta?.last_page ?? 1)
          setProductsLoading(false)
        })
        .catch((error) => {
          if (cancelled) return
          setProductsError(error instanceof Error ? error.message : String(error))
          setProductsLoading(false)
        })
    }, 250)

    return () => {
      cancelled = true
      window.clearTimeout(timer)
    }
  }, [kind, selectedSection, search, stateFilter, sort, productsPage])

  if (editingProductId !== null) {
    return (
      <ProductEditor
        productId={editingProductId}
        session={session}
        categories={categories}
        onBack={() => setEditingProductId(null)}
        onProductSaved={(saved) => {
          setProducts((current) => current.map((item) => (
            item.id === saved.id ? saved : item
          )))
        }}
      />
    )
  }

  const chooseKind = (value: SectionKind) => {
    setKind(value)
    setSelectedSection(null)
    setProductsPage(1)
  }

  const chooseSection = (value: TreeRow | null) => {
    setSelectedSection(value)
    setProductsPage(1)
  }

  const clearFilters = () => {
    setSearch('')
    setStateFilter('')
    setSort('updated_desc')
    setProductsPage(1)
  }

  return (
    <div className="catalog-browser-layout">
      <section className="panel section-panel">
        <PanelHead eyebrow="Структура" title="Разделы" subtitle="Категории и комнаты" />

        <div className="segmented">
          <button
            className={kind === 'categories' ? 'active' : ''}
            type="button"
            onClick={() => chooseKind('categories')}
          >
            Категории
          </button>
          <button
            className={kind === 'rooms' ? 'active' : ''}
            type="button"
            onClick={() => chooseKind('rooms')}
          >
            Комнаты
          </button>
        </div>

        {treeLoading && <InlineLoading text="Загружаю дерево…" />}
        {treeError && <InlineError text={treeError} />}

        {!treeLoading && !treeError && (
          <div className="tree">
            <button
              className={selectedSection === null ? 'tree-row active' : 'tree-row'}
              type="button"
              onClick={() => chooseSection(null)}
            >
              <span><FolderTree size={14} /></span>
              <div><strong>Все товары</strong></div>
              <em>—</em>
            </button>

            {tree.map((node) => (
              <button
                className={selectedSection?.id === node.id ? 'tree-row active' : 'tree-row'}
                style={{ paddingLeft: 8 + node.depth * 13 }}
                type="button"
                onClick={() => chooseSection(node)}
                key={node.id}
              >
                <span>{node.depth > 0 ? <ChevronRight size={12} /> : <FolderTree size={14} />}</span>
                <div><strong>{node.name}</strong></div>
                <em>{node.count || ''}</em>
              </button>
            ))}
          </div>
        )}
      </section>

      <section className="panel catalog-products-panel">
        <div className="catalog-head">
          <div>
            <span className="eyebrow">{kind === 'categories' ? 'Категория' : 'Комната'}</span>
            <h2>{selectedSection?.name ?? 'Все товары'}</h2>
            <p>{productsTotal} товаров</p>
          </div>
        </div>

        <div className="catalog-toolbar">
          <label className="catalog-search">
            <Search size={16} />
            <input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value)
                setProductsPage(1)
              }}
              placeholder="Поиск по названию, SKU или GTIN"
            />
          </label>

          <label className="catalog-select">
            <Filter size={15} />
            <select
              value={stateFilter}
              onChange={(event) => {
                setStateFilter(event.target.value)
                setProductsPage(1)
              }}
            >
              <option value="">Все статусы</option>
              <option value="active">Активные</option>
              <option value="draft">Черновики</option>
              <option value="inactive">Неактивные</option>
              <option value="unlisted">Скрытые</option>
              <option value="unavailable">Недоступные</option>
              <option value="retired">Снятые с продажи</option>
            </select>
          </label>

          <label className="catalog-select sort-select">
            <ArrowUpDown size={15} />
            <select
              value={sort}
              onChange={(event) => {
                setSort(event.target.value as typeof sort)
                setProductsPage(1)
              }}
            >
              <option value="updated_desc">Недавно изменённые</option>
              <option value="updated_asc">Давно не изменялись</option>
              <option value="name_asc">Название А–Я</option>
              <option value="name_desc">Название Я–А</option>
              <option value="price_asc">Цена по возрастанию</option>
              <option value="price_desc">Цена по убыванию</option>
            </select>
          </label>

          {(search || stateFilter || sort !== 'updated_desc') && (
            <button className="btn ghost small" type="button" onClick={clearFilters}>
              Сбросить
            </button>
          )}
        </div>

        <div className="catalog-list">
          <div className="catalog-list-head">
            <span>Товар</span>
            <span>Статус</span>
            <span>Цена</span>
            <span>Остаток</span>
            <span>Разделы</span>
            <span />
          </div>

          {productsLoading && <InlineLoading text="Загружаю товары…" />}
          {productsError && <InlineError text={productsError} />}

          {!productsLoading && !productsError && products.map((product) => (
            <button
              className="catalog-list-row"
              type="button"
              onClick={() => setEditingProductId(product.id)}
              key={product.id}
            >
              <span className="catalog-product">
                <span className="thumb">{product.name.slice(0, 2).toUpperCase()}</span>
                <span>
                  <strong>{product.name}</strong>
                  <small>{product.sku || `#${product.id}`}{product.gtin ? ` · ${product.gtin}` : ''}</small>
                </span>
              </span>
              <span><Status value={stateLabel(product.state)} compact /></span>
              <strong className="catalog-price">{formatMoney(product.price)}</strong>
              <span>{product.stock} шт.{product.variants_count > 0 ? ` · ${product.variants_count} вар.` : ''}</span>
              <span className="catalog-categories">
                {product.categories.length > 0
                  ? product.categories.slice(0, 2).map((item) => item.name).join(', ')
                  : 'Без категории'}
              </span>
              <ChevronRight size={16} />
            </button>
          ))}

          {!productsLoading && !productsError && products.length === 0 && (
            <EmptyState text="По выбранным условиям товары не найдены." />
          )}
        </div>

        <footer className="catalog-pagination">
          <span>
            Страница {productsPage} из {productsLastPage} · всего {productsTotal}
          </span>
          <div>
            <button
              className="btn ghost small"
              type="button"
              disabled={productsPage <= 1}
              onClick={() => setProductsPage((page) => Math.max(1, page - 1))}
            >
              Назад
            </button>
            <button
              className="btn ghost small"
              type="button"
              disabled={productsPage >= productsLastPage}
              onClick={() => setProductsPage((page) => Math.min(productsLastPage, page + 1))}
            >
              Далее
            </button>
          </div>
        </footer>
      </section>
    </div>
  )
}

function ProductEditor({
  productId,
  session,
  categories,
  onBack,
  onProductSaved,
}: {
  productId: number
  session: SessionInfo
  categories: CategoryNode[]
  onBack: () => void
  onProductSaved: (product: ProductDetails) => void
}) {
  const [product, setProduct] = useState<ProductDetails | null>(null)
  const [draft, setDraft] = useState<ProductDraft | null>(null)
  const [options, setOptions] = useState<ProductEditorOptions | null>(null)
  const [attributeRows, setAttributeRows] = useState<ProductAttributeRow[]>([])
  const [tab, setTab] = useState<ProductTab>('main')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [attributesSaving, setAttributesSaving] = useState(false)
  const [mediaSaving, setMediaSaving] = useState(false)
  const [variantDraftState, setVariantDraftState] = useState<VariantDraft | null>(null)
  const [variantSaving, setVariantSaving] = useState(false)
  const [variantMessage, setVariantMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [attributesMessage, setAttributesMessage] = useState<string | null>(null)

  const flatCategories = useMemo(() => flattenTree(categories), [categories])

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)
    setMessage(null)
    setAttributesMessage(null)
    setVariantMessage(null)
    setVariantDraftState(null)

    Promise.all([
      backendApi.product(productId),
      backendApi.productEditorOptions(productId),
    ])
      .then(([productResponse, editorOptions]) => {
        if (cancelled) return

        setProduct(productResponse.product)
        setDraft(productDraft(productResponse.product))
        setOptions(editorOptions)
        setAttributeRows(productResponse.product.attribute_rows.map((row) => ({
          attribute_id: row.attribute_id,
          attribute_value_id: [...row.attribute_value_id],
          custom_value: row.custom_value,
        })))
        setLoading(false)
      })
      .catch((loadError) => {
        if (cancelled) return
        setError(loadError instanceof Error ? loadError.message : String(loadError))
        setLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [productId])

  if (loading || !product || !draft || !options) {
    return (
      <section className="panel product-editor-page">
        <div className="editor-page-back">
          <button className="btn ghost small" type="button" onClick={onBack}>
            <ArrowLeft size={15} />
            Назад к каталогу
          </button>
        </div>
        {error ? <InlineError text={error} /> : <InlineLoading text="Загружаю товар и справочники…" />}
      </section>
    )
  }

  const canUpdate = Boolean(session.permissions.products?.update)
  const requiredChecks = [
    Boolean(draft.name.trim()),
    Number(draft.price) >= 0,
    Boolean(draft.state),
  ]
  const completion = Math.round(
    requiredChecks.filter(Boolean).length / requiredChecks.length * 100,
  )

  const numberOrNull = (value: string): number | null => {
    const trimmed = value.trim()
    if (trimmed === '') return null

    const parsed = Number(trimmed.replace(',', '.'))
    return Number.isFinite(parsed) ? parsed : null
  }

  const applySavedProduct = (saved: ProductDetails) => {
    setProduct(saved)
    setDraft(productDraft(saved))
    setAttributeRows(saved.attribute_rows.map((row) => ({
      attribute_id: row.attribute_id,
      attribute_value_id: [...row.attribute_value_id],
      custom_value: row.custom_value,
    })))
    onProductSaved(saved)
  }

  const saveProduct = async () => {
    if (!canUpdate) return

    setSaving(true)
    setError(null)
    setMessage(null)

    try {
      const response = await backendApi.updateProduct(
        product.id,
        {
          name: draft.name.trim(),
          slug: draft.slug.trim() || null,
          sku: draft.sku.trim() || null,
          gtin: draft.gtin.trim() || null,
          description: draft.description.trim() || null,
          state: draft.state,
          priority: Number(draft.priority || 0),
          price: Number(draft.price.replace(',', '.') || 0),
          original_price: numberOrNull(draft.original_price),
          category_ids: draft.category_ids,
          stock: numberOrNull(draft.stock),
          backorder: draft.backorder,
          length: numberOrNull(draft.length),
          width: numberOrNull(draft.width),
          height: numberOrNull(draft.height),
          weight: numberOrNull(draft.weight),
          tax_category_id: draft.tax_category_id ? Number(draft.tax_category_id) : null,
          shipping_category_id: draft.shipping_category_id ? Number(draft.shipping_category_id) : null,
          warehouse_stocks: draft.warehouse_stocks.map((row) => ({
            warehouse_id: row.warehouse_id,
            quantity: Number(row.quantity.replace(',', '.') || 0),
          })),
        },
        session.csrf_token,
      )

      applySavedProduct(response.product)
      setMessage(response.message)
    } catch (saveError) {
      setError(saveError instanceof Error ? saveError.message : String(saveError))
    } finally {
      setSaving(false)
    }
  }

  const addAttributeRow = () => {
    const used = new Set(attributeRows.map((row) => row.attribute_id))
    const available = options.attributes.find((attribute) => !used.has(attribute.id))
    if (!available) return

    setAttributeRows((current) => [
      ...current,
      {
        attribute_id: available.id,
        attribute_value_id: [],
        custom_value: '',
      },
    ])
    setAttributesMessage(null)
  }

  const updateAttributeRow = (
    index: number,
    patch: Partial<ProductAttributeRow>,
  ) => {
    setAttributeRows((current) => current.map((row, rowIndex) => (
      rowIndex === index ? { ...row, ...patch } : row
    )))
    setAttributesMessage(null)
  }

  const removeAttributeRow = (index: number) => {
    setAttributeRows((current) => current.filter((_, rowIndex) => rowIndex !== index))
    setAttributesMessage(null)
  }

  const saveAttributes = async () => {
    if (!canUpdate) return

    const incomplete = attributeRows.find((row) => (
      row.attribute_value_id.length === 0 && row.custom_value.trim() === ''
    ))

    if (incomplete) {
      setError('У каждой добавленной характеристики нужно выбрать или ввести значение.')
      return
    }

    setAttributesSaving(true)
    setError(null)
    setAttributesMessage(null)

    try {
      const response = await backendApi.updateProductAttributes(
        product.id,
        attributeRows,
        session.csrf_token,
      )

      applySavedProduct(response.product)
      setAttributesMessage(response.message)
    } catch (saveError) {
      setError(saveError instanceof Error ? saveError.message : String(saveError))
    } finally {
      setAttributesSaving(false)
    }
  }

  const addWarehouseRow = () => {
    const used = new Set(draft.warehouse_stocks.map((row) => row.warehouse_id))
    const warehouse = options.warehouses.find((item) => !used.has(item.id))
    if (!warehouse) return

    setDraft({
      ...draft,
      warehouse_stocks: [
        ...draft.warehouse_stocks,
        { warehouse_id: warehouse.id, quantity: '0' },
      ],
    })
  }

  const openVariant = (variant: ProductDetails['variants'][number]) => {
    setVariantDraftState(variantDraft(product, options, variant))
    setVariantMessage(null)
    setError(null)
  }

  const createVariant = () => {
    setVariantDraftState(variantDraft(product, options))
    setVariantMessage(null)
    setError(null)
  }

  const saveVariant = async () => {
    if (!variantDraftState || !canUpdate) return

    setVariantSaving(true)
    setError(null)
    setVariantMessage(null)

    const payload = {
      name: variantDraftState.name.trim(),
      sku: variantDraftState.sku.trim(),
      price: Number(variantDraftState.price.replace(',', '.') || 0),
      original_price: numberOrNull(variantDraftState.original_price),
      stock: numberOrNull(variantDraftState.stock),
      backorder: variantDraftState.backorder,
      state: variantDraftState.state,
      external_id: variantDraftState.external_id.trim() || null,
      warehouse_stocks: variantDraftState.warehouse_stocks.map((row) => ({
        warehouse_id: row.warehouse_id,
        quantity: Number(row.quantity.replace(',', '.') || 0),
      })),
      attributes: variantDraftState.attributes,
    }

    try {
      const response = variantDraftState.id === null
        ? await backendApi.createProductVariant(
            product.id,
            payload,
            session.csrf_token,
          )
        : await backendApi.updateProductVariant(
            product.id,
            variantDraftState.id,
            payload,
            session.csrf_token,
          )

      applySavedProduct(response.product)

      const savedVariant = variantDraftState.id === null
        ? response.product.variants.find((item) => item.sku === payload.sku)
        : response.product.variants.find((item) => item.id === variantDraftState.id)

      setVariantDraftState(
        savedVariant
          ? variantDraft(response.product, options, savedVariant)
          : null,
      )
      setVariantMessage(response.message)
    } catch (variantError) {
      setError(variantError instanceof Error ? variantError.message : String(variantError))
    } finally {
      setVariantSaving(false)
    }
  }

  const deleteVariant = async () => {
    if (!variantDraftState?.id || !session.permissions.products?.delete) return

    const confirmed = window.confirm(
      `Удалить торговое предложение «${variantDraftState.name}»? Это действие нельзя отменить.`,
    )
    if (!confirmed) return

    setVariantSaving(true)
    setError(null)
    setVariantMessage(null)

    try {
      const response = await backendApi.deleteProductVariant(
        product.id,
        variantDraftState.id,
        session.csrf_token,
      )

      applySavedProduct(response.product)
      setVariantDraftState(null)
      setVariantMessage(response.message)
    } catch (variantError) {
      setError(variantError instanceof Error ? variantError.message : String(variantError))
    } finally {
      setVariantSaving(false)
    }
  }

  const uploadMedia = async (
    collection: 'images' | 'gallery',
    file: File | null,
  ) => {
    if (!file || !canUpdate) return

    setMediaSaving(true)
    setError(null)
    setMessage(null)

    try {
      const response = await backendApi.uploadProductMedia(
        product.id,
        collection,
        file,
        session.csrf_token,
      )
      applySavedProduct(response.product)
      setMessage(response.message)
    } catch (mediaError) {
      setError(mediaError instanceof Error ? mediaError.message : String(mediaError))
    } finally {
      setMediaSaving(false)
    }
  }

  const deleteMedia = async (mediaId: number) => {
    if (!canUpdate) return

    setMediaSaving(true)
    setError(null)
    setMessage(null)

    try {
      const response = await backendApi.deleteProductMedia(
        product.id,
        mediaId,
        session.csrf_token,
      )
      applySavedProduct(response.product)
      setMessage(response.message)
    } catch (mediaError) {
      setError(mediaError instanceof Error ? mediaError.message : String(mediaError))
    } finally {
      setMediaSaving(false)
    }
  }

  const variantAttributes = product.attributes.filter((attribute) => attribute.source === 'variants')
  const tabUsesProductSave = tab === 'main' || tab === 'description' || tab === 'inventory'
  const mainImage = product.media.find((item) => item.collection === 'images') ?? null
  const galleryImages = product.media.filter((item) => item.collection === 'gallery')

  return (
    <section className="panel product-editor-page">
      <div className="editor-page-back">
        <button className="btn ghost small" type="button" onClick={onBack}>
          <ArrowLeft size={15} />
          Назад к каталогу
        </button>
        <span>Редактирование товара #{product.id}</span>
      </div>

      <div className="editor-head">
        <div className="product-title">
          <span className="hero-thumb">{product.name.slice(0, 2).toUpperCase()}</span>
          <div>
            <span className="eyebrow">Товар #{product.id}</span>
            <h2>{product.name || 'Без названия'}</h2>
            <p>
              {product.sku || 'Без SKU'}
              {product.categories.length > 0 && ' · ' + product.categories.map((item) => item.name).join(', ')}
            </p>
          </div>
        </div>

        <div className="editor-actions">
          <Status value={stateLabel(product.state)} />
          {!canUpdate && <span className="readonly-badge">Только чтение</span>}
        </div>
      </div>

      <div className="quality">
        <div className="quality-summary">
          <div className="quality-score">
            <div
              className="progress-ring"
              style={{
                background: `conic-gradient(var(--secondary) 0 ${completion}%, var(--border) ${completion}% 100%)`,
              }}
            >
              <span>{completion}%</span>
            </div>
            <div>
              <strong>Обязательные поля</strong>
              <small>{requiredChecks.filter(Boolean).length} из {requiredChecks.length} заполнены</small>
            </div>
          </div>

          {error && <span className="chip error"><CircleAlert size={13} /> Есть ошибка</span>}
          {message && <span className="chip ok"><CheckCircle2 size={13} /> {message}</span>}
          {attributesMessage && <span className="chip ok"><CheckCircle2 size={13} /> {attributesMessage}</span>}
          {variantMessage && <span className="chip ok"><CheckCircle2 size={13} /> {variantMessage}</span>}
        </div>

        {error && (
          <div className="quality-problems">
            <div className="backend-error">
              <CircleAlert size={15} />
              <strong>{error}</strong>
            </div>
          </div>
        )}
      </div>

      <div className="tabs product-tabs">
        <button className={tab === 'main' ? 'active' : ''} type="button" onClick={() => setTab('main')}>Основное</button>
        <button className={tab === 'description' ? 'active' : ''} type="button" onClick={() => setTab('description')}>Описание</button>
        <button className={tab === 'attributes' ? 'active' : ''} type="button" onClick={() => setTab('attributes')}>
          Характеристики <span>{attributeRows.length + variantAttributes.length}</span>
        </button>
        <button className={tab === 'variants' ? 'active' : ''} type="button" onClick={() => setTab('variants')}>
          Вариации <span>{product.variants.length}</span>
        </button>
        <button className={tab === 'inventory' ? 'active' : ''} type="button" onClick={() => setTab('inventory')}>Остатки и доставка</button>
        <button className={tab === 'media' ? 'active' : ''} type="button" onClick={() => setTab('media')}>Изображения</button>
        <button className={tab === 'seo' ? 'active' : ''} type="button" onClick={() => setTab('seo')}>SEO / 1С</button>
        <button className={tab === 'links' ? 'active' : ''} type="button" onClick={() => setTab('links')}>Связи</button>
      </div>

      <div className="editor-scroll product-editor-scroll">
        {tab === 'main' && (
          <div className="editor-content-wide">
            <div className="editor-two-column">
              <Card title="Идентификация" subtitle="Название, URL и артикулы">
                <div className="form-grid readable">
                  <LiveField
                    label="Название"
                    value={draft.name}
                    required
                    onChange={(value) => setDraft({ ...draft, name: value })}
                  />
                  <LiveField
                    label="Slug"
                    value={draft.slug}
                    onChange={(value) => setDraft({ ...draft, slug: value })}
                  />
                  <LiveField
                    label="SKU"
                    value={draft.sku}
                    onChange={(value) => setDraft({ ...draft, sku: value })}
                  />
                  <LiveField
                    label="GTIN / штрихкод"
                    value={draft.gtin}
                    onChange={(value) => setDraft({ ...draft, gtin: value })}
                  />
                </div>
              </Card>

              <Card title="Публикация и цены" subtitle="Статус, порядок и стоимость">
                <div className="form-grid readable">
                  <label className="field">
                    <span>Статус <b>*</b></span>
                    <select
                      value={draft.state}
                      onChange={(event) => setDraft({ ...draft, state: event.target.value })}
                      disabled={!canUpdate}
                    >
                      <option value="draft">Черновик</option>
                      <option value="active">Активен</option>
                      <option value="inactive">Неактивен</option>
                    </select>
                  </label>

                  <LiveField
                    label="Приоритет"
                    value={draft.priority}
                    onChange={(value) => setDraft({ ...draft, priority: value })}
                    inputMode="numeric"
                  />
                  <LiveField
                    label="Цена"
                    value={draft.price}
                    suffix="₽"
                    required
                    onChange={(value) => setDraft({ ...draft, price: value })}
                    inputMode="decimal"
                  />
                  <LiveField
                    label="Цена до скидки"
                    value={draft.original_price}
                    suffix="₽"
                    onChange={(value) => setDraft({ ...draft, original_price: value })}
                    inputMode="decimal"
                  />
                </div>
              </Card>
            </div>

            <Card title="Категории" subtitle="Товар может находиться сразу в нескольких разделах каталога">
              <CategoryPicker
                categories={flatCategories}
                selected={draft.category_ids}
                disabled={!canUpdate}
                onChange={(categoryIds) => setDraft({ ...draft, category_ids: categoryIds })}
              />
            </Card>
          </div>
        )}

        {tab === 'description' && (
          <div className="editor-content-narrow">
            <Card title="Описание товара" subtitle="Контент карточки товара на сайте. HTML сохраняется как есть.">
              <textarea
                className="product-description-editor"
                value={draft.description}
                onChange={(event) => setDraft({ ...draft, description: event.target.value })}
                disabled={!canUpdate}
              />
            </Card>
          </div>
        )}

        {tab === 'attributes' && (
          <div className="editor-content-wide">
            <div className="attribute-editor-toolbar">
              <div>
                <h3>Характеристики товара</h3>
                <p>Добавляйте только свойства и значения из справочника. Структура справочника редактируется в разделе «Характеристики».</p>
              </div>
              <div>
                <button
                  className="btn ghost"
                  type="button"
                  onClick={addAttributeRow}
                  disabled={!canUpdate || attributeRows.length >= options.attributes.length}
                >
                  <Plus size={15} />
                  Добавить характеристику
                </button>
                <button
                  className="btn primary"
                  type="button"
                  onClick={saveAttributes}
                  disabled={!canUpdate || attributesSaving}
                >
                  {attributesSaving ? <Loader2 className="spin" size={15} /> : <CheckCircle2 size={15} />}
                  {attributesSaving ? 'Сохраняю…' : 'Сохранить характеристики'}
                </button>
              </div>
            </div>

            {attributeRows.length === 0 ? (
              <div className="attribute-editor-empty">
                <SlidersHorizontal size={24} />
                <strong>Характеристики не добавлены</strong>
                <span>Нажмите «Добавить характеристику», выберите свойство и его значение.</span>
              </div>
            ) : (
              <div className="attribute-editor-list">
                {attributeRows.map((row, index) => {
                  const attribute = options.attributes.find((item) => item.id === row.attribute_id)
                  const usedIds = new Set(attributeRows.map((item, rowIndex) => rowIndex === index ? -1 : item.attribute_id))

                  return (
                    <article className="attribute-editor-row" key={`${row.attribute_id}-${index}`}>
                      <div className="attribute-editor-row-head">
                        <label className="field">
                          <span>Характеристика</span>
                          <select
                            value={row.attribute_id}
                            disabled={!canUpdate}
                            onChange={(event) => {
                              const attributeId = Number(event.target.value)
                              updateAttributeRow(index, {
                                attribute_id: attributeId,
                                attribute_value_id: [],
                                custom_value: '',
                              })
                            }}
                          >
                            {options.attributes.map((item) => (
                              <option
                                value={item.id}
                                disabled={usedIds.has(item.id)}
                                key={item.id}
                              >
                                {item.name}{item.is_required ? ' *' : ''}
                              </option>
                            ))}
                          </select>
                        </label>

                        <div className="attribute-editor-meta">
                          {attribute?.is_required && <span className="chip error">Обязательная</span>}
                          {attribute?.is_multiple && <span className="chip">Несколько значений</span>}
                          {attribute?.is_filterable && <span className="chip">Фильтр</span>}
                        </div>

                        <button
                          className="icon-danger"
                          type="button"
                          aria-label="Удалить характеристику"
                          onClick={() => removeAttributeRow(index)}
                          disabled={!canUpdate}
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>

                      {attribute && (
                        <div className="attribute-editor-values">
                          {attribute.values.length > 0 && !attribute.is_multiple && (
                            <label className="field">
                              <span>Значение</span>
                              <select
                                value={row.attribute_value_id[0] ?? ''}
                                disabled={!canUpdate}
                                onChange={(event) => updateAttributeRow(index, {
                                  attribute_value_id: event.target.value ? [Number(event.target.value)] : [],
                                  custom_value: '',
                                })}
                              >
                                <option value="">Выберите значение</option>
                                {attribute.values.map((value) => (
                                  <option value={value.id} key={value.id}>{value.value}</option>
                                ))}
                              </select>
                            </label>
                          )}

                          {attribute.values.length > 0 && attribute.is_multiple && (
                            <div className="attribute-multiple-field">
                              <span>Значения</span>
                              <div className="attribute-choice-grid">
                                {attribute.values.map((value) => {
                                  const selected = row.attribute_value_id.includes(value.id)
                                  return (
                                    <button
                                      className={selected ? 'attribute-choice selected' : 'attribute-choice'}
                                      type="button"
                                      disabled={!canUpdate}
                                      onClick={() => {
                                        const next = selected
                                          ? row.attribute_value_id.filter((id) => id !== value.id)
                                          : [...row.attribute_value_id, value.id]
                                        updateAttributeRow(index, {
                                          attribute_value_id: next,
                                          custom_value: '',
                                        })
                                      }}
                                      key={value.id}
                                    >
                                      {value.color_code && <i style={{ background: value.color_code }} />}
                                      {value.value}
                                    </button>
                                  )
                                })}
                              </div>
                            </div>
                          )}

                          {attribute.allow_custom_value && (
                            <LiveField
                              label="Своё значение"
                              value={row.custom_value}
                              onChange={(value) => updateAttributeRow(index, {
                                custom_value: value,
                                attribute_value_id: value.trim() ? [] : row.attribute_value_id,
                              })}
                            />
                          )}

                          {attribute.values.length === 0 && !attribute.allow_custom_value && (
                            <div className="attribute-no-values">
                              <CircleAlert size={16} />
                              У этой характеристики нет доступных значений. Добавьте их в справочнике «Характеристики».
                            </div>
                          )}
                        </div>
                      )}
                    </article>
                  )
                })}
              </div>
            )}

            {variantAttributes.length > 0 && (
              <section className="variant-attribute-panel">
                <header>
                  <div>
                    <h3>Характеристики вариаций</h3>
                    <p>Эти значения принадлежат торговым предложениям и редактируются во вкладке «Вариации».</p>
                  </div>
                  <span>{variantAttributes.length}</span>
                </header>

                <div className="product-attribute-grid">
                  {variantAttributes.map((attribute) => (
                    <article className="product-attribute-card" key={`variant-${attribute.id}`}>
                      <header>
                        <div>
                          <strong>{attribute.name}</strong>
                          <small>{attribute.slug}</small>
                        </div>
                        <span className="attribute-source variant">Из вариаций</span>
                      </header>
                      <div className="attribute-values">
                        {attribute.values.map((value) => (
                          <span className="attribute-value-chip" key={`${value.value_id ?? 'custom'}-${value.value}`}>
                            {value.color_code && <i className="attribute-color" style={{ background: value.color_code }} />}
                            {value.value}
                          </span>
                        ))}
                      </div>
                    </article>
                  ))}
                </div>
              </section>
            )}
          </div>
        )}

        {tab === 'variants' && (
          <div className="editor-content-wide">
            <div className="variant-workspace">
              <section className="variant-list-panel">
                <header>
                  <div>
                    <h3>Торговые предложения</h3>
                    <p>{product.variants.length} шт.</p>
                  </div>
                  <button
                    className="btn primary small"
                    type="button"
                    onClick={createVariant}
                    disabled={!canUpdate || !session.permissions.products?.create}
                  >
                    <Plus size={14} />
                    Добавить
                  </button>
                </header>

                <div className="variant-list">
                  {product.variants.length === 0 && (
                    <div className="variant-list-empty">
                      Торговых предложений пока нет.
                    </div>
                  )}

                  {product.variants.map((variant) => {
                    const selected = variantDraftState?.id === variant.id
                    const labels = variant.attributes
                      .flatMap((row) => {
                        const attribute = options.variation_attributes.find((item) => item.id === row.attribute_id)
                        if (!attribute) return []

                        const predefined = row.attribute_value_id
                          .map((id) => attribute.values.find((value) => value.id === id)?.value)
                          .filter(Boolean) as string[]
                        const values = row.custom_value
                          ? [...predefined, row.custom_value]
                          : predefined

                        return values.length > 0
                          ? [`${attribute.name}: ${values.join(', ')}`]
                          : []
                      })
                      .slice(0, 2)

                    return (
                      <button
                        className={selected ? 'variant-list-row selected' : 'variant-list-row'}
                        type="button"
                        onClick={() => openVariant(variant)}
                        key={variant.id}
                      >
                        <div>
                          <strong>{variant.name}</strong>
                          <small>{variant.sku}</small>
                          {labels.length > 0 && <em>{labels.join(' · ')}</em>}
                        </div>
                        <div>
                          <strong>{formatMoney(variant.price)}</strong>
                          <small>{variant.stock} шт.</small>
                        </div>
                        <ChevronRight size={15} />
                      </button>
                    )
                  })}
                </div>
              </section>

              {variantDraftState ? (
                <VariantEditorPanel
                  draft={variantDraftState}
                  options={options}
                  canUpdate={canUpdate}
                  canDelete={Boolean(session.permissions.products?.delete)}
                  saving={variantSaving}
                  onChange={setVariantDraftState}
                  onSave={saveVariant}
                  onDelete={deleteVariant}
                  onCancel={() => setVariantDraftState(null)}
                />
              ) : (
                <section className="variant-editor-placeholder">
                  <Package size={28} />
                  <strong>Выберите торговое предложение</strong>
                  <span>Или создайте новое, чтобы задать SKU, цену, остатки и параметры вариации.</span>
                </section>
              )}
            </div>
          </div>
        )}

        {tab === 'inventory' && (
          <div className="editor-content-wide">
            <div className="editor-two-column">
              <Card title="Наличие" subtitle={options.stock_settings.warehouse_accounting_enabled ? 'Учёт остатков ведётся по складам' : 'Общий остаток товара'}>
                <div className="inventory-stack">
                  {!options.stock_settings.warehouse_accounting_enabled && !product.is_variable && (
                    <LiveField
                      label="Общий остаток"
                      value={draft.stock}
                      onChange={(value) => setDraft({ ...draft, stock: value })}
                      inputMode="decimal"
                    />
                  )}

                  {!options.stock_settings.warehouse_accounting_enabled && product.is_variable && (
                    <div className="info-line">
                      <span>Общий остаток</span>
                      <strong>{product.variants.reduce((sum, variant) => sum + Number(variant.stock || 0), 0)} шт.</strong>
                      <small>Сумма остатков вариаций. Меняется в торговых предложениях.</small>
                    </div>
                  )}

                  {options.stock_settings.warehouse_accounting_enabled && !product.is_variable && (
                    <div className="warehouse-stock-editor">
                      {draft.warehouse_stocks.map((row, index) => (
                        <div className="warehouse-stock-row" key={`${row.warehouse_id}-${index}`}>
                          <label className="field">
                            <span>Склад</span>
                            <select
                              value={row.warehouse_id}
                              disabled={!canUpdate}
                              onChange={(event) => {
                                const warehouseId = Number(event.target.value)
                                setDraft({
                                  ...draft,
                                  warehouse_stocks: draft.warehouse_stocks.map((item, rowIndex) => (
                                    rowIndex === index ? { ...item, warehouse_id: warehouseId } : item
                                  )),
                                })
                              }}
                            >
                              {options.warehouses.map((warehouse) => (
                                <option
                                  value={warehouse.id}
                                  disabled={draft.warehouse_stocks.some((item, rowIndex) => rowIndex !== index && item.warehouse_id === warehouse.id)}
                                  key={warehouse.id}
                                >
                                  {warehouse.name}{warehouse.external_id ? ` · 1С: ${warehouse.external_id}` : ''}
                                </option>
                              ))}
                            </select>
                          </label>
                          <LiveField
                            label="Количество"
                            value={row.quantity}
                            onChange={(value) => {
                              setDraft({
                                ...draft,
                                warehouse_stocks: draft.warehouse_stocks.map((item, rowIndex) => (
                                  rowIndex === index ? { ...item, quantity: value } : item
                                )),
                              })
                            }}
                            inputMode="decimal"
                          />
                          <button
                            className="icon-danger warehouse-remove"
                            type="button"
                            onClick={() => setDraft({
                              ...draft,
                              warehouse_stocks: draft.warehouse_stocks.filter((_, rowIndex) => rowIndex !== index),
                            })}
                            disabled={!canUpdate}
                            aria-label="Удалить склад"
                          >
                            <Trash2 size={16} />
                          </button>
                        </div>
                      ))}

                      <button
                        className="btn ghost small"
                        type="button"
                        onClick={addWarehouseRow}
                        disabled={!canUpdate || draft.warehouse_stocks.length >= options.warehouses.length}
                      >
                        <Plus size={15} />
                        Добавить склад
                      </button>
                    </div>
                  )}

                  {options.stock_settings.warehouse_accounting_enabled && product.is_variable && (
                    <div className="callout muted compact-callout">
                      <Warehouse size={17} />
                      <div>
                        <strong>Остатки хранятся у вариаций</strong>
                        <span>Для вариативного товара складские остатки меняются в каждой вариации отдельно.</span>
                      </div>
                    </div>
                  )}

                  <label className="switch-line">
                    <input
                      type="checkbox"
                      checked={draft.backorder}
                      onChange={(event) => setDraft({ ...draft, backorder: event.target.checked })}
                      disabled={!canUpdate}
                    />
                    <span>
                      <strong>Разрешить предзаказ</strong>
                      <small>Покупатель сможет оформить товар при нулевом остатке.</small>
                    </span>
                  </label>

                  <div className="info-line">
                    <span>Продано</span>
                    <strong>{product.units_sold} шт.</strong>
                    <small>Системное значение, вручную не редактируется.</small>
                  </div>
                </div>
              </Card>

              <Card title="Габариты и вес" subtitle="Физические параметры для расчёта доставки, не коммерческий размер вариации">
                <div className="form-grid readable">
                  <LiveField label="Длина" value={draft.length} suffix="см" onChange={(value) => setDraft({ ...draft, length: value })} inputMode="decimal" />
                  <LiveField label="Ширина" value={draft.width} suffix="см" onChange={(value) => setDraft({ ...draft, width: value })} inputMode="decimal" />
                  <LiveField label="Высота" value={draft.height} suffix="см" onChange={(value) => setDraft({ ...draft, height: value })} inputMode="decimal" />
                  <LiveField label="Вес" value={draft.weight} suffix="кг" onChange={(value) => setDraft({ ...draft, weight: value })} inputMode="decimal" />
                </div>
              </Card>
            </div>

            <Card title="Налоги и доставка" subtitle="Системные категории Vanilo, используемые расчётами">
              <div className="form-grid readable">
                <label className="field">
                  <span>Категория налога</span>
                  <select
                    value={draft.tax_category_id}
                    onChange={(event) => setDraft({ ...draft, tax_category_id: event.target.value })}
                    disabled={!canUpdate}
                  >
                    <option value="">Не выбрана</option>
                    {options.tax_categories.map((item) => (
                      <option value={item.id} key={item.id}>{item.name}</option>
                    ))}
                  </select>
                </label>

                <label className="field">
                  <span>Категория доставки</span>
                  <select
                    value={draft.shipping_category_id}
                    onChange={(event) => setDraft({ ...draft, shipping_category_id: event.target.value })}
                    disabled={!canUpdate}
                  >
                    <option value="">Не выбрана</option>
                    {options.shipping_categories.map((item) => (
                      <option value={item.id} key={item.id}>{item.name}</option>
                    ))}
                  </select>
                </label>
              </div>
            </Card>
          </div>
        )}

        {tab === 'media' && (
          <div className="editor-content-wide">
            <div className="editor-two-column media-layout">
              <Card title="Главное изображение" subtitle="Используется реальная коллекция модели «images»">
                <div className="main-media-editor">
                  {mainImage ? (
                    <div className="media-preview main">
                      <img src={mainImage.thumb_url || mainImage.url} alt={mainImage.name} />
                      <div>
                        <strong>{mainImage.file_name}</strong>
                        <a href={mainImage.url} target="_blank" rel="noreferrer">Открыть оригинал</a>
                      </div>
                      <button
                        className="icon-danger"
                        type="button"
                        onClick={() => deleteMedia(mainImage.id)}
                        disabled={!canUpdate || mediaSaving}
                        aria-label="Удалить главное изображение"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  ) : (
                    <div className="media-empty">Главное изображение не загружено</div>
                  )}

                  <label className={canUpdate ? 'media-upload' : 'media-upload disabled'}>
                    <Plus size={17} />
                    <span>{mainImage ? 'Заменить изображение' : 'Загрузить изображение'}</span>
                    <small>JPEG, PNG или WebP · до 10 МБ</small>
                    <input
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      disabled={!canUpdate || mediaSaving}
                      onChange={(event) => {
                        const file = event.target.files?.[0] ?? null
                        void uploadMedia('images', file)
                        event.currentTarget.value = ''
                      }}
                    />
                  </label>
                </div>
              </Card>

              <Card title="Галерея" subtitle="Дополнительные фотографии товара">
                <div className="gallery-editor">
                  {galleryImages.length === 0 ? (
                    <div className="media-empty">Галерея пока пустая</div>
                  ) : (
                    <div className="gallery-grid">
                      {galleryImages.map((media) => (
                        <article className="gallery-item" key={media.id}>
                          <img src={media.thumb_url || media.url} alt={media.name} />
                          <div>
                            <span>{media.file_name}</span>
                            <button
                              className="icon-danger"
                              type="button"
                              onClick={() => deleteMedia(media.id)}
                              disabled={!canUpdate || mediaSaving}
                              aria-label="Удалить изображение"
                            >
                              <Trash2 size={14} />
                            </button>
                          </div>
                        </article>
                      ))}
                    </div>
                  )}

                  <label className={canUpdate ? 'media-upload compact' : 'media-upload compact disabled'}>
                    <Plus size={16} />
                    <span>Добавить в галерею</span>
                    <input
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      disabled={!canUpdate || mediaSaving}
                      onChange={(event) => {
                        const file = event.target.files?.[0] ?? null
                        void uploadMedia('gallery', file)
                        event.currentTarget.value = ''
                      }}
                    />
                  </label>
                </div>
              </Card>
            </div>

            <div className="callout muted compact-callout">
              <AlertTriangle size={17} />
              <div>
                <strong>Исправлено расхождение старой формы</strong>
                <span>Новая панель использует «images» для главной фотографии и «gallery» для галереи — именно эти коллекции зарегистрированы в модели Product и читаются сайтом.</span>
              </div>
            </div>
          </div>
        )}

        {tab === 'seo' && (
          <div className="editor-content-wide">
            <div className="editor-two-column">
              <Card title="1С" subtitle="Идентификатор и синхронизация">
                <dl className="detail-list">
                  <div><dt>External ID</dt><dd>{product.external_id || '—'}</dd></div>
                </dl>
                <div className="callout muted compact-callout">
                  <AlertTriangle size={17} />
                  <div>
                    <strong>Импорт из 1С пока не запускается из React</strong>
                    <span>По аудиту текущая операция Filament смешивает импорт и повторное сохранение формы. Сначала выносим её в отдельную серверную команду.</span>
                  </div>
                </div>
              </Card>

              <Card title="SEO" subtitle="Динамические значения уже формируются MetaUniversalSEO">
                <dl className="detail-list">
                  <div><dt>Заголовок</dt><dd>{draft.name ? `${draft.name} – Светофор Мебели` : '—'}</dd></div>
                  <div><dt>URL</dt><dd>{draft.slug ? `/product/${draft.slug}` : '—'}</dd></div>
                  <div><dt>Описание</dt><dd>{draft.description ? draft.description.replace(/<[^>]*>/g, '').slice(0, 160) : '—'}</dd></div>
                </dl>
                <small className="section-note">Индивидуальные SEO-переопределения из пакета laravel-seo подключим отдельным API после сверки его persisted-модели.</small>
              </Card>
            </div>
          </div>
        )}

        {tab === 'links' && (
          <div className="editor-content-wide">
            <div className="relation-grid">
              <article className="relation-card">
                <span><Package size={18} /></span>
                <div>
                  <small>Сопутствующие товары</small>
                  <strong>Отдельная связь</strong>
                  <p>product_related_products, симметричная привязка.</p>
                </div>
                <ChevronRight size={16} />
              </article>
              <article className="relation-card">
                <span><Package size={18} /></span>
                <div>
                  <small>Набор / комплект</small>
                  <strong>Отдельная связь</strong>
                  <p>product_bundle_products с собственным порядком.</p>
                </div>
                <ChevronRight size={16} />
              </article>
              <article className="relation-card">
                <span><MapPin size={18} /></span>
                <div>
                  <small>Региональные правила</small>
                  <strong>Отдельная логика</strong>
                  <p>Цена, видимость и срок доставки по локациям.</p>
                </div>
                <ChevronRight size={16} />
              </article>
            </div>
          </div>
        )}
      </div>

      <footer className="editor-foot">
        <span>
          {tab === 'attributes'
            ? 'Характеристики сохраняются отдельной кнопкой внутри вкладки.'
            : tab === 'media'
              ? 'Загрузка, замена и удаление изображений сохраняются сразу.'
              : tabUsesProductSave
                ? canUpdate
                  ? 'Изменения этой вкладки сохраняются кнопкой справа.'
                  : 'У пользователя нет разрешения update products.'
                : 'На этой вкладке пока нет изменяющих операций.'}
        </span>

        {tabUsesProductSave && (
          <div>
            <button
              className="btn ghost"
              type="button"
              onClick={() => setDraft(productDraft(product))}
              disabled={saving || !canUpdate}
            >
              Сбросить
            </button>
            <button
              className="btn primary"
              type="button"
              onClick={saveProduct}
              disabled={saving || !canUpdate}
            >
              {saving ? <Loader2 className="spin" size={15} /> : null}
              {saving ? 'Сохраняю…' : 'Сохранить изменения'}
            </button>
          </div>
        )}
      </footer>
    </section>
  )
}

function VariantEditorPanel({
  draft,
  options,
  canUpdate,
  canDelete,
  saving,
  onChange,
  onSave,
  onDelete,
  onCancel,
}: {
  draft: VariantDraft
  options: ProductEditorOptions
  canUpdate: boolean
  canDelete: boolean
  saving: boolean
  onChange: (draft: VariantDraft) => void
  onSave: () => void
  onDelete: () => void
  onCancel: () => void
}) {
  const updateAttribute = (
    attributeId: number,
    patch: Partial<ProductAttributeRow>,
  ) => {
    onChange({
      ...draft,
      attributes: draft.attributes.map((row) => (
        row.attribute_id === attributeId ? { ...row, ...patch } : row
      )),
    })
  }

  const addWarehouse = () => {
    const used = new Set(draft.warehouse_stocks.map((row) => row.warehouse_id))
    const warehouse = options.warehouses.find((item) => !used.has(item.id))
    if (!warehouse) return

    onChange({
      ...draft,
      warehouse_stocks: [
        ...draft.warehouse_stocks,
        { warehouse_id: warehouse.id, quantity: '0' },
      ],
    })
  }

  return (
    <section className="variant-editor-panel">
      <header className="variant-editor-head">
        <div>
          <span className="eyebrow">
            {draft.id === null ? 'Новое торговое предложение' : `Торговое предложение #${draft.id}`}
          </span>
          <h3>{draft.name || 'Без названия'}</h3>
        </div>
        <button className="icon-btn" type="button" onClick={onCancel} aria-label="Закрыть редактор">
          <X size={16} />
        </button>
      </header>

      <div className="variant-editor-scroll">
        <div className="editor-two-column">
          <Card title="Основные данные" subtitle="Идентичность, цена и публикация вариации">
            <div className="form-grid readable">
              <LiveField
                label="Название вариации"
                value={draft.name}
                required
                onChange={(value) => onChange({ ...draft, name: value })}
              />
              <LiveField
                label="SKU"
                value={draft.sku}
                required
                onChange={(value) => onChange({ ...draft, sku: value })}
              />
              <LiveField
                label="Цена"
                value={draft.price}
                required
                suffix="₽"
                inputMode="decimal"
                onChange={(value) => onChange({ ...draft, price: value })}
              />
              <LiveField
                label="Цена до скидки"
                value={draft.original_price}
                suffix="₽"
                inputMode="decimal"
                onChange={(value) => onChange({ ...draft, original_price: value })}
              />
              <label className="field">
                <span>Статус <b>*</b></span>
                <select
                  value={draft.state}
                  disabled={!canUpdate}
                  onChange={(event) => onChange({ ...draft, state: event.target.value })}
                >
                  <option value="active">Активен</option>
                  <option value="draft">Черновик</option>
                  <option value="inactive">Неактивен</option>
                </select>
              </label>
              <LiveField
                label="Внешний ID 1С"
                value={draft.external_id}
                onChange={(value) => onChange({ ...draft, external_id: value })}
              />
            </div>

            <label className="switch-line variant-backorder">
              <input
                type="checkbox"
                checked={draft.backorder}
                disabled={!canUpdate}
                onChange={(event) => onChange({ ...draft, backorder: event.target.checked })}
              />
              <span>
                <strong>Разрешить предзаказ</strong>
                <small>Можно оформить вариацию при нулевом остатке.</small>
              </span>
            </label>
          </Card>

          <Card title="Остатки" subtitle={options.stock_settings.warehouse_accounting_enabled ? 'По складам' : 'Общий остаток'}>
            {!options.stock_settings.warehouse_accounting_enabled ? (
              <LiveField
                label="Остаток"
                value={draft.stock}
                inputMode="decimal"
                onChange={(value) => onChange({ ...draft, stock: value })}
              />
            ) : (
              <div className="warehouse-stock-editor">
                {draft.warehouse_stocks.map((row, index) => (
                  <div className="warehouse-stock-row" key={`${row.warehouse_id}-${index}`}>
                    <label className="field">
                      <span>Склад</span>
                      <select
                        value={row.warehouse_id}
                        disabled={!canUpdate}
                        onChange={(event) => {
                          const warehouseId = Number(event.target.value)
                          onChange({
                            ...draft,
                            warehouse_stocks: draft.warehouse_stocks.map((item, rowIndex) => (
                              rowIndex === index ? { ...item, warehouse_id: warehouseId } : item
                            )),
                          })
                        }}
                      >
                        {options.warehouses.map((warehouse) => (
                          <option
                            value={warehouse.id}
                            disabled={draft.warehouse_stocks.some((item, rowIndex) => (
                              rowIndex !== index && item.warehouse_id === warehouse.id
                            ))}
                            key={warehouse.id}
                          >
                            {warehouse.name}
                          </option>
                        ))}
                      </select>
                    </label>
                    <LiveField
                      label="Количество"
                      value={row.quantity}
                      inputMode="decimal"
                      onChange={(value) => onChange({
                        ...draft,
                        warehouse_stocks: draft.warehouse_stocks.map((item, rowIndex) => (
                          rowIndex === index ? { ...item, quantity: value } : item
                        )),
                      })}
                    />
                    <button
                      className="icon-danger warehouse-remove"
                      type="button"
                      aria-label="Удалить склад"
                      disabled={!canUpdate}
                      onClick={() => onChange({
                        ...draft,
                        warehouse_stocks: draft.warehouse_stocks.filter((_, rowIndex) => rowIndex !== index),
                      })}
                    >
                      <Trash2 size={15} />
                    </button>
                  </div>
                ))}

                <button
                  className="btn ghost small"
                  type="button"
                  onClick={addWarehouse}
                  disabled={!canUpdate || draft.warehouse_stocks.length >= options.warehouses.length}
                >
                  <Plus size={14} />
                  Добавить склад
                </button>
              </div>
            )}
          </Card>
        </div>

        <Card title="Параметры вариации" subtitle="Цвет, размер, вариант и другие свойства торгового предложения">
          <div className="variant-attribute-editor">
            {options.variation_attributes.map((attribute) => {
              const row = draft.attributes.find((item) => item.attribute_id === attribute.id)
                ?? { attribute_id: attribute.id, attribute_value_id: [], custom_value: '' }
              const required = attribute.is_required || attribute.slug === 'variant'
              const showCustom = attribute.allow_custom_value
                && (attribute.values.length === 0 || attribute.type !== 'select')

              return (
                <div className="variant-attribute-row" key={attribute.id}>
                  <div className="variant-attribute-label">
                    <strong>{attribute.name}{required ? ' *' : ''}</strong>
                    <small>{attribute.slug}</small>
                  </div>

                  <div className="variant-attribute-control">
                    {attribute.values.length > 0 && !attribute.is_multiple && (
                      <label className="field">
                        <span>Значение из справочника</span>
                        <select
                          value={row.attribute_value_id[0] ?? ''}
                          disabled={!canUpdate}
                          onChange={(event) => updateAttribute(attribute.id, {
                            attribute_value_id: event.target.value ? [Number(event.target.value)] : [],
                            custom_value: '',
                          })}
                        >
                          <option value="">Не выбрано</option>
                          {attribute.values.map((value) => (
                            <option value={value.id} key={value.id}>{value.value}</option>
                          ))}
                        </select>
                      </label>
                    )}

                    {attribute.values.length > 0 && attribute.is_multiple && (
                      <div className="attribute-multiple-field">
                        <span>Значения</span>
                        <div className="attribute-choice-grid">
                          {attribute.values.map((value) => {
                            const selected = row.attribute_value_id.includes(value.id)
                            return (
                              <button
                                className={selected ? 'attribute-choice selected' : 'attribute-choice'}
                                type="button"
                                disabled={!canUpdate}
                                onClick={() => {
                                  const next = selected
                                    ? row.attribute_value_id.filter((id) => id !== value.id)
                                    : [...row.attribute_value_id, value.id]
                                  updateAttribute(attribute.id, {
                                    attribute_value_id: next,
                                    custom_value: '',
                                  })
                                }}
                                key={value.id}
                              >
                                {value.color_code && <i style={{ background: value.color_code }} />}
                                {value.value}
                              </button>
                            )
                          })}
                        </div>
                      </div>
                    )}

                    {showCustom && (
                      <LiveField
                        label={attribute.values.length > 0 ? 'Или своё значение' : 'Значение'}
                        value={row.custom_value}
                        inputMode={attribute.type === 'number_input' ? 'decimal' : 'text'}
                        onChange={(value) => updateAttribute(attribute.id, {
                          custom_value: value,
                          attribute_value_id: value.trim() ? [] : row.attribute_value_id,
                        })}
                      />
                    )}

                    {attribute.values.length === 0 && !showCustom && (
                      <div className="attribute-no-values">
                        <CircleAlert size={15} />
                        В справочнике нет значений для этой характеристики.
                      </div>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
        </Card>
      </div>

      <footer className="variant-editor-actions">
        {draft.id !== null && canDelete ? (
          <button
            className="btn danger"
            type="button"
            onClick={onDelete}
            disabled={saving}
          >
            <Trash2 size={15} />
            Удалить
          </button>
        ) : <span />}

        <div>
          <button className="btn ghost" type="button" onClick={onCancel} disabled={saving}>
            Отмена
          </button>
          <button className="btn primary" type="button" onClick={onSave} disabled={!canUpdate || saving}>
            {saving ? <Loader2 className="spin" size={15} /> : <CheckCircle2 size={15} />}
            {saving ? 'Сохраняю…' : draft.id === null ? 'Создать' : 'Сохранить'}
          </button>
        </div>
      </footer>
    </section>
  )
}

function CategoryPicker({
  categories,
  selected,
  disabled,
  onChange,
}: {
  categories: TreeRow[]
  selected: number[]
  disabled: boolean
  onChange: (value: number[]) => void
}) {
  const [search, setSearch] = useState('')
  const normalized = search.trim().toLocaleLowerCase('ru-RU')
  const filtered = normalized
    ? categories.filter((category) => category.name.toLocaleLowerCase('ru-RU').includes(normalized))
    : categories

  return (
    <div className="category-picker">
      <label className="small-search">
        <Search size={15} />
        <input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder="Найти категорию"
        />
      </label>

      <div className="category-picker-selected">
        {selected.length === 0 && <span className="attribute-empty">Категории не выбраны</span>}
        {selected.map((id) => {
          const category = categories.find((item) => item.id === id)
          if (!category) return null

          return (
            <button
              type="button"
              onClick={() => !disabled && onChange(selected.filter((selectedId) => selectedId !== id))}
              disabled={disabled}
              key={id}
            >
              {category.name}
              <X size={12} />
            </button>
          )
        })}
      </div>

      <div className="category-picker-list">
        {filtered.map((category) => {
          const checked = selected.includes(category.id)
          return (
            <label
              className={checked ? 'category-picker-option selected' : 'category-picker-option'}
              style={{ paddingLeft: 8 + category.depth * 12 }}
              key={category.id}
            >
              <input
                type="checkbox"
                checked={checked}
                disabled={disabled}
                onChange={(event) => {
                  const next = event.target.checked
                    ? [...selected, category.id]
                    : selected.filter((id) => id !== category.id)
                  onChange(next)
                }}
              />
              <span>{category.name}</span>
            </label>
          )
        })}
      </div>
    </div>
  )
}

function AttributesWorkspace({ session }: { session: SessionInfo }) {
  const [attributes, setAttributes] = useState<AttributeDefinition[]>([])
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [draft, setDraft] = useState<AttributeDefinition | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  const load = () => {
    setLoading(true)
    setError(null)

    backendApi.attributes()
      .then((response) => {
        setAttributes(response.data)
        const id = selectedId ?? response.data[0]?.id ?? null
        setSelectedId(id)
        const selected = response.data.find((item) => item.id === id) ?? null
        setDraft(selected ? { ...selected } : null)
        setLoading(false)
      })
      .catch((loadError) => {
        setError(loadError instanceof Error ? loadError.message : String(loadError))
        setLoading(false)
      })
  }

  useEffect(load, [])

  const choose = (attribute: AttributeDefinition) => {
    setSelectedId(attribute.id)
    setDraft({ ...attribute })
    setError(null)
    setMessage(null)
  }

  const canUpdate = Boolean(session.permissions.attributes?.update)

  const save = async () => {
    if (!draft || !canUpdate) return

    setSaving(true)
    setError(null)
    setMessage(null)

    try {
      const response = await backendApi.updateAttribute(
        draft.id,
        {
          name: draft.name,
          slug: draft.slug,
          type: draft.type,
          is_filterable: draft.is_filterable,
          is_required: draft.is_required,
          is_use_in_variations: draft.is_use_in_variations,
          allow_custom_value: draft.allow_custom_value,
          is_multiple: draft.is_multiple,
          sort_order: draft.sort_order,
        },
        session.csrf_token,
      )

      setAttributes((current) => current.map((item) => (
        item.id === response.attribute.id ? response.attribute : item
      )))
      setDraft({ ...response.attribute })
      setMessage(response.message)
    } catch (saveError) {
      setError(saveError instanceof Error ? saveError.message : String(saveError))
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return <WorkspaceLoading text="Загружаю характеристики из БД…" />
  }

  if (error && !draft) {
    return <WorkspaceError text={error} onRetry={load} />
  }

  return (
    <div className="module-layout attributes-workspace">
      <section className="panel module-list">
        <PanelHead eyebrow="Справочник" title="Свойства" subtitle={`${attributes.length} записей из БД`} />

        <div className="module-list-scroll">
          {attributes.map((attribute) => (
            <button
              className={attribute.id === selectedId ? 'entity-row selected' : 'entity-row'}
              type="button"
              onClick={() => choose(attribute)}
              key={attribute.id}
            >
              <span className="entity-icon"><SlidersHorizontal size={17} /></span>
              <div>
                <strong>{attribute.name}</strong>
                <small>{attribute.type} · {attribute.products_count} товаров</small>
              </div>
              <ChevronRight size={16} />
            </button>
          ))}
        </div>
      </section>

      <section className="panel module-detail">
        {!draft ? (
          <EmptyState text="Характеристики не найдены." />
        ) : (
          <>
            <div className="module-detail-head">
              <div>
                <span className="eyebrow">Характеристика #{draft.id}</span>
                <h2>{draft.name}</h2>
                <p>{draft.values.length} значений · используется у {draft.products_count} товаров</p>
              </div>

              <div className="editor-actions">
                {!canUpdate && <span className="readonly-badge">Только чтение</span>}
                <button
                  className="btn primary"
                  type="button"
                  onClick={save}
                  disabled={!canUpdate || saving}
                >
                  {saving ? <Loader2 className="spin" size={15} /> : null}
                  {saving ? 'Сохраняю…' : 'Сохранить'}
                </button>
              </div>
            </div>

            <div className="module-detail-scroll">
              {error && <InlineError text={error} />}
              {message && <InlineSuccess text={message} />}

              <div className="attribute-settings-grid">
                <Card title="Основные настройки" subtitle="Реальная запись product_attributes">
                  <div className="form-grid readable">
                    <LiveField
                      label="Название"
                      value={draft.name}
                      required
                      onChange={(value) => setDraft({ ...draft, name: value })}
                    />
                    <LiveField
                      label="Slug"
                      value={draft.slug}
                      required
                      onChange={(value) => setDraft({ ...draft, slug: value })}
                    />
                    <label className="field">
                      <span>Тип <b>*</b></span>
                      <select
                        value={draft.type}
                        onChange={(event) => setDraft({ ...draft, type: event.target.value })}
                        disabled={!canUpdate}
                      >
                        <option value="select">Список</option>
                        <option value="color">Цвет</option>
                        <option value="string">Строка</option>
                        <option value="text">Текст</option>
                        <option value="number">Число (список)</option>
                        <option value="number_input">Число (ввод)</option>
                      </select>
                    </label>
                    <LiveField
                      label="Порядок"
                      value={String(draft.sort_order)}
                      onChange={(value) => setDraft({ ...draft, sort_order: Number(value || 0) })}
                      inputMode="numeric"
                    />
                  </div>
                </Card>

                <Card title="Поведение" subtitle="Переключатели реально сохраняются">
                  <div className="flag-grid">
                    <FlagToggle
                      label="Вариации"
                      text="Использовать свойство при формировании торговых предложений"
                      value={draft.is_use_in_variations}
                      disabled={!canUpdate}
                      onChange={(value) => setDraft({ ...draft, is_use_in_variations: value })}
                    />
                    <FlagToggle
                      label="Фильтр"
                      text="Показывать свойство в фильтрах каталога"
                      value={draft.is_filterable}
                      disabled={!canUpdate}
                      onChange={(value) => setDraft({ ...draft, is_filterable: value })}
                    />
                    <FlagToggle
                      label="Обязательная"
                      text="Без значения товар считается незаполненным"
                      value={draft.is_required}
                      disabled={!canUpdate}
                      onChange={(value) => setDraft({ ...draft, is_required: value })}
                    />
                    <FlagToggle
                      label="Множественная"
                      text="Разрешить несколько значений у одного товара"
                      value={draft.is_multiple}
                      disabled={!canUpdate}
                      onChange={(value) => setDraft({ ...draft, is_multiple: value })}
                    />
                    <FlagToggle
                      label="Ручное значение"
                      text="Разрешить значение вне справочника"
                      value={draft.allow_custom_value}
                      disabled={!canUpdate}
                      onChange={(value) => setDraft({ ...draft, allow_custom_value: value })}
                    />
                  </div>
                </Card>
              </div>

              <Card title="Допустимые значения" subtitle="Реальные product_attribute_values">
                {draft.values.length === 0 ? (
                  <EmptyState text="У свойства нет справочника значений." />
                ) : (
                  <div className="value-grid">
                    {draft.values.map((value) => (
                      <div className="value-card static-value" key={value.id}>
                        <span>{value.sort_order}</span>
                        <strong>{value.value}</strong>
                        <small>{value.slug}</small>
                      </div>
                    ))}
                  </div>
                )}
              </Card>
            </div>
          </>
        )}
      </section>
    </div>
  )
}

function OrdersWorkspace() {
  const [orders, setOrders] = useState<AdminOrder[]>([])
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = () => {
    setLoading(true)
    backendApi.orders()
      .then((response) => {
        setOrders(response.data)
        setSelectedId((current) => current ?? response.data[0]?.id ?? null)
        setLoading(false)
      })
      .catch((loadError) => {
        setError(loadError instanceof Error ? loadError.message : String(loadError))
        setLoading(false)
      })
  }

  useEffect(load, [])

  if (loading) return <WorkspaceLoading text="Загружаю заказы…" />
  if (error) return <WorkspaceError text={error} onRetry={load} />

  const order = orders.find((item) => item.id === selectedId) ?? null

  return (
    <div className="module-layout split-workspace">
      <section className="panel module-list wide-list">
        <PanelHead eyebrow="Очередь" title="Заказы" subtitle={`${orders.length} записей на странице`} />

        <div className="module-list-scroll">
          {orders.map((item) => (
            <button
              className={item.id === selectedId ? 'order-row selected' : 'order-row'}
              type="button"
              onClick={() => setSelectedId(item.id)}
              key={item.id}
            >
              <div>
                <strong>{item.number}</strong>
                <small>{item.contact_name || 'Без имени'} · {item.created_at ? new Date(item.created_at).toLocaleString('ru-RU') : '—'}</small>
              </div>
              <div>
                <strong>{formatMoney(item.total)}</strong>
                <small>{item.status_label}</small>
              </div>
            </button>
          ))}
        </div>
      </section>

      <section className="panel module-detail">
        {!order ? (
          <EmptyState text="Заказов нет." />
        ) : (
          <>
            <div className="module-detail-head">
              <div>
                <span className="eyebrow">Заказ #{order.id}</span>
                <h2>{order.number}</h2>
                <p>{order.contact_name || 'Без имени'} · {formatMoney(order.total)} · {order.status_label}</p>
              </div>
              <span className="readonly-badge">Только чтение</span>
            </div>

            <div className="module-detail-scroll">
              <div className="summary-grid">
                <Card title="Покупатель" subtitle="Данные из заказа">
                  <dl className="detail-list">
                    <div><dt>Имя</dt><dd>{order.contact_name || '—'}</dd></div>
                    <div><dt>Телефон</dt><dd>{order.contact_phone || '—'}</dd></div>
                    <div><dt>Email</dt><dd>{order.contact_email || '—'}</dd></div>
                  </dl>
                </Card>

                <Card title="Доставка" subtitle="Текущие связи заказа">
                  <dl className="detail-list">
                    <div><dt>Локация</dt><dd>{order.shipping_location?.name || '—'}</dd></div>
                    <div><dt>Способ</dt><dd>{order.shipping_method?.name || '—'}</dd></div>
                    <div><dt>Статус</dt><dd>{order.status_label}</dd></div>
                  </dl>
                </Card>
              </div>

              <Card title="Состав заказа" subtitle={`${order.items.length} позиций`}>
                <Table headers={['Товар', 'Количество', 'Цена', 'Сумма']}>
                  {order.items.map((item) => (
                    <tr key={item.id}>
                      <td><strong>{item.name}</strong></td>
                      <td>{item.quantity}</td>
                      <td>{formatMoney(item.price)}</td>
                      <td>{formatMoney(item.total)}</td>
                    </tr>
                  ))}
                </Table>
              </Card>

              <div className="callout muted">
                <AlertTriangle size={17} />
                <div>
                  <strong>Изменяющие действия намеренно отключены</strong>
                  <span>По аудиту сначала нужно исправить серверную целостность статусов, возвратов и пересчёта сумм.</span>
                </div>
              </div>
            </div>
          </>
        )}
      </section>
    </div>
  )
}

function StoresWorkspace() {
  const [stores, setStores] = useState<StoreRecord[]>([])
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = () => {
    setLoading(true)
    backendApi.stores()
      .then((response) => {
        setStores(response.data)
        setSelectedId((current) => current ?? response.data[0]?.id ?? null)
        setLoading(false)
      })
      .catch((loadError) => {
        setError(loadError instanceof Error ? loadError.message : String(loadError))
        setLoading(false)
      })
  }

  useEffect(load, [])

  if (loading) return <WorkspaceLoading text="Загружаю магазины…" />
  if (error) return <WorkspaceError text={error} onRetry={load} />

  const store = stores.find((item) => item.id === selectedId) ?? null

  return (
    <div className="module-layout split-workspace">
      <section className="panel module-list">
        <PanelHead eyebrow="Точки продаж" title="Магазины" subtitle={`${stores.length} записей`} />

        <div className="module-list-scroll">
          {stores.map((item) => (
            <button
              className={item.id === selectedId ? 'entity-row selected' : 'entity-row'}
              type="button"
              onClick={() => setSelectedId(item.id)}
              key={item.id}
            >
              <span className="entity-icon"><Store size={17} /></span>
              <div>
                <strong>{item.name}</strong>
                <small>{item.city || '—'} · {item.is_active ? 'активен' : 'выключен'}</small>
              </div>
              <ChevronRight size={16} />
            </button>
          ))}
        </div>
      </section>

      <section className="panel module-detail">
        {!store ? (
          <EmptyState text="Магазинов нет." />
        ) : (
          <>
            <div className="module-detail-head">
              <div>
                <span className="eyebrow">Магазин #{store.id}</span>
                <h2>{store.name}</h2>
                <p>{store.city || 'Город не указан'} · {store.address || 'Адрес не указан'}</p>
              </div>
              <span className="readonly-badge">Только чтение</span>
            </div>

            <div className="module-detail-scroll">
              <div className="summary-grid">
                <Card title="Адрес" subtitle="Реальные поля stores">
                  <dl className="detail-list">
                    <div><dt>Город</dt><dd>{store.city || '—'}</dd></div>
                    <div><dt>Адрес</dt><dd>{store.address || '—'}</dd></div>
                    <div><dt>Регион</dt><dd>{store.region?.name || '—'}</dd></div>
                    <div><dt>Координаты</dt><dd>{store.coordinates || '—'}</dd></div>
                  </dl>
                </Card>

                <Card title="Контакты" subtitle="Текущие данные магазина">
                  <dl className="detail-list">
                    <div><dt>Телефон</dt><dd>{store.phone || '—'}</dd></div>
                    <div><dt>Режим</dt><dd>{store.hours || '—'}</dd></div>
                    <div><dt>Статус</dt><dd>{store.is_active ? 'Активен' : 'Выключен'}</dd></div>
                  </dl>
                </Card>
              </div>
            </div>
          </>
        )}
      </section>
    </div>
  )
}

function LocationsWorkspace() {
  const [locations, setLocations] = useState<LocationRecord[]>([])
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = () => {
    setLoading(true)
    backendApi.locations()
      .then((response) => {
        setLocations(response.data)
        setSelectedId((current) => current ?? response.data[0]?.id ?? null)
        setLoading(false)
      })
      .catch((loadError) => {
        setError(loadError instanceof Error ? loadError.message : String(loadError))
        setLoading(false)
      })
  }

  useEffect(load, [])

  if (loading) return <WorkspaceLoading text="Загружаю локации…" />
  if (error) return <WorkspaceError text={error} onRetry={load} />

  const location = locations.find((item) => item.id === selectedId) ?? null

  return (
    <div className="module-layout split-workspace">
      <section className="panel module-list">
        <PanelHead eyebrow="География" title="Локации" subtitle={`${locations.length} записей`} />

        <div className="module-list-scroll">
          {locations.map((item) => (
            <button
              className={item.id === selectedId ? 'entity-row selected' : 'entity-row'}
              type="button"
              onClick={() => setSelectedId(item.id)}
              key={item.id}
            >
              <span className="entity-icon"><MapPin size={17} /></span>
              <div>
                <strong>{item.name}</strong>
                <small>{item.type} · {item.parent?.name || 'корень'}</small>
              </div>
              <ChevronRight size={16} />
            </button>
          ))}
        </div>
      </section>

      <section className="panel module-detail">
        {!location ? (
          <EmptyState text="Локаций нет." />
        ) : (
          <>
            <div className="module-detail-head">
              <div>
                <span className="eyebrow">{location.type}</span>
                <h2>{location.name}</h2>
                <p>{location.parent?.name ? `Родитель: ${location.parent.name}` : 'Корневая локация'}</p>
              </div>
              <span className="readonly-badge">Только чтение</span>
            </div>

            <div className="module-detail-scroll">
              <div className="summary-grid">
                <Card title="Основное" subtitle="Реальная запись shipping_locations">
                  <dl className="detail-list">
                    <div><dt>Slug</dt><dd>{location.slug}</dd></div>
                    <div><dt>Код</dt><dd>{location.code || '—'}</dd></div>
                    <div><dt>Статус</dt><dd>{location.is_active ? 'Активна' : 'Выключена'}</dd></div>
                  </dl>
                </Card>

                <Card title="Доставка" subtitle="Собственные значения локации">
                  <dl className="detail-list">
                    <div><dt>Стоимость</dt><dd>{formatMoney(location.delivery_price)}</dd></div>
                    <div><dt>Бесплатно от</dt><dd>{formatMoney(location.free_delivery_threshold)}</dd></div>
                    <div><dt>Срок</dt><dd>{location.delivery_days_min ?? '—'}–{location.delivery_days_max ?? '—'} дней</dd></div>
                  </dl>
                </Card>
              </div>
            </div>
          </>
        )}
      </section>
    </div>
  )
}

function WarehousesWorkspace() {
  const [warehouses, setWarehouses] = useState<WarehouseRecord[]>([])
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = () => {
    setLoading(true)
    setError(null)

    backendApi.warehouses()
      .then((response) => {
        setWarehouses(response.data)
        setSelectedId((current) => current ?? response.data[0]?.id ?? null)
        setLoading(false)
      })
      .catch((loadError) => {
        setError(loadError instanceof Error ? loadError.message : String(loadError))
        setLoading(false)
      })
  }

  useEffect(load, [])

  if (loading) return <WorkspaceLoading text="Загружаю склады…" />
  if (error) return <WorkspaceError text={error} onRetry={load} />

  const warehouse = warehouses.find((item) => item.id === selectedId) ?? null

  return (
    <div className="module-layout split-workspace">
      <section className="panel module-list">
        <PanelHead eyebrow="Остатки" title="Склады" subtitle={`${warehouses.length} записей`} />

        <div className="module-list-scroll">
          {warehouses.map((item) => (
            <button
              className={item.id === selectedId ? 'entity-row selected' : 'entity-row'}
              type="button"
              onClick={() => setSelectedId(item.id)}
              key={item.id}
            >
              <span className="entity-icon"><Warehouse size={17} /></span>
              <div>
                <strong>{item.name}</strong>
                <small>{item.external_id} · {item.is_active ? 'активен' : 'выключен'}</small>
              </div>
              <ChevronRight size={16} />
            </button>
          ))}
        </div>
      </section>

      <section className="panel module-detail">
        {!warehouse ? (
          <EmptyState text="Складов нет." />
        ) : (
          <>
            <div className="module-detail-head">
              <div>
                <span className="eyebrow">Склад #{warehouse.id}</span>
                <h2>{warehouse.name}</h2>
                <p>{warehouse.external_id} · {warehouse.is_active ? 'активен' : 'выключен'}</p>
              </div>
              <span className="readonly-badge">Живые данные</span>
            </div>

            <div className="module-detail-scroll">
              <div className="summary-grid">
                <Card title="Сводка" subtitle="Текущие связи склада">
                  <dl className="detail-list">
                    <div><dt>Товарных остатков</dt><dd>{warehouse.product_stocks_count}</dd></div>
                    <div><dt>Локаций</dt><dd>{warehouse.shipping_locations_count}</dd></div>
                    <div><dt>Статус</dt><dd>{warehouse.is_active ? 'Активен' : 'Выключен'}</dd></div>
                  </dl>
                </Card>

                <Card title="Локации" subtitle="Связи warehouse_shipping_location">
                  {warehouse.shipping_locations.length === 0 ? (
                    <EmptyState text="Локации не привязаны." />
                  ) : (
                    <div className="attribute-list">
                      {warehouse.shipping_locations.map((location) => (
                        <div className="attribute-row" key={location.id}>
                          <span>#{location.id}</span>
                          <strong>{location.name}</strong>
                          <small />
                        </div>
                      ))}
                    </div>
                  )}
                </Card>
              </div>

              <div className="callout muted">
                <AlertTriangle size={17} />
                <div>
                  <strong>Редактирование склада пока не включено</strong>
                  <span>Чтение уже защищено WarehousePolicy. Форму изменения подключим вместе с актуальной моделью доставки после стабилизации PR доставки.</span>
                </div>
              </div>
            </div>
          </>
        )}
      </section>
    </div>
  )
}

function LiveField({
  label,
  value,
  onChange,
  required = false,
  suffix,
  inputMode,
}: {
  label: string
  value: string
  onChange: (value: string) => void
  required?: boolean
  suffix?: string
  inputMode?: 'text' | 'numeric' | 'decimal'
}) {
  return (
    <label className="field">
      <span>{label} {required && <b>*</b>}</span>
      <div className="input-wrap">
        <input
          value={value}
          onChange={(event) => onChange(event.target.value)}
          inputMode={inputMode}
        />
        {suffix && <em>{suffix}</em>}
      </div>
    </label>
  )
}

function FlagToggle({
  label,
  text,
  value,
  disabled,
  onChange,
}: {
  label: string
  text: string
  value: boolean
  disabled: boolean
  onChange: (value: boolean) => void
}) {
  return (
    <button
      className={value ? 'flag-card enabled' : 'flag-card'}
      type="button"
      onClick={() => !disabled && onChange(!value)}
      disabled={disabled}
    >
      <span className="fake-switch"><i /></span>
      <div>
        <strong>{label}</strong>
        <small>{text}</small>
      </div>
    </button>
  )
}

function PanelHead({
  eyebrow,
  title,
  subtitle,
}: {
  eyebrow: string
  title: string
  subtitle?: string
}) {
  return (
    <header className="panel-head">
      <div>
        <span className="eyebrow">{eyebrow}</span>
        <h2>{title}</h2>
        {subtitle && <p>{subtitle}</p>}
      </div>
    </header>
  )
}

function Card({
  title,
  subtitle,
  children,
}: {
  title: string
  subtitle: string
  children: ReactNode
}) {
  return (
    <section className="card">
      <header>
        <h3>{title}</h3>
        <p>{subtitle}</p>
      </header>
      <div className="card-body">{children}</div>
    </section>
  )
}

function Table({
  headers,
  children,
}: {
  headers: string[]
  children: ReactNode
}) {
  return (
    <div className="table-wrap">
      <table>
        <thead>
          <tr>{headers.map((header) => <th key={header}>{header}</th>)}</tr>
        </thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  )
}

function Status({ value, compact = false }: { value: string; compact?: boolean }) {
  const ok = value === 'Активен' || value === 'Доставлен'

  return (
    <span className={'status ' + (ok ? 'ok ' : '') + (compact ? 'compact' : '')}>
      <i />
      {value}
    </span>
  )
}

function InlineLoading({ text }: { text: string }) {
  return (
    <div className="inline-state">
      <Loader2 className="spin" size={18} />
      <span>{text}</span>
    </div>
  )
}

function InlineError({ text }: { text: string }) {
  return (
    <div className="inline-state error-state">
      <CircleAlert size={18} />
      <span>{text}</span>
    </div>
  )
}

function InlineSuccess({ text }: { text: string }) {
  return (
    <div className="inline-state success-state">
      <CheckCircle2 size={18} />
      <span>{text}</span>
    </div>
  )
}

function EmptyState({ text }: { text: string }) {
  return (
    <div className="empty-state">
      <Package size={24} />
      <span>{text}</span>
    </div>
  )
}

function WorkspaceLoading({ text }: { text: string }) {
  return (
    <section className="panel workspace-state">
      <InlineLoading text={text} />
    </section>
  )
}

function WorkspaceError({
  text,
  onRetry,
}: {
  text: string
  onRetry: () => void
}) {
  return (
    <section className="panel workspace-state">
      <InlineError text={text} />
      <button className="btn ghost" type="button" onClick={onRetry}>
        <RefreshCw size={15} />
        Повторить
      </button>
    </section>
  )
}

function FullScreenLoading({ text }: { text: string }) {
  return (
    <div className="full-state">
      <Loader2 className="spin" size={26} />
      <strong>{text}</strong>
    </div>
  )
}

function FullScreenError({
  title,
  message,
}: {
  title: string
  message: string
}) {
  return (
    <div className="full-state">
      <CircleAlert size={28} />
      <strong>{title}</strong>
      <span>{message}</span>
    </div>
  )
}
