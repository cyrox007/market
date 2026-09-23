import { Component, type ErrorInfo, type ReactNode, useEffect, useMemo, useState } from 'react'
import {
  AlertTriangle,
  ArrowLeft,
  ArrowUpDown,
  Bell,
  Check,
  CheckCircle2,
  ChevronDown,
  ChevronRight,
  CircleAlert,
  ClipboardList,
  ExternalLink,
  Filter,
  FolderTree,
  GripVertical,
  ImagePlus,
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
  type ProductEditorAttributeOption,
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
  manufacturer_id: string
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

type MediaItem = ProductDetails['media'][number]

type QuickAttributeDraft = {
  name: string
  slug: string
  type: string
  is_filterable: boolean
  is_required: boolean
  is_use_in_variations: boolean
  allow_custom_value: boolean
  is_multiple: boolean
}

type QuickValueTarget = {
  attributeId: number
  context: 'product' | 'variant'
}

type QuickValueDraft = {
  value: string
  slug: string
  color_code: string
}

type ProductCreateDraft = {
  name: string
  sku: string
  external_id: string
  sync_from_1c: boolean
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

function editorAttributeOption(attribute: AttributeDefinition): ProductEditorAttributeOption {
  return {
    id: attribute.id,
    name: attribute.name,
    slug: attribute.slug,
    type: attribute.type,
    is_required: attribute.is_required,
    is_filterable: attribute.is_filterable,
    is_multiple: attribute.is_multiple,
    is_use_in_variations: attribute.is_use_in_variations,
    allow_custom_value: attribute.allow_custom_value,
    values: attribute.values.map((value) => ({
      id: value.id,
      value: value.value,
      slug: value.slug,
      color_code: value.color_code,
    })),
  }
}

function sortAttributeOptions(options: ProductEditorAttributeOption[]) {
  return [...options].sort((left, right) => (
    left.name.localeCompare(right.name, 'ru-RU', { sensitivity: 'base' })
  ))
}

function quickAttributeDraft(useInVariations = false): QuickAttributeDraft {
  return {
    name: '',
    slug: '',
    type: useInVariations ? 'select' : 'select',
    is_filterable: false,
    is_required: false,
    is_use_in_variations: useInVariations,
    allow_custom_value: false,
    is_multiple: false,
  }
}

function productCreateDraft(): ProductCreateDraft {
  return {
    name: '',
    sku: '',
    external_id: '',
    sync_from_1c: false,
  }
}

function productIdFromUrl(): number | null {
  const value = new URL(window.location.href).searchParams.get('product')
  const id = Number(value)

  return Number.isInteger(id) && id > 0 ? id : null
}

function writeProductIdToUrl(productId: number | null, replace = false) {
  const url = new URL(window.location.href)

  if (productId === null) {
    url.searchParams.delete('product')
  } else {
    url.searchParams.set('product', String(productId))
  }

  if (replace) {
    window.history.replaceState({}, '', url)
    return
  }

  window.history.pushState({}, '', url)
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
    manufacturer_id: product.manufacturer_id === null ? '' : String(product.manufacturer_id),
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
  const [editingProductId, setEditingProductId] = useState<number | null>(() => productIdFromUrl())
  const [sectionsOpen, setSectionsOpen] = useState(false)
  const [createDialogOpen, setCreateDialogOpen] = useState(false)
  const [createDraft, setCreateDraft] = useState<ProductCreateDraft>(productCreateDraft())
  const [creatingProduct, setCreatingProduct] = useState(false)
  const [createError, setCreateError] = useState<string | null>(null)
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
    const handlePopState = () => setEditingProductId(productIdFromUrl())
    window.addEventListener('popstate', handlePopState)

    return () => window.removeEventListener('popstate', handlePopState)
  }, [])

  const openProduct = (productId: number) => {
    setEditingProductId(productId)
    writeProductIdToUrl(productId)
  }

  const closeProduct = () => {
    setEditingProductId(null)
    writeProductIdToUrl(null)
  }

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
        onBack={closeProduct}
        onProductSaved={(saved) => {
          setProducts((current) => (
            current.some((item) => item.id === saved.id)
              ? current.map((item) => item.id === saved.id ? saved : item)
              : [saved, ...current]
          ))
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
    setSectionsOpen(false)
  }

  const clearFilters = () => {
    setSearch('')
    setStateFilter('')
    setSort('updated_desc')
    setProductsPage(1)
  }

  const openCreateDialog = () => {
    setCreateDraft(productCreateDraft())
    setCreateError(null)
    setCreateDialogOpen(true)
  }

  const createProduct = async () => {
    if (!session.permissions.products?.create) return

    const syncFromOneC = createDraft.sync_from_1c
    if (!syncFromOneC && !createDraft.name.trim()) {
      setCreateError('Укажите название товара.')
      return
    }
    if (syncFromOneC && !createDraft.external_id.trim()) {
      setCreateError('Укажите внешний ID товара в 1С.')
      return
    }

    setCreatingProduct(true)
    setCreateError(null)

    try {
      const response = await backendApi.createProduct(
        {
          name: createDraft.name.trim() || null,
          sku: createDraft.sku.trim() || null,
          external_id: createDraft.external_id.trim() || null,
          category_id: kind === 'categories' ? selectedSection?.id ?? null : null,
          sync_from_1c: syncFromOneC,
        },
        session.csrf_token,
      )

      setCreateDialogOpen(false)
      setProductsTotal((total) => total + 1)
      openProduct(response.product.id)
    } catch (error) {
      setCreateError(error instanceof Error ? error.message : String(error))
    } finally {
      setCreatingProduct(false)
    }
  }

  return (
    <>
    <div className="catalog-browser-layout">
      <section className={sectionsOpen ? 'panel section-panel catalog-sections-panel open' : 'panel section-panel catalog-sections-panel'}>
        <div className="catalog-sections-mobile-head">
          <div>
            <strong>Разделы каталога</strong>
            <small>Категории и комнаты</small>
          </div>
          <button type="button" onClick={() => setSectionsOpen(false)} aria-label="Закрыть разделы">
            <X size={16} />
          </button>
        </div>

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
          <div className="catalog-head-actions">
            <button
              className="btn ghost catalog-section-toggle"
              type="button"
              onClick={() => setSectionsOpen(true)}
            >
              <FolderTree size={15} />
              Разделы
            </button>
            {session.permissions.products?.create && (
              <button className="btn primary" type="button" onClick={openCreateDialog}>
                <Plus size={15} />
                Создать товар
              </button>
            )}
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
              onClick={() => openProduct(product.id)}
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

    <button
      className={sectionsOpen ? 'catalog-sections-backdrop open' : 'catalog-sections-backdrop'}
      type="button"
      aria-label="Закрыть разделы каталога"
      onClick={() => setSectionsOpen(false)}
    />

    {createDialogOpen && (
      <ProductCreateDialog
        draft={createDraft}
        selectedCategoryName={kind === 'categories' ? selectedSection?.name ?? null : null}
        busy={creatingProduct}
        error={createError}
        onChange={setCreateDraft}
        onCreate={() => void createProduct()}
        onClose={() => {
          if (!creatingProduct) setCreateDialogOpen(false)
        }}
      />
    )}
    </>
  )
}

function ProductCreateDialog({
  draft,
  selectedCategoryName,
  busy,
  error,
  onChange,
  onCreate,
  onClose,
}: {
  draft: ProductCreateDraft
  selectedCategoryName: string | null
  busy: boolean
  error: string | null
  onChange: (draft: ProductCreateDraft) => void
  onCreate: () => void
  onClose: () => void
}) {
  return (
    <div
      className="quick-editor-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !busy) onClose()
      }}
    >
      <section className="quick-editor-dialog product-create-dialog">
        <header>
          <div>
            <span className="eyebrow">Новый товар</span>
            <h3>{draft.sync_from_1c ? 'Создание по данным 1С' : 'Создание черновика'}</h3>
          </div>
          <button className="icon-btn" type="button" onClick={onClose} disabled={busy} aria-label="Закрыть">
            <X size={16} />
          </button>
        </header>

        <div className="quick-editor-body">
          {error && (
            <div className="backend-error" role="alert">
              <CircleAlert size={16} />
              <div>
                <strong>Не удалось создать товар</strong>
                <span>{error}</span>
              </div>
            </div>
          )}

          <label className="switch-line product-create-sync">
            <input
              type="checkbox"
              checked={draft.sync_from_1c}
              disabled={busy}
              onChange={(event) => onChange({
                ...draft,
                sync_from_1c: event.target.checked,
              })}
            />
            <span>
              <strong>Загрузить карточку из 1С</strong>
              <small>Создаст товар по external_id и сразу подтянет характеристики, вариации, цены и остатки по складам.</small>
            </span>
          </label>

          <div className="form-grid readable">
            <LiveField
              label={draft.sync_from_1c ? 'Название (необязательно)' : 'Название'}
              value={draft.name}
              required={!draft.sync_from_1c}
              onChange={(value) => onChange({ ...draft, name: value })}
            />
            <LiveField
              label="SKU (необязательно)"
              value={draft.sku}
              onChange={(value) => onChange({ ...draft, sku: value })}
            />
            <LiveField
              label="External ID 1С"
              value={draft.external_id}
              required={draft.sync_from_1c}
              onChange={(value) => onChange({ ...draft, external_id: value })}
            />
          </div>

          {selectedCategoryName && (
            <div className="callout muted compact-callout">
              <FolderTree size={16} />
              <div>
                <strong>Категория будет назначена автоматически</strong>
                <span>{selectedCategoryName}</span>
              </div>
            </div>
          )}

          {draft.sync_from_1c && (
            <div className="callout warn compact-callout">
              <RefreshCw size={16} />
              <div>
                <strong>Будет выполнена полноценная синхронизация</strong>
                <span>После создания откроется карточка с данными из 1С. Для вариативного товара остатки подтягиваются отдельно для каждой вариации по всем складам.</span>
              </div>
            </div>
          )}
        </div>

        <footer className="quick-editor-actions">
          <button className="btn ghost" type="button" onClick={onClose} disabled={busy}>
            Отмена
          </button>
          <button
            className="btn primary"
            type="button"
            onClick={onCreate}
            disabled={busy || (draft.sync_from_1c ? !draft.external_id.trim() : !draft.name.trim())}
          >
            {busy ? <Loader2 className="spin" size={15} /> : <Plus size={15} />}
            {busy ? 'Создаю…' : draft.sync_from_1c ? 'Создать из 1С' : 'Создать и открыть'}
          </button>
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
  const [oneCSaving, setOneCSaving] = useState(false)
  const [variantDraftState, setVariantDraftState] = useState<VariantDraft | null>(null)
  const [variantSaving, setVariantSaving] = useState(false)
  const [variantMessage, setVariantMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [attributesMessage, setAttributesMessage] = useState<string | null>(null)
  const [variationSelection, setVariationSelection] = useState<number[]>([])
  const [variationSelectionDirty, setVariationSelectionDirty] = useState(false)
  const [variationSelectionSaving, setVariationSelectionSaving] = useState(false)
  const [variationAttributeSearch, setVariationAttributeSearch] = useState('')
  const [quickAttributeMode, setQuickAttributeMode] = useState<'product' | 'variation' | null>(null)
  const [quickAttributeState, setQuickAttributeState] = useState<QuickAttributeDraft>(quickAttributeDraft())
  const [quickValueTarget, setQuickValueTarget] = useState<QuickValueTarget | null>(null)
  const [quickValueState, setQuickValueState] = useState<QuickValueDraft>({ value: '', slug: '', color_code: '' })
  const [dictionarySaving, setDictionarySaving] = useState(false)

  const flatCategories = useMemo(() => flattenTree(categories), [categories])

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)
    setMessage(null)
    setAttributesMessage(null)
    setVariantMessage(null)
    setVariantDraftState(null)

    backendApi.productEditor(productId)
      .then((editorResponse) => {
        if (cancelled) return

        if (editorResponse.contract_version !== 2) {
          throw new Error(
            `Backend редактора товара использует устаревший контракт (ожидался v2, получено ${String(editorResponse.contract_version ?? 'без версии')}). Выполните php artisan optimize:clear.`,
          )
        }

        const loadedProduct = editorResponse.product
        const editorOptions = editorResponse.options

        if (!loadedProduct || Number(loadedProduct.id) !== productId) {
          throw new Error(
            `Backend вернул некорректную карточку товара #${productId}: отсутствует product.id или id не совпадает.`,
          )
        }

        if (!editorOptions
          || !Array.isArray(editorOptions.attributes)
          || !Array.isArray(editorOptions.variation_attributes)
          || !Array.isArray(editorOptions.available_variation_attributes)
          || !Array.isArray(editorOptions.warehouses)) {
          throw new Error(
            `Backend вернул неполные справочники редактора товара #${productId}.`,
          )
        }

        if (typeof loadedProduct.name !== 'string'
          || typeof loadedProduct.sku !== 'string'
          || typeof loadedProduct.state !== 'string'
          || !Number.isFinite(Number(loadedProduct.price))
          || !Array.isArray(loadedProduct.category_ids)
          || !Array.isArray(loadedProduct.attribute_rows)
          || !Array.isArray(loadedProduct.variants)) {
          throw new Error(
            `Backend вернул неполную карточку товара #${productId}. Редактирование остановлено, чтобы не затереть существующие данные.`,
          )
        }

        setProduct(loadedProduct)
        setDraft(productDraft(loadedProduct))
        setOptions(editorOptions)
        setAttributeRows(loadedProduct.attribute_rows.map((row) => ({
          attribute_id: row.attribute_id,
          attribute_value_id: [...row.attribute_value_id],
          custom_value: row.custom_value,
        })))

        const selectedVariationIds = loadedProduct.variation_attribute_ids.length > 0
          ? [...loadedProduct.variation_attribute_ids]
          : editorOptions.variation_attributes.map((attribute) => attribute.id)

        setVariationSelection(Array.from(new Set(selectedVariationIds)))
        setVariationSelectionDirty(false)
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
  const canCreateAttributes = Boolean(session.permissions.attributes?.create)
  const canUpdateAttributes = Boolean(session.permissions.attributes?.update)
  const systemVariantAttribute = options.available_variation_attributes.find(
    (attribute) => attribute.slug === 'variant',
  )
  const activeVariationAttributes = options.available_variation_attributes.filter(
    (attribute) => attribute.slug === 'variant' || variationSelection.includes(attribute.id),
  )
  const variantEditorOptions: ProductEditorOptions = {
    ...options,
    variation_attributes: activeVariationAttributes,
  }
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

  const saveProduct = async (exitAfterSave = false) => {
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
          manufacturer_id: draft.manufacturer_id ? Number(draft.manufacturer_id) : null,
          warehouse_stocks: draft.warehouse_stocks.map((row) => ({
            warehouse_id: row.warehouse_id,
            quantity: Number(row.quantity.replace(',', '.') || 0),
          })),
        },
        session.csrf_token,
      )

      applySavedProduct(response.product)
      setMessage(response.message)

      if (exitAfterSave) {
        onBack()
      }
    } catch (saveError) {
      setError(saveError instanceof Error ? saveError.message : String(saveError))
    } finally {
      setSaving(false)
    }
  }

  const syncFromOneC = async () => {
    if (!canUpdate || !product.external_id) return

    const confirmed = window.confirm(
      'Синхронизация из 1С может изменить карточку товара, категории, производителя, характеристики, остатки и вариации. Продолжить?',
    )
    if (!confirmed) return

    setOneCSaving(true)
    setError(null)
    setMessage(null)

    try {
      const response = await backendApi.syncProductFromOneC(
        product.id,
        session.csrf_token,
      )

      applySavedProduct(response.product)
      setMessage(response.message)
    } catch (syncError) {
      setError(syncError instanceof Error ? syncError.message : String(syncError))
    } finally {
      setOneCSaving(false)
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

  const patchAttributeOption = (attribute: AttributeDefinition) => {
    const option = editorAttributeOption(attribute)

    setOptions((current) => {
      if (!current) return current

      const replaceIn = (items: ProductEditorAttributeOption[]) => {
        const exists = items.some((item) => item.id === option.id)
        const next = exists
          ? items.map((item) => item.id === option.id ? option : item)
          : [...items, option]

        return sortAttributeOptions(next)
      }

      const regularAttributes = option.is_use_in_variations && product.is_variable
        ? current.attributes.filter((item) => item.id !== option.id)
        : replaceIn(current.attributes)

      return {
        ...current,
        attributes: regularAttributes,
        available_variation_attributes: option.is_use_in_variations
          ? replaceIn(current.available_variation_attributes)
          : current.available_variation_attributes.filter((item) => item.id !== option.id),
        variation_attributes: current.variation_attributes.some((item) => item.id === option.id)
          ? replaceIn(current.variation_attributes)
          : current.variation_attributes,
      }
    })

    return option
  }

  const openQuickAttribute = (mode: 'product' | 'variation') => {
    setQuickAttributeMode(mode)
    setQuickAttributeState(quickAttributeDraft(mode === 'variation'))
    setError(null)
  }

  const saveQuickAttribute = async () => {
    if (!quickAttributeMode || !canCreateAttributes || !quickAttributeState.name.trim()) return

    setDictionarySaving(true)
    setError(null)

    try {
      const response = await backendApi.createAttribute(
        {
          ...quickAttributeState,
          name: quickAttributeState.name.trim(),
          slug: quickAttributeState.slug.trim() || null,
          is_use_in_variations: quickAttributeMode === 'variation'
            ? true
            : quickAttributeState.is_use_in_variations,
        },
        session.csrf_token,
      )

      const option = patchAttributeOption(response.attribute)

      if (option.is_use_in_variations || quickAttributeMode === 'variation') {
        setVariationSelection((current) => Array.from(new Set([...current, option.id])))
        setVariationSelectionDirty(true)
        setTab('variants')
      } else {
        setAttributeRows((current) => [
          ...current,
          {
            attribute_id: option.id,
            attribute_value_id: [],
            custom_value: '',
          },
        ])
      }

      setQuickAttributeMode(null)
      setAttributesMessage(response.message)
    } catch (createError) {
      setError(createError instanceof Error ? createError.message : String(createError))
    } finally {
      setDictionarySaving(false)
    }
  }

  const openQuickValue = (attributeId: number, context: 'product' | 'variant') => {
    setQuickValueTarget({ attributeId, context })
    setQuickValueState({ value: '', slug: '', color_code: '' })
    setError(null)
  }

  const saveQuickValue = async () => {
    if (!quickValueTarget || !quickValueState.value.trim()) return

    setDictionarySaving(true)
    setError(null)

    try {
      const response = await backendApi.createAttributeValue(
        quickValueTarget.attributeId,
        {
          value: quickValueState.value.trim(),
          slug: quickValueState.slug.trim() || null,
          color_code: quickValueState.color_code.trim() || null,
        },
        session.csrf_token,
      )

      const option = patchAttributeOption(response.attribute)
      const createdValue = option.values[option.values.length - 1]

      if (createdValue && quickValueTarget.context === 'product') {
        setAttributeRows((current) => current.map((row) => {
          if (row.attribute_id !== option.id) return row

          return {
            ...row,
            attribute_value_id: option.is_multiple
              ? Array.from(new Set([...row.attribute_value_id, createdValue.id]))
              : [createdValue.id],
            custom_value: '',
          }
        }))
      }

      if (createdValue && quickValueTarget.context === 'variant') {
        setVariantDraftState((current) => {
          if (!current) return current

          return {
            ...current,
            attributes: current.attributes.map((row) => (
              row.attribute_id === option.id
                ? {
                    ...row,
                    attribute_value_id: option.is_multiple
                      ? Array.from(new Set([...row.attribute_value_id, createdValue.id]))
                      : [createdValue.id],
                    custom_value: '',
                  }
                : row
            )),
          }
        })
      }

      setQuickValueTarget(null)
      setAttributesMessage(response.message)
    } catch (createError) {
      setError(createError instanceof Error ? createError.message : String(createError))
    } finally {
      setDictionarySaving(false)
    }
  }

  const saveVariationSelection = async () => {
    if (!canUpdate || !variationSelectionDirty) return

    setVariationSelectionSaving(true)
    setError(null)
    setVariantMessage(null)

    try {
      const selectableIds = variationSelection.filter(
        (id) => id !== systemVariantAttribute?.id,
      )
      const response = await backendApi.updateProductVariationAttributes(
        product.id,
        selectableIds,
        session.csrf_token,
      )
      const refreshedOptions = await backendApi.productEditorOptions(product.id)

      applySavedProduct(response.product)
      setOptions(refreshedOptions)
      setVariationSelection([...response.product.variation_attribute_ids])
      setVariationSelectionDirty(false)
      setVariantDraftState(null)
      setVariantMessage(response.message)
    } catch (saveError) {
      setError(saveError instanceof Error ? saveError.message : String(saveError))
    } finally {
      setVariationSelectionSaving(false)
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
    if (variationSelectionDirty) return

    setVariantDraftState(variantDraft(product, variantEditorOptions, variant))
    setVariantMessage(null)
    setError(null)
  }

  const createVariant = () => {
    if (variationSelectionDirty) return

    setVariantDraftState(variantDraft(product, variantEditorOptions))
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
          ? variantDraft(response.product, variantEditorOptions, savedVariant)
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

  const uploadVariantMedia = async (
    collection: 'images' | 'gallery',
    files: File[],
  ) => {
    if (files.length === 0 || !variantDraftState?.id || !canUpdate) return

    const selected = collection === 'images'
      ? files.slice(0, 1)
      : files.slice(0, Math.max(0, 20 - (selectedVariant?.media.filter((item) => item.collection === 'gallery').length ?? 0)))

    if (selected.length === 0) {
      setError('В галерее уже 20 изображений.')
      return
    }

    setVariantSaving(true)
    setError(null)
    setVariantMessage(null)

    try {
      let latest: { message: string; product: ProductDetails } | null = null

      for (const file of selected) {
        latest = await backendApi.uploadProductVariantMedia(
          product.id,
          variantDraftState.id,
          collection,
          file,
          session.csrf_token,
        )
      }

      if (!latest) return

      applySavedProduct(latest.product)
      const savedVariant = latest.product.variants.find((item) => item.id === variantDraftState.id)
      if (savedVariant) {
        setVariantDraftState(variantDraft(latest.product, variantEditorOptions, savedVariant))
      }
      setVariantMessage(
        collection === 'gallery' && selected.length > 1
          ? `Загружено изображений: ${selected.length}`
          : latest.message,
      )
    } catch (variantError) {
      setError(variantError instanceof Error ? variantError.message : String(variantError))
    } finally {
      setVariantSaving(false)
    }
  }

  const deleteVariantMedia = async (mediaId: number) => {
    if (!variantDraftState?.id || !canUpdate) return

    setVariantSaving(true)
    setError(null)
    setVariantMessage(null)

    try {
      const response = await backendApi.deleteProductVariantMedia(
        product.id,
        variantDraftState.id,
        mediaId,
        session.csrf_token,
      )

      applySavedProduct(response.product)
      const savedVariant = response.product.variants.find((item) => item.id === variantDraftState.id)
      if (savedVariant) {
        setVariantDraftState(variantDraft(response.product, variantEditorOptions, savedVariant))
      }
      setVariantMessage(response.message)
    } catch (variantError) {
      setError(variantError instanceof Error ? variantError.message : String(variantError))
    } finally {
      setVariantSaving(false)
    }
  }

  const uploadMedia = async (
    collection: 'images' | 'gallery',
    files: File[],
  ) => {
    if (files.length === 0 || !canUpdate) return

    const selected = collection === 'images'
      ? files.slice(0, 1)
      : files.slice(0, Math.max(0, 20 - galleryImages.length))

    if (selected.length === 0) {
      setError('В галерее уже 20 изображений.')
      return
    }

    setMediaSaving(true)
    setError(null)
    setMessage(null)

    try {
      let latest: { message: string; product: ProductDetails } | null = null

      for (const file of selected) {
        latest = await backendApi.uploadProductMedia(
          product.id,
          collection,
          file,
          session.csrf_token,
        )
      }

      if (!latest) return

      applySavedProduct(latest.product)
      setMessage(
        collection === 'gallery' && selected.length > 1
          ? `Загружено изображений: ${selected.length}`
          : latest.message,
      )
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

  const reorderMedia = async (mediaIds: number[]) => {
    if (!canUpdate || mediaSaving) return

    setMediaSaving(true)
    setError(null)

    try {
      const response = await backendApi.reorderProductMedia(
        product.id,
        mediaIds,
        session.csrf_token,
      )
      applySavedProduct(response.product)
      setMessage(response.message)
    } catch (mediaError) {
      setError(mediaError instanceof Error ? mediaError.message : String(mediaError))
      throw mediaError
    } finally {
      setMediaSaving(false)
    }
  }

  const reorderVariantMedia = async (mediaIds: number[]) => {
    if (!canUpdate || variantSaving || !variantDraftState?.id) return

    setVariantSaving(true)
    setError(null)

    try {
      const response = await backendApi.reorderProductVariantMedia(
        product.id,
        variantDraftState.id,
        mediaIds,
        session.csrf_token,
      )
      applySavedProduct(response.product)
      const savedVariant = response.product.variants.find((item) => item.id === variantDraftState.id)
      if (savedVariant) {
        setVariantDraftState(variantDraft(response.product, variantEditorOptions, savedVariant))
      }
      setVariantMessage(response.message)
    } catch (mediaError) {
      setError(mediaError instanceof Error ? mediaError.message : String(mediaError))
      throw mediaError
    } finally {
      setVariantSaving(false)
    }
  }

  const variantAttributes = product.attributes.filter((attribute) => attribute.source === 'variants')
  const selectedVariant = variantDraftState?.id === null || variantDraftState === null
    ? null
    : product.variants.find((variant) => variant.id === variantDraftState.id) ?? null
  const tabUsesProductSave = tab === 'main' || tab === 'description' || tab === 'inventory'
  const mainImage = product.media.find((item) => item.collection === 'images') ?? null
  const galleryImages = product.media.filter((item) => item.collection === 'gallery')
  const quickValueAttribute = quickValueTarget
    ? (
        options.attributes.find((attribute) => attribute.id === quickValueTarget.attributeId)
        ?? options.available_variation_attributes.find((attribute) => attribute.id === quickValueTarget.attributeId)
      )
    : null

  return (
    <>
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
          {product.slug && (
            <a
              className="btn ghost small"
              href={`/product/${encodeURIComponent(product.slug)}`}
              target="_blank"
              rel="noreferrer"
              title="Открыть сохранённую карточку товара на сайте"
            >
              <ExternalLink size={14} />
              Просмотр на сайте
            </a>
          )}
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

          {error && <span className="chip error" role="status"><CircleAlert size={13} /> Есть ошибка</span>}
          {message && <span className="chip ok" role="status"><CheckCircle2 size={13} /> {message}</span>}
          {attributesMessage && <span className="chip ok" role="status"><CheckCircle2 size={13} /> {attributesMessage}</span>}
          {variantMessage && <span className="chip ok" role="status"><CheckCircle2 size={13} /> {variantMessage}</span>}
        </div>

        {error && (
          <div className="quality-problems" role="alert" aria-live="assertive">
            <div className="backend-error">
              <CircleAlert size={15} />
              <div>
                <strong>Не удалось сохранить изменения</strong>
                <span>{error}</span>
              </div>
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

            <Card title="Производитель" subtitle="Справочник производителей, в том числе загруженных из 1С">
              <label className="field">
                <span>Производитель</span>
                <select
                  value={draft.manufacturer_id}
                  onChange={(event) => setDraft({ ...draft, manufacturer_id: event.target.value })}
                  disabled={!canUpdate}
                >
                  <option value="">Не выбран</option>
                  {options.manufacturers.map((manufacturer) => (
                    <option value={manufacturer.id} key={manufacturer.id}>
                      {manufacturer.name}{manufacturer.external_id ? ` · 1С: ${manufacturer.external_id}` : ''}
                    </option>
                  ))}
                </select>
              </label>
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
                <p>Компактный список: характеристика, значение и действия в одной строке.</p>
              </div>
              <div>
                {canCreateAttributes && (
                  <button className="btn ghost" type="button" onClick={() => openQuickAttribute('product')} disabled={dictionarySaving}>
                    <Plus size={15} /> Новая характеристика
                  </button>
                )}
                <button className="btn ghost" type="button" onClick={addAttributeRow} disabled={!canUpdate || attributeRows.length >= options.attributes.length}>
                  <Plus size={15} /> Добавить
                </button>
                <button className="btn primary" type="button" onClick={saveAttributes} disabled={!canUpdate || attributesSaving}>
                  {attributesSaving ? <Loader2 className="spin" size={15} /> : <CheckCircle2 size={15} />}
                  {attributesSaving ? 'Сохраняю…' : 'Сохранить'}
                </button>
              </div>
            </div>

            {attributeRows.length === 0 ? (
              <div className="attribute-editor-empty">
                <SlidersHorizontal size={24} />
                <strong>Характеристики не добавлены</strong>
                <span>Добавьте существующую характеристику или создайте новую.</span>
              </div>
            ) : (
              <div className="attribute-table">
                <div className="attribute-table-head">
                  <span>Характеристика</span>
                  <span>Значение</span>
                  <span>Свойства</span>
                  <span />
                </div>

                {attributeRows.map((row, index) => {
                  const attribute = options.attributes.find((item) => item.id === row.attribute_id)
                  const usedIds = new Set(
                    attributeRows.filter((_, rowIndex) => rowIndex !== index).map((item) => item.attribute_id),
                  )

                  return (
                    <div className="attribute-table-row" key={`${row.attribute_id}-${index}`}>
                      <div>
                        <SearchableSelect
                          value={row.attribute_id}
                          disabled={!canUpdate}
                          placeholder="Выберите характеристику"
                          searchPlaceholder="Поиск по названию или slug"
                          options={options.attributes.map((item) => ({
                            value: item.id,
                            label: item.name,
                            hint: item.slug,
                            disabled: usedIds.has(item.id),
                          }))}
                          onChange={(attributeId) => updateAttributeRow(index, {
                            attribute_id: attributeId,
                            attribute_value_id: [],
                            custom_value: '',
                          })}
                        />
                      </div>

                      <div className="attribute-table-value">
                        {attribute && attribute.values.length > 0 && !attribute.is_multiple && (
                          <SearchableSelect
                            value={row.attribute_value_id[0] ?? null}
                            disabled={!canUpdate}
                            placeholder="Выберите значение"
                            searchPlaceholder="Найти значение"
                            options={attribute.values.map((value) => ({
                              value: value.id,
                              label: value.value,
                              swatch: value.color_code,
                            }))}
                            onChange={(valueId) => updateAttributeRow(index, {
                              attribute_value_id: [valueId],
                              custom_value: '',
                            })}
                          />
                        )}

                        {attribute && attribute.values.length > 0 && attribute.is_multiple && (
                          <SearchableMultiSelect
                            value={row.attribute_value_id}
                            disabled={!canUpdate}
                            placeholder="Выберите значения"
                            searchPlaceholder="Найти значение"
                            options={attribute.values.map((value) => ({
                              value: value.id,
                              label: value.value,
                              swatch: value.color_code,
                            }))}
                            onChange={(valueIds) => updateAttributeRow(index, {
                              attribute_value_id: valueIds,
                              custom_value: '',
                            })}
                          />
                        )}

                        {attribute?.allow_custom_value && (
                          <input
                            className="attribute-custom-input"
                            value={row.custom_value}
                            disabled={!canUpdate}
                            placeholder={attribute.values.length > 0 ? 'Или своё значение' : 'Введите значение'}
                            onChange={(event) => updateAttributeRow(index, {
                              custom_value: event.target.value,
                              attribute_value_id: event.target.value.trim() ? [] : row.attribute_value_id,
                            })}
                          />
                        )}

                        {attribute && attribute.values.length === 0 && !attribute.allow_custom_value && (
                          <span className="attribute-inline-warning">Нет значений</span>
                        )}
                      </div>

                      <div className="attribute-table-flags">
                        {attribute?.is_required && <span className="chip error">Обяз.</span>}
                        {attribute?.is_multiple && <span className="chip">Множеств.</span>}
                        {attribute?.is_filterable && <span className="chip">Фильтр</span>}
                      </div>

                      <div className="attribute-table-actions">
                        {attribute && canUpdateAttributes && (
                          <button
                            className="icon-btn"
                            type="button"
                            title="Создать новое значение"
                            onClick={() => openQuickValue(attribute.id, 'product')}
                            disabled={dictionarySaving}
                          >
                            <Plus size={15} />
                          </button>
                        )}
                        <button
                          className="icon-danger"
                          type="button"
                          title="Удалить характеристику из товара"
                          onClick={() => removeAttributeRow(index)}
                          disabled={!canUpdate}
                        >
                          <Trash2 size={15} />
                        </button>
                      </div>
                    </div>
                  )
                })}
              </div>
            )}

            {variantAttributes.length > 0 && (
              <section className="variant-attribute-panel">
                <header>
                  <div>
                    <h3>Характеристики вариаций</h3>
                    <p>Значения торговых предложений. Состав параметров меняется во вкладке «Вариации».</p>
                  </div>
                  <span>{variantAttributes.length}</span>
                </header>
                <div className="product-attribute-grid">
                  {variantAttributes.map((attribute) => (
                    <article className="product-attribute-card" key={`variant-${attribute.id}`}>
                      <header>
                        <div><strong>{attribute.name}</strong><small>{attribute.slug}</small></div>
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
            <section className="variation-config-card">
              <div className="variation-config-head">
                <div>
                  <span className="eyebrow">Параметры торговых предложений</span>
                  <h3>Чем отличаются вариации этого товара</h3>
                  <p>Например: «Цвет» и «Размер». После сохранения эти поля появятся у каждой вариации вместе с собственной ценой и остатками.</p>
                </div>
                <div className="variation-config-actions">
                  {canCreateAttributes && (
                    <button
                      className="btn ghost"
                      type="button"
                      onClick={() => openQuickAttribute('variation')}
                      disabled={dictionarySaving || variationSelectionSaving}
                    >
                      <Plus size={15} />
                      Новая характеристика
                    </button>
                  )}
                  <button
                    className="btn primary"
                    type="button"
                    onClick={saveVariationSelection}
                    disabled={!canUpdate || !variationSelectionDirty || variationSelectionSaving}
                  >
                    {variationSelectionSaving ? <Loader2 className="spin" size={15} /> : <CheckCircle2 size={15} />}
                    {variationSelectionSaving ? 'Сохраняю…' : 'Сохранить параметры'}
                  </button>
                </div>
              </div>

              <label className="small-search variation-search">
                <Search size={15} />
                <input
                  value={variationAttributeSearch}
                  onChange={(event) => setVariationAttributeSearch(event.target.value)}
                  placeholder="Найти параметр: цвет, размер…"
                />
              </label>

              <div className="variation-attribute-picker">
                {options.available_variation_attributes
                  .filter((attribute) => attribute.slug !== 'variant')
                  .filter((attribute) => {
                    const query = variationAttributeSearch.trim().toLocaleLowerCase('ru-RU')
                    if (!query) return true

                    return attribute.name.toLocaleLowerCase('ru-RU').includes(query)
                      || attribute.slug.toLocaleLowerCase('ru-RU').includes(query)
                  })
                  .map((attribute) => {
                    const selected = variationSelection.includes(attribute.id)

                    return (
                      <button
                        className={selected ? 'variation-attribute-option selected' : 'variation-attribute-option'}
                        type="button"
                        disabled={!canUpdate || variationSelectionSaving}
                        onClick={() => {
                          setVariationSelection((current) => (
                            selected
                              ? current.filter((id) => id !== attribute.id)
                              : [...current, attribute.id]
                          ))
                          setVariationSelectionDirty(true)
                          setVariantDraftState(null)
                        }}
                        key={attribute.id}
                      >
                        <span className="variation-option-check">
                          {selected && <Check size={14} />}
                        </span>
                        <span>
                          <strong>{attribute.name}</strong>
                          <small>{attribute.slug} · {attribute.values.length} значений</small>
                        </span>
                      </button>
                    )
                  })}

                {options.available_variation_attributes.filter((attribute) => attribute.slug !== 'variant').length === 0 && (
                  <div className="attribute-editor-empty compact">
                    <SlidersHorizontal size={20} />
                    <strong>Нет параметров вариаций</strong>
                    <span>Создайте, например, «Цвет» или «Размер» и включите использование в вариациях.</span>
                  </div>
                )}
              </div>

              <div className="variation-system-note">
                <CheckCircle2 size={16} />
                <span>Служебное поле «Вариант» заполняется названием торгового предложения автоматически.</span>
              </div>

              {variationSelectionDirty && (
                <div className="variation-unsaved">
                  <CircleAlert size={16} />
                  Сначала сохраните выбранные параметры. После этого можно создавать и редактировать торговые предложения.
                </div>
              )}
            </section>

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
                    disabled={!canUpdate || !session.permissions.products?.create || variationSelectionDirty}
                  >
                    <Plus size={14} />
                    Добавить вариацию
                  </button>
                </header>

                <div className="variant-list">
                  {product.variants.length === 0 && (
                    <div className="variant-list-empty">
                      Торговых предложений пока нет. Выберите параметры выше и создайте первое.
                    </div>
                  )}

                  {product.variants.map((variant) => {
                    const selected = variantDraftState?.id === variant.id
                    const labels = variant.attributes
                      .flatMap((row) => {
                        const attribute = options.available_variation_attributes.find((item) => item.id === row.attribute_id)
                        if (!attribute || attribute.slug === 'variant') return []

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
                      .slice(0, 3)

                    return (
                      <button
                        className={selected ? 'variant-list-row selected' : 'variant-list-row'}
                        type="button"
                        disabled={variationSelectionDirty}
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
                          <small>
                            {options.stock_settings.warehouse_accounting_enabled
                              ? `${variant.warehouse_stocks.reduce((sum, row) => sum + Number(row.quantity || 0), 0)} шт. по складам`
                              : `${variant.stock} шт.`}
                          </small>
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
                  options={variantEditorOptions}
                  canUpdate={canUpdate}
                  canDelete={Boolean(session.permissions.products?.delete)}
                  canUpdateAttributes={canUpdateAttributes}
                  saving={variantSaving}
                  onChange={setVariantDraftState}
                  media={selectedVariant?.media ?? []}
                  onSave={saveVariant}
                  onDelete={deleteVariant}
                  onUploadMedia={uploadVariantMedia}
                  onDeleteMedia={deleteVariantMedia}
                  onReorderMedia={reorderVariantMedia}
                  onCreateValue={(attributeId) => openQuickValue(attributeId, 'variant')}
                  onCancel={() => setVariantDraftState(null)}
                />
              ) : (
                <section className="variant-editor-placeholder">
                  <Package size={28} />
                  <strong>Выберите или создайте вариацию</strong>
                  <span>У каждой вариации можно отдельно задать цвет, размер, цену, SKU, остатки по складам и изображения.</span>
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
              <Card title="Главное изображение" subtitle="Миниатюра показывается сразу после загрузки">
                <SingleImageDropzone
                  item={mainImage}
                  disabled={!canUpdate}
                  busy={mediaSaving}
                  onFiles={(files) => void uploadMedia('images', files)}
                  onDelete={mainImage ? () => void deleteMedia(mainImage.id) : undefined}
                />
              </Card>

              <Card title="Галерея" subtitle="До 20 изображений · порядок на сайте соответствует порядку здесь">
                <GalleryManager
                  items={galleryImages}
                  maxFiles={20}
                  disabled={!canUpdate}
                  busy={mediaSaving}
                  onFiles={(files) => void uploadMedia('gallery', files)}
                  onDelete={(mediaId) => void deleteMedia(mediaId)}
                  onReorder={reorderMedia}
                />
              </Card>
            </div>

            <div className="callout muted compact-callout">
              <ImagePlus size={17} />
              <div>
                <strong>Порядок изображений сохраняется</strong>
                <span>Можно выбрать сразу несколько файлов или перетащить их в область загрузки. Карточки фотографий также перетаскиваются мышью для изменения порядка.</span>
              </div>
            </div>
          </div>
        )}

        {tab === 'seo' && (
          <div className="editor-content-wide">
            <div className="editor-two-column">
              <Card title="1С" subtitle="Отдельная серверная операция, не часть обычного сохранения">
                <dl className="detail-list">
                  <div><dt>External ID</dt><dd>{product.external_id || '—'}</dd></div>
                  <div><dt>Производитель</dt><dd>{options.manufacturers.find((item) => item.id === product.manufacturer_id)?.name || '—'}</dd></div>
                </dl>

                <div className="callout warn compact-callout">
                  <AlertTriangle size={17} />
                  <div>
                    <strong>Синхронизация изменяет несколько частей товара</strong>
                    <span>Могут обновиться карточка, категории, производитель, характеристики, цены, остатки и торговые предложения.</span>
                  </div>
                </div>

                <button
                  className="btn primary sync-1c-button"
                  type="button"
                  onClick={syncFromOneC}
                  disabled={!canUpdate || oneCSaving || !product.external_id}
                >
                  {oneCSaving ? <Loader2 className="spin" size={15} /> : <RefreshCw size={15} />}
                  {oneCSaving ? 'Синхронизирую…' : 'Синхронизировать из 1С'}
                </button>

                {!product.external_id && (
                  <small className="section-note">Синхронизация недоступна: у товара нет external_id.</small>
                )}
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
            <ProductLinksPanel
              product={product}
              options={options}
              canUpdate={canUpdate}
              csrfToken={session.csrf_token}
              onProductSaved={applySavedProduct}
            />
          </div>
        )}
      </div>

      <footer className="editor-foot">
        <span>
          {tab === 'attributes'
            ? 'Характеристики сохраняются отдельной кнопкой внутри вкладки.'
            : tab === 'variants'
              ? 'Параметры вариаций и торговые предложения сохраняются внутри этой вкладки.'
              : tab === 'media'
              ? 'Загрузка, удаление и порядок изображений сохраняются сразу.'
              : tab === 'links'
                ? 'Связи и региональные правила сохраняются внутри соответствующих блоков.'
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
              className="btn ghost"
              type="button"
              onClick={() => void saveProduct(true)}
              disabled={saving || !canUpdate}
            >
              {saving ? <Loader2 className="spin" size={15} /> : <ArrowLeft size={14} />}
              {saving ? 'Сохраняю…' : 'Сохранить и выйти'}
            </button>
            <button
              className="btn primary"
              type="button"
              onClick={() => void saveProduct(false)}
              disabled={saving || !canUpdate}
            >
              {saving ? <Loader2 className="spin" size={15} /> : <CheckCircle2 size={15} />}
              {saving ? 'Сохраняю…' : 'Сохранить'}
            </button>
          </div>
        )}
      </footer>
    </section>

    {quickAttributeMode && (
      <QuickAttributeDialog
        mode={quickAttributeMode}
        draft={quickAttributeState}
        busy={dictionarySaving}
        onChange={setQuickAttributeState}
        onSave={() => void saveQuickAttribute()}
        onClose={() => setQuickAttributeMode(null)}
      />
    )}

    {quickValueTarget && quickValueAttribute && (
      <QuickValueDialog
        attribute={quickValueAttribute}
        draft={quickValueState}
        busy={dictionarySaving}
        onChange={setQuickValueState}
        onSave={() => void saveQuickValue()}
        onClose={() => setQuickValueTarget(null)}
      />
    )}
    </>
  )
}

type RegionRuleDraft = {
  id: number | null
  variant_id: string
  shipping_location_id: string
  price_override: string
  price_modifier_type: '' | 'fixed' | 'percent' | 'multiply'
  price_modifier_value: string
  is_hidden: boolean
  delivery_days_override: string
  priority: string
  is_active: boolean
}

function regionRuleDraft(
  rule?: ProductDetails['region_rules'][number],
): RegionRuleDraft {
  return {
    id: rule?.id ?? null,
    variant_id: rule?.variant_id === null || rule?.variant_id === undefined
      ? ''
      : String(rule.variant_id),
    shipping_location_id: rule ? String(rule.shipping_location_id) : '',
    price_override: rule?.price_override === null || rule?.price_override === undefined
      ? ''
      : String(rule.price_override),
    price_modifier_type: rule?.price_modifier_type ?? '',
    price_modifier_value: rule?.price_modifier_value === null || rule?.price_modifier_value === undefined
      ? ''
      : String(rule.price_modifier_value),
    is_hidden: Boolean(rule?.is_hidden ?? false),
    delivery_days_override: rule?.delivery_days_override === null || rule?.delivery_days_override === undefined
      ? ''
      : String(rule.delivery_days_override),
    priority: String(rule?.priority ?? 0),
    is_active: Boolean(rule?.is_active ?? true),
  }
}

function ProductLinksPanel({
  product,
  options,
  canUpdate,
  csrfToken,
  onProductSaved,
}: {
  product: ProductDetails
  options: ProductEditorOptions
  canUpdate: boolean
  csrfToken: string
  onProductSaved: (product: ProductDetails) => void
}) {
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [regionDraft, setRegionDraft] = useState<RegionRuleDraft | null>(null)

  const run = async (
    action: () => Promise<{ message: string; product: ProductDetails }>,
  ) => {
    setBusy(true)
    setError(null)
    setMessage(null)

    try {
      const response = await action()
      onProductSaved(response.product)
      setMessage(response.message)
      return response.product
    } catch (actionError) {
      setError(actionError instanceof Error ? actionError.message : String(actionError))
      return null
    } finally {
      setBusy(false)
    }
  }

  const saveRegionRule = async () => {
    if (!regionDraft || !canUpdate) return

    if (!regionDraft.shipping_location_id) {
      setError('Выберите локацию доставки.')
      return
    }

    const nullableNumber = (value: string): number | null => {
      if (value.trim() === '') return null
      const parsed = Number(value.replace(',', '.'))
      return Number.isFinite(parsed) ? parsed : null
    }

    const data = {
      variant_id: regionDraft.variant_id ? Number(regionDraft.variant_id) : null,
      shipping_location_id: Number(regionDraft.shipping_location_id),
      price_override: nullableNumber(regionDraft.price_override),
      price_modifier_type: regionDraft.price_modifier_type || null,
      price_modifier_value: nullableNumber(regionDraft.price_modifier_value),
      is_hidden: regionDraft.is_hidden,
      delivery_days_override: nullableNumber(regionDraft.delivery_days_override),
      priority: Number(regionDraft.priority || 0),
      is_active: regionDraft.is_active,
    }

    const saved = await run(() => (
      regionDraft.id === null
        ? backendApi.createProductRegionRule(product.id, data, csrfToken)
        : backendApi.updateProductRegionRule(product.id, regionDraft.id, data, csrfToken)
    ))

    if (!saved) return

    const rule = regionDraft.id === null
      ? saved.region_rules.find((item) => (
          item.shipping_location_id === data.shipping_location_id
          && item.variant_id === data.variant_id
        ))
      : saved.region_rules.find((item) => item.id === regionDraft.id)

    setRegionDraft(rule ? regionRuleDraft(rule) : null)
  }

  const deleteRegionRule = async () => {
    if (!regionDraft?.id || !canUpdate) return

    const confirmed = window.confirm('Удалить региональное правило?')
    if (!confirmed) return

    const saved = await run(() => backendApi.deleteProductRegionRule(
      product.id,
      regionDraft.id as number,
      csrfToken,
    ))

    if (saved) setRegionDraft(null)
  }

  return (
    <div className="product-links-stack">
      {error && <InlineError text={error} />}
      {message && <InlineSuccess text={message} />}

      <div className="editor-two-column">
        <Card
          title="Сопутствующие товары"
          subtitle="Связь симметричная: товар появится сопутствующим в обе стороны"
        >
          <ProductRelationSearch
            productId={product.id}
            excludedIds={product.related_products.map((item) => item.id)}
            disabled={!canUpdate || busy}
            placeholder="Найти товар по названию или SKU"
            actionLabel="Добавить"
            onSelect={(id) => {
              void run(() => backendApi.attachRelatedProduct(product.id, id, csrfToken))
            }}
          />

          <div className="linked-product-list">
            {product.related_products.length === 0 && (
              <div className="linked-empty">Сопутствующие товары не добавлены.</div>
            )}
            {product.related_products.map((item) => (
              <LinkedProductRow
                product={item}
                key={item.id}
                action={
                  <button
                    className="icon-danger"
                    type="button"
                    disabled={!canUpdate || busy}
                    onClick={() => void run(() => backendApi.detachRelatedProduct(
                      product.id,
                      item.id,
                      csrfToken,
                    ))}
                    aria-label="Убрать сопутствующий товар"
                  >
                    <Trash2 size={14} />
                  </button>
                }
              />
            ))}
          </div>
        </Card>

        <Card
          title="Комплект / набор"
          subtitle="Односторонний состав товара с собственным порядком"
        >
          <ProductRelationSearch
            productId={product.id}
            excludedIds={product.bundle_products.map((item) => item.id)}
            disabled={!canUpdate || busy}
            placeholder="Добавить товар в комплект"
            actionLabel="Добавить"
            onSelect={(id) => {
              void run(() => backendApi.attachBundleProducts(
                product.id,
                [id],
                csrfToken,
              ))
            }}
          />

          <div className="linked-product-list">
            {product.bundle_products.length === 0 && (
              <div className="linked-empty">Состав комплекта не задан.</div>
            )}
            {product.bundle_products.map((item) => (
              <div className="bundle-row" key={item.id}>
                <LinkedProductRow product={item} />
                <label className="bundle-order">
                  <span>Порядок</span>
                  <input
                    type="number"
                    defaultValue={item.sort_order}
                    disabled={!canUpdate || busy}
                    onBlur={(event) => {
                      const next = Number(event.currentTarget.value || 0)
                      if (next === item.sort_order) return
                      void run(() => backendApi.updateBundleProduct(
                        product.id,
                        item.id,
                        next,
                        csrfToken,
                      ))
                    }}
                  />
                </label>
                <button
                  className="icon-danger"
                  type="button"
                  disabled={!canUpdate || busy}
                  onClick={() => void run(() => backendApi.detachBundleProduct(
                    product.id,
                    item.id,
                    csrfToken,
                  ))}
                  aria-label="Убрать товар из комплекта"
                >
                  <Trash2 size={14} />
                </button>
              </div>
            ))}
          </div>
        </Card>
      </div>

      <section className="region-rules-panel">
        <header>
          <div>
            <h3>Региональные правила</h3>
            <p>Цена, видимость и срок доставки для территории. Правило локации наследуется её дочерними локациями.</p>
          </div>
          <button
            className="btn primary small"
            type="button"
            disabled={!canUpdate || busy}
            onClick={() => setRegionDraft(regionRuleDraft())}
          >
            <Plus size={14} />
            Добавить правило
          </button>
        </header>

        <div className="region-rules-workspace">
          <div className="region-rule-list">
            {product.region_rules.length === 0 && (
              <div className="linked-empty">Региональных правил нет.</div>
            )}
            {product.region_rules.map((rule) => (
              <button
                className={regionDraft?.id === rule.id ? 'region-rule-row selected' : 'region-rule-row'}
                type="button"
                onClick={() => setRegionDraft(regionRuleDraft(rule))}
                key={rule.id}
              >
                <div>
                  <strong>{rule.location_path || rule.location_name || `Локация #${rule.shipping_location_id}`}</strong>
                  <small>
                    {rule.variant_id
                      ? `Вариация: ${rule.variant_name || '#' + rule.variant_id}`
                      : 'Весь товар'}
                  </small>
                </div>
                <div>
                  {rule.is_hidden && <span className="chip error">Скрыт</span>}
                  {!rule.is_active && <span className="chip">Выключено</span>}
                  {rule.price_override !== null && <span>{formatMoney(rule.price_override)}</span>}
                </div>
                <ChevronRight size={14} />
              </button>
            ))}
          </div>

          {regionDraft ? (
            <div className="region-rule-editor">
              <div className="form-grid readable">
                <label className="field">
                  <span>Применять к</span>
                  <select
                    value={regionDraft.variant_id}
                    disabled={!canUpdate || busy}
                    onChange={(event) => setRegionDraft({
                      ...regionDraft,
                      variant_id: event.target.value,
                    })}
                  >
                    <option value="">Всему товару</option>
                    {product.variants.map((variant) => (
                      <option value={variant.id} key={variant.id}>
                        {variant.name} · {variant.sku}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="field">
                  <span>Локация <b>*</b></span>
                  <select
                    value={regionDraft.shipping_location_id}
                    disabled={!canUpdate || busy}
                    onChange={(event) => setRegionDraft({
                      ...regionDraft,
                      shipping_location_id: event.target.value,
                    })}
                  >
                    <option value="">Выберите территорию</option>
                    {options.shipping_locations.map((location) => (
                      <option value={location.id} key={location.id}>
                        {location.path || location.name}
                      </option>
                    ))}
                  </select>
                </label>

                <LiveField
                  label="Цена для региона"
                  value={regionDraft.price_override}
                  suffix="₽"
                  inputMode="decimal"
                  onChange={(value) => setRegionDraft({ ...regionDraft, price_override: value })}
                />

                <label className="field">
                  <span>Модификатор цены</span>
                  <select
                    value={regionDraft.price_modifier_type}
                    disabled={!canUpdate || busy}
                    onChange={(event) => setRegionDraft({
                      ...regionDraft,
                      price_modifier_type: event.target.value as RegionRuleDraft['price_modifier_type'],
                      price_modifier_value: event.target.value ? regionDraft.price_modifier_value : '',
                    })}
                  >
                    <option value="">Без модификатора</option>
                    <option value="fixed">Фиксированная сумма +/-</option>
                    <option value="percent">Процент %</option>
                    <option value="multiply">Множитель ×</option>
                  </select>
                </label>

                {regionDraft.price_modifier_type && (
                  <LiveField
                    label="Значение модификатора"
                    value={regionDraft.price_modifier_value}
                    inputMode="decimal"
                    onChange={(value) => setRegionDraft({ ...regionDraft, price_modifier_value: value })}
                  />
                )}

                <LiveField
                  label="Срок доставки"
                  value={regionDraft.delivery_days_override}
                  suffix="дн."
                  inputMode="numeric"
                  onChange={(value) => setRegionDraft({ ...regionDraft, delivery_days_override: value })}
                />

                <LiveField
                  label="Приоритет"
                  value={regionDraft.priority}
                  inputMode="numeric"
                  onChange={(value) => setRegionDraft({ ...regionDraft, priority: value })}
                />
              </div>

              <div className="region-rule-switches">
                <label className="switch-line">
                  <input
                    type="checkbox"
                    checked={regionDraft.is_hidden}
                    disabled={!canUpdate || busy}
                    onChange={(event) => setRegionDraft({
                      ...regionDraft,
                      is_hidden: event.target.checked,
                    })}
                  />
                  <span>
                    <strong>Скрыть товар</strong>
                    <small>Скрывает товар или выбранную вариацию в этой территории и дочерних локациях.</small>
                  </span>
                </label>

                <label className="switch-line">
                  <input
                    type="checkbox"
                    checked={regionDraft.is_active}
                    disabled={!canUpdate || busy}
                    onChange={(event) => setRegionDraft({
                      ...regionDraft,
                      is_active: event.target.checked,
                    })}
                  />
                  <span>
                    <strong>Правило активно</strong>
                    <small>Выключенное правило хранится, но не применяется.</small>
                  </span>
                </label>
              </div>

              <footer className="region-rule-actions">
                {regionDraft.id !== null ? (
                  <button
                    className="btn danger"
                    type="button"
                    disabled={!canUpdate || busy}
                    onClick={() => void deleteRegionRule()}
                  >
                    <Trash2 size={14} />
                    Удалить
                  </button>
                ) : <span />}

                <div>
                  <button
                    className="btn ghost"
                    type="button"
                    disabled={busy}
                    onClick={() => setRegionDraft(null)}
                  >
                    Отмена
                  </button>
                  <button
                    className="btn primary"
                    type="button"
                    disabled={!canUpdate || busy}
                    onClick={() => void saveRegionRule()}
                  >
                    {busy ? <Loader2 className="spin" size={14} /> : <CheckCircle2 size={14} />}
                    {regionDraft.id === null ? 'Добавить правило' : 'Сохранить правило'}
                  </button>
                </div>
              </footer>
            </div>
          ) : (
            <div className="region-rule-placeholder">
              <MapPin size={26} />
              <strong>Выберите правило</strong>
              <span>Или добавьте новое для товара или конкретной вариации.</span>
            </div>
          )}
        </div>
      </section>
    </div>
  )
}

function ProductRelationSearch({
  productId,
  excludedIds,
  disabled,
  placeholder,
  actionLabel,
  onSelect,
}: {
  productId: number
  excludedIds: number[]
  disabled: boolean
  placeholder: string
  actionLabel: string
  onSelect: (id: number) => void
}) {
  const [search, setSearch] = useState('')
  const [items, setItems] = useState<ProductSummary[]>([])
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    const query = search.trim()
    if (query.length < 2) {
      setItems([])
      return
    }

    let cancelled = false
    setLoading(true)

    const timer = window.setTimeout(() => {
      backendApi.products({ search: query, state: 'active', sort: 'name_asc' })
        .then((response) => {
          if (cancelled) return
          const excluded = new Set([productId, ...excludedIds])
          setItems(response.data.filter((item) => !excluded.has(item.id)).slice(0, 8))
          setLoading(false)
        })
        .catch(() => {
          if (cancelled) return
          setItems([])
          setLoading(false)
        })
    }, 250)

    return () => {
      cancelled = true
      window.clearTimeout(timer)
    }
  }, [search, productId, excludedIds.join(',')])

  return (
    <div className="relation-search">
      <label className="small-search">
        <Search size={15} />
        <input
          value={search}
          disabled={disabled}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={placeholder}
        />
        {loading && <Loader2 className="spin" size={14} />}
      </label>

      {items.length > 0 && (
        <div className="relation-search-results">
          {items.map((item) => (
            <button
              type="button"
              disabled={disabled}
              onClick={() => {
                onSelect(item.id)
                setSearch('')
                setItems([])
              }}
              key={item.id}
            >
              <span>
                <strong>{item.name}</strong>
                <small>{item.sku || `#${item.id}`} · {formatMoney(item.price)}</small>
              </span>
              <em>{actionLabel}</em>
            </button>
          ))}
        </div>
      )}
    </div>
  )
}

function LinkedProductRow({
  product,
  action,
}: {
  product: {
    id: number
    name: string
    sku: string
    price: number
    image_url: string | null
  }
  action?: ReactNode
}) {
  return (
    <article className="linked-product-row">
      {product.image_url ? (
        <img src={product.image_url} alt="" />
      ) : (
        <span className="linked-product-placeholder"><Package size={16} /></span>
      )}
      <div>
        <strong>{product.name}</strong>
        <small>{product.sku || `#${product.id}`} · {formatMoney(product.price)}</small>
      </div>
      {action}
    </article>
  )
}

type SearchableSelectOption = {
  value: number
  label: string
  hint?: string
  disabled?: boolean
  swatch?: string | null
}

function SearchableSelect({
  value,
  options,
  disabled,
  placeholder,
  searchPlaceholder,
  onChange,
}: {
  value: number | null
  options: SearchableSelectOption[]
  disabled: boolean
  placeholder: string
  searchPlaceholder: string
  onChange: (value: number) => void
}) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const selected = options.find((option) => option.value === value) ?? null
  const normalized = search.trim().toLocaleLowerCase('ru-RU')
  const filtered = normalized
    ? options.filter((option) => (
        option.label.toLocaleLowerCase('ru-RU').includes(normalized)
        || (option.hint ?? '').toLocaleLowerCase('ru-RU').includes(normalized)
      ))
    : options

  return (
    <div
      className={open ? 'searchable-select open' : 'searchable-select'}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
          setOpen(false)
          setSearch('')
        }
      }}
    >
      <button
        className="searchable-select-trigger"
        type="button"
        disabled={disabled}
        aria-expanded={open}
        onClick={() => setOpen((current) => !current)}
      >
        <span>
          {selected?.swatch && <i style={{ background: selected.swatch }} />}
          <strong>{selected?.label ?? placeholder}</strong>
          {selected?.hint && <small>{selected.hint}</small>}
        </span>
        <ChevronDown size={15} />
      </button>

      {open && !disabled && (
        <div className="searchable-select-popover">
          <label className="small-search">
            <Search size={14} />
            <input
              autoFocus
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={searchPlaceholder}
            />
          </label>

          <div className="searchable-select-options">
            {filtered.length === 0 && (
              <span className="searchable-select-empty">Ничего не найдено</span>
            )}

            {filtered.map((option) => (
              <button
                className={option.value === value ? 'searchable-select-option selected' : 'searchable-select-option'}
                type="button"
                disabled={option.disabled}
                onClick={() => {
                  onChange(option.value)
                  setOpen(false)
                  setSearch('')
                }}
                key={option.value}
              >
                <span>
                  {option.swatch && <i style={{ background: option.swatch }} />}
                  <strong>{option.label}</strong>
                  {option.hint && <small>{option.hint}</small>}
                </span>
                {option.value === value && <Check size={14} />}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

function SearchableMultiSelect({
  value,
  options,
  disabled,
  placeholder,
  searchPlaceholder,
  onChange,
}: {
  value: number[]
  options: SearchableSelectOption[]
  disabled: boolean
  placeholder: string
  searchPlaceholder: string
  onChange: (value: number[]) => void
}) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const selected = options.filter((option) => value.includes(option.value))
  const normalized = search.trim().toLocaleLowerCase('ru-RU')
  const filtered = normalized
    ? options.filter((option) => option.label.toLocaleLowerCase('ru-RU').includes(normalized))
    : options

  const label = selected.length === 0
    ? placeholder
    : selected.length <= 2
      ? selected.map((option) => option.label).join(', ')
      : `${selected.slice(0, 2).map((option) => option.label).join(', ')} +${selected.length - 2}`

  return (
    <div
      className={open ? 'searchable-select open' : 'searchable-select'}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
          setOpen(false)
          setSearch('')
        }
      }}
    >
      <button
        className="searchable-select-trigger"
        type="button"
        disabled={disabled}
        onClick={() => setOpen((current) => !current)}
      >
        <span><strong>{label}</strong></span>
        <ChevronDown size={15} />
      </button>

      {open && !disabled && (
        <div className="searchable-select-popover">
          <label className="small-search">
            <Search size={14} />
            <input
              autoFocus
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={searchPlaceholder}
            />
          </label>
          <div className="searchable-select-options">
            {filtered.map((option) => {
              const active = value.includes(option.value)
              return (
                <button
                  className={active ? 'searchable-select-option selected' : 'searchable-select-option'}
                  type="button"
                  onClick={() => onChange(
                    active
                      ? value.filter((id) => id !== option.value)
                      : [...value, option.value],
                  )}
                  key={option.value}
                >
                  <span>
                    {option.swatch && <i style={{ background: option.swatch }} />}
                    <strong>{option.label}</strong>
                  </span>
                  {active && <Check size={14} />}
                </button>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}

function SingleImageDropzone({
  item,
  disabled,
  busy,
  onFiles,
  onDelete,
}: {
  item: MediaItem | null
  disabled: boolean
  busy: boolean
  onFiles: (files: File[]) => void
  onDelete?: () => void
}) {
  const [dragActive, setDragActive] = useState(false)

  const acceptFiles = (files: File[]) => {
    const images = files.filter((file) => file.type.startsWith('image/'))
    if (images.length > 0) onFiles(images.slice(0, 1))
  }

  return (
    <div className="single-image-manager">
      {item && (
        <div className="media-preview main media-thumb-card">
          <img src={item.thumb_url || item.url} alt={item.name} />
          <div>
            <strong>{item.file_name}</strong>
            <a href={item.url} target="_blank" rel="noreferrer">Открыть оригинал</a>
          </div>
          {onDelete && (
            <button
              className="icon-danger"
              type="button"
              onClick={onDelete}
              disabled={disabled || busy}
              aria-label="Удалить изображение"
            >
              <Trash2 size={15} />
            </button>
          )}
        </div>
      )}

      <label
        className={[
          'media-dropzone',
          'single',
          dragActive ? 'drag-active' : '',
          disabled || busy ? 'disabled' : '',
        ].filter(Boolean).join(' ')}
        onDragEnter={(event) => {
          event.preventDefault()
          if (!disabled && !busy) setDragActive(true)
        }}
        onDragOver={(event) => event.preventDefault()}
        onDragLeave={(event) => {
          if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
            setDragActive(false)
          }
        }}
        onDrop={(event) => {
          event.preventDefault()
          setDragActive(false)
          if (disabled || busy) return
          acceptFiles(Array.from(event.dataTransfer.files))
        }}
      >
        {busy ? <Loader2 className="spin" size={24} /> : <ImagePlus size={24} />}
        <strong>{item ? 'Заменить главное изображение' : 'Перетащите главное изображение сюда'}</strong>
        <span>или нажмите для выбора файла · JPEG, PNG, WebP · до 10 МБ</span>
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          disabled={disabled || busy}
          onChange={(event) => {
            acceptFiles(Array.from(event.currentTarget.files ?? []))
            event.currentTarget.value = ''
          }}
        />
      </label>
    </div>
  )
}

function GalleryManager({
  items,
  maxFiles,
  disabled,
  busy,
  onFiles,
  onDelete,
  onReorder,
}: {
  items: MediaItem[]
  maxFiles: number
  disabled: boolean
  busy: boolean
  onFiles: (files: File[]) => void
  onDelete: (mediaId: number) => void
  onReorder: (mediaIds: number[]) => Promise<void>
}) {
  const [draggedId, setDraggedId] = useState<number | null>(null)
  const [fileDragActive, setFileDragActive] = useState(false)
  const [orderedItems, setOrderedItems] = useState(items)
  const remaining = Math.max(0, maxFiles - orderedItems.length)
  const itemsSignature = items
    .map((item) => `${item.id}:${item.order}`)
    .join('|')

  useEffect(() => {
    setOrderedItems(items)
  }, [itemsSignature])

  const uploadFiles = (files: File[]) => {
    if (remaining <= 0) return

    const images = files
      .filter((file) => file.type.startsWith('image/'))
      .slice(0, remaining)

    if (images.length > 0) onFiles(images)
  }

  const moveAround = async (targetId: number, placeAfter: boolean) => {
    if (draggedId === null || draggedId === targetId) return

    const dragged = orderedItems.find((item) => item.id === draggedId)
    if (!dragged) return

    const next = orderedItems.filter((item) => item.id !== draggedId)
    const targetIndex = next.findIndex((item) => item.id === targetId)
    if (targetIndex < 0) return

    next.splice(targetIndex + (placeAfter ? 1 : 0), 0, dragged)
    setDraggedId(null)

    const ids = next.map((item) => item.id)
    if (!ids.some((id, index) => id !== orderedItems[index]?.id)) {
      return
    }

    setOrderedItems(next)

    try {
      await onReorder(ids)
    } catch {
      setOrderedItems(items)
    }
  }

  return (
    <div className="gallery-manager">
      <div className="gallery-manager-head">
        <span>{items.length} / {maxFiles}</span>
        <small>Перетаскивайте миниатюры, чтобы изменить порядок.</small>
      </div>

      {orderedItems.length > 0 ? (
        <div className="gallery-grid sortable-gallery">
          {orderedItems.map((item, index) => (
            <article
              className={draggedId === item.id ? 'gallery-item sortable dragging' : 'gallery-item sortable'}
              draggable={!disabled && !busy}
              onDragStart={(event) => {
                setDraggedId(item.id)
                event.dataTransfer.effectAllowed = 'move'
                event.dataTransfer.setData('text/plain', String(item.id))
              }}
              onDragEnd={() => setDraggedId(null)}
              onDragOver={(event) => {
                event.preventDefault()
                event.dataTransfer.dropEffect = event.dataTransfer.files.length > 0 ? 'copy' : 'move'
              }}
              onDrop={(event) => {
                event.preventDefault()

                if (event.dataTransfer.files.length > 0) {
                  setDraggedId(null)
                  uploadFiles(Array.from(event.dataTransfer.files))
                  return
                }

                const bounds = event.currentTarget.getBoundingClientRect()
                const placeAfter = event.clientY > bounds.top + bounds.height / 2
                void moveAround(item.id, placeAfter)
              }}
              key={item.id}
            >
              <div className="gallery-order-badge">{index + 1}</div>
              <div className="gallery-drag-handle" title="Перетащить">
                <GripVertical size={15} />
              </div>
              <img src={item.thumb_url || item.url} alt={item.name} />
              <div>
                <span title={item.file_name}>{item.file_name}</span>
                <button
                  className="icon-danger"
                  type="button"
                  onClick={() => onDelete(item.id)}
                  disabled={disabled || busy}
                  aria-label="Удалить изображение"
                >
                  <Trash2 size={14} />
                </button>
              </div>
            </article>
          ))}
        </div>
      ) : (
        <div className="media-empty">Галерея пока пустая</div>
      )}

      <label
        className={[
          'media-dropzone',
          'gallery',
          fileDragActive ? 'drag-active' : '',
          disabled || busy || remaining <= 0 ? 'disabled' : '',
        ].filter(Boolean).join(' ')}
        onDragEnter={(event) => {
          event.preventDefault()
          if (!disabled && !busy && remaining > 0) setFileDragActive(true)
        }}
        onDragOver={(event) => event.preventDefault()}
        onDragLeave={(event) => {
          if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
            setFileDragActive(false)
          }
        }}
        onDrop={(event) => {
          event.preventDefault()
          setFileDragActive(false)
          if (disabled || busy || remaining <= 0) return
          uploadFiles(Array.from(event.dataTransfer.files))
        }}
      >
        {busy ? <Loader2 className="spin" size={22} /> : <ImagePlus size={22} />}
        <strong>
          {remaining > 0
            ? `Перетащите фотографии сюда · можно ещё ${remaining}`
            : `Достигнут предел в ${maxFiles} изображений`}
        </strong>
        <span>{remaining > 0 ? 'или нажмите и выберите несколько файлов сразу' : 'Удалите фотографию, чтобы загрузить новую'}</span>
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          multiple
          disabled={disabled || busy || remaining <= 0}
          onChange={(event) => {
            uploadFiles(Array.from(event.currentTarget.files ?? []))
            event.currentTarget.value = ''
          }}
        />
      </label>
    </div>
  )
}

function QuickAttributeDialog({
  mode,
  draft,
  busy,
  onChange,
  onSave,
  onClose,
}: {
  mode: 'product' | 'variation'
  draft: QuickAttributeDraft
  busy: boolean
  onChange: (draft: QuickAttributeDraft) => void
  onSave: () => void
  onClose: () => void
}) {
  return (
    <div
      className="quick-editor-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !busy) onClose()
      }}
    >
      <section className="quick-editor-dialog">
        <header>
          <div>
            <span className="eyebrow">Создание без перехода в справочник</span>
            <h3>{mode === 'variation' ? 'Новый параметр вариации' : 'Новая характеристика'}</h3>
          </div>
          <button className="icon-btn" type="button" onClick={onClose} disabled={busy} aria-label="Закрыть">
            <X size={16} />
          </button>
        </header>

        <div className="quick-editor-body">
          <div className="form-grid readable">
            <LiveField
              label="Название"
              value={draft.name}
              required
              onChange={(value) => onChange({ ...draft, name: value })}
            />
            <LiveField
              label="Slug (необязательно)"
              value={draft.slug}
              onChange={(value) => onChange({ ...draft, slug: value })}
            />
            <label className="field">
              <span>Тип <b>*</b></span>
              <select
                value={draft.type}
                onChange={(event) => onChange({ ...draft, type: event.target.value })}
              >
                <option value="select">Список</option>
                <option value="color">Цвет</option>
                <option value="string">Строка</option>
                <option value="text">Текст</option>
                <option value="number">Число из списка</option>
                <option value="number_input">Число — ручной ввод</option>
              </select>
            </label>
          </div>

          {mode === 'variation' && (
            <div className="callout muted compact-callout">
              <SlidersHorizontal size={16} />
              <div>
                <strong>Будет использоваться в торговых предложениях</strong>
                <span>Например, «Цвет» или «Размер». После создания характеристика сразу добавится в параметры этого товара.</span>
              </div>
            </div>
          )}

          <div className="quick-editor-flags">
            <label>
              <input
                type="checkbox"
                checked={draft.is_filterable}
                onChange={(event) => onChange({ ...draft, is_filterable: event.target.checked })}
              />
              <span><strong>Фильтр</strong><small>Показывать в фильтрах каталога</small></span>
            </label>
            <label>
              <input
                type="checkbox"
                checked={draft.is_required}
                onChange={(event) => onChange({ ...draft, is_required: event.target.checked })}
              />
              <span><strong>Обязательная</strong><small>Требовать значение</small></span>
            </label>
            <label>
              <input
                type="checkbox"
                checked={draft.is_multiple}
                onChange={(event) => onChange({ ...draft, is_multiple: event.target.checked })}
              />
              <span><strong>Несколько значений</strong><small>Можно выбрать больше одного</small></span>
            </label>
            <label>
              <input
                type="checkbox"
                checked={draft.allow_custom_value}
                onChange={(event) => onChange({ ...draft, allow_custom_value: event.target.checked })}
              />
              <span><strong>Ручное значение</strong><small>Разрешить ввод вне справочника</small></span>
            </label>
          </div>
        </div>

        <footer className="quick-editor-actions">
          <button className="btn ghost" type="button" onClick={onClose} disabled={busy}>Отмена</button>
          <button className="btn primary" type="button" onClick={onSave} disabled={busy || !draft.name.trim()}>
            {busy ? <Loader2 className="spin" size={15} /> : <Plus size={15} />}
            {busy ? 'Создаю…' : 'Создать'}
          </button>
        </footer>
      </section>
    </div>
  )
}

function QuickValueDialog({
  attribute,
  draft,
  busy,
  onChange,
  onSave,
  onClose,
}: {
  attribute: ProductEditorAttributeOption
  draft: QuickValueDraft
  busy: boolean
  onChange: (draft: QuickValueDraft) => void
  onSave: () => void
  onClose: () => void
}) {
  const colorValue = /^#[0-9a-f]{6}$/i.test(draft.color_code)
    ? draft.color_code
    : '#000000'

  return (
    <div
      className="quick-editor-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !busy) onClose()
      }}
    >
      <section className="quick-editor-dialog compact-dialog">
        <header>
          <div>
            <span className="eyebrow">{attribute.name}</span>
            <h3>Новое значение характеристики</h3>
          </div>
          <button className="icon-btn" type="button" onClick={onClose} disabled={busy} aria-label="Закрыть">
            <X size={16} />
          </button>
        </header>

        <div className="quick-editor-body">
          <LiveField
            label="Значение"
            value={draft.value}
            required
            onChange={(value) => onChange({ ...draft, value })}
          />
          <LiveField
            label="Slug (необязательно)"
            value={draft.slug}
            onChange={(value) => onChange({ ...draft, slug: value })}
          />

          {attribute.type === 'color' && (
            <label className="field color-value-field">
              <span>Цвет</span>
              <div>
                <input
                  type="color"
                  value={colorValue}
                  onChange={(event) => onChange({ ...draft, color_code: event.target.value })}
                />
                <input
                  value={draft.color_code}
                  onChange={(event) => onChange({ ...draft, color_code: event.target.value })}
                  placeholder="#000000"
                />
              </div>
            </label>
          )}
        </div>

        <footer className="quick-editor-actions">
          <button className="btn ghost" type="button" onClick={onClose} disabled={busy}>Отмена</button>
          <button className="btn primary" type="button" onClick={onSave} disabled={busy || !draft.value.trim()}>
            {busy ? <Loader2 className="spin" size={15} /> : <Plus size={15} />}
            {busy ? 'Создаю…' : 'Создать значение'}
          </button>
        </footer>
      </section>
    </div>
  )
}

function VariantEditorPanel({
  draft,
  options,
  canUpdate,
  canDelete,
  canUpdateAttributes,
  saving,
  onChange,
  media,
  onSave,
  onDelete,
  onUploadMedia,
  onDeleteMedia,
  onReorderMedia,
  onCreateValue,
  onCancel,
}: {
  draft: VariantDraft
  options: ProductEditorOptions
  canUpdate: boolean
  canDelete: boolean
  canUpdateAttributes: boolean
  saving: boolean
  media: ProductDetails['variants'][number]['media']
  onChange: (draft: VariantDraft) => void
  onSave: () => void
  onDelete: () => void
  onUploadMedia: (collection: 'images' | 'gallery', files: File[]) => void
  onDeleteMedia: (mediaId: number) => void
  onReorderMedia: (mediaIds: number[]) => void
  onCreateValue: (attributeId: number) => void
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

  const mainImage = media.find((item) => item.collection === 'images') ?? null
  const galleryImages = media.filter((item) => item.collection === 'gallery')
  const editableVariationAttributes = options.variation_attributes.filter(
    (attribute) => attribute.slug !== 'variant',
  )

  return (
    <section className="variant-editor-panel">
      <header className="variant-editor-head">
        <div>
          <span className="eyebrow">
            {draft.id === null ? 'Новая вариация' : `Вариация #${draft.id}`}
          </span>
          <h3>{draft.name || 'Без названия'}</h3>
        </div>
        <button className="icon-btn" type="button" onClick={onCancel} aria-label="Закрыть редактор">
          <X size={16} />
        </button>
      </header>

      <div className="variant-editor-scroll">
        <div className="editor-two-column">
          <Card title="Основные данные" subtitle="Каждая вариация имеет собственные SKU и цену">
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

          <Card title="Остатки" subtitle={options.stock_settings.warehouse_accounting_enabled ? 'Отдельно по складам для этой вариации' : 'Общий остаток этой вариации'}>
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
                            {warehouse.name}{warehouse.external_id ? ` · 1С: ${warehouse.external_id}` : ''}
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

                {draft.warehouse_stocks.length === 0 && (
                  <div className="attribute-no-values">
                    <Warehouse size={15} />
                    Для этой вариации пока не задан остаток ни на одном складе.
                  </div>
                )}

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

        <Card title="Параметры вариации" subtitle="Значения, которыми это торговое предложение отличается от остальных">
          {editableVariationAttributes.length === 0 ? (
            <div className="attribute-editor-empty compact">
              <SlidersHorizontal size={20} />
              <strong>Параметры не выбраны</strong>
              <span>Закройте редактор и выберите сверху, например, «Цвет» и «Размер».</span>
            </div>
          ) : (
            <div className="variant-attribute-editor">
              {editableVariationAttributes.map((attribute) => {
                const row = draft.attributes.find((item) => item.attribute_id === attribute.id)
                  ?? { attribute_id: attribute.id, attribute_value_id: [], custom_value: '' }
                const showCustom = attribute.allow_custom_value
                  && (attribute.values.length === 0 || attribute.type !== 'select')

                return (
                  <div className="variant-attribute-row" key={attribute.id}>
                    <div className="variant-attribute-label">
                      <strong>{attribute.name}{attribute.is_required ? ' *' : ''}</strong>
                      <small>{attribute.slug}</small>
                    </div>

                    <div className="variant-attribute-control">
                      <div className="attribute-value-toolbar">
                        <span>Значение</span>
                        {canUpdateAttributes && (
                          <button
                            className="btn ghost small"
                            type="button"
                            onClick={() => onCreateValue(attribute.id)}
                            disabled={saving}
                          >
                            <Plus size={13} />
                            Новое значение
                          </button>
                        )}
                      </div>

                      {attribute.values.length > 0 && !attribute.is_multiple && (
                        <SearchableSelect
                          value={row.attribute_value_id[0] ?? null}
                          disabled={!canUpdate}
                          placeholder="Не выбрано"
                          searchPlaceholder={`Найти значение «${attribute.name}»`}
                          options={attribute.values.map((value) => ({
                            value: value.id,
                            label: value.value,
                            swatch: value.color_code,
                          }))}
                          onChange={(valueId) => updateAttribute(attribute.id, {
                            attribute_value_id: [valueId],
                            custom_value: '',
                          })}
                        />
                      )}

                      {attribute.values.length > 0 && attribute.is_multiple && (
                        <div className="attribute-multiple-field">
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
                          Значений пока нет. Создайте первое кнопкой выше.
                        </div>
                      )}
                    </div>
                  </div>
                )
              })}
            </div>
          )}

          <div className="variation-system-note inside-card">
            <CheckCircle2 size={15} />
            <span>Служебное значение «Вариант» будет взято из поля «Название вариации».</span>
          </div>
        </Card>

        {draft.id !== null && (
          <Card title="Изображения вариации" subtitle="Собственная главная фотография и галерея до 20 изображений">
            <div className="variant-media-grid">
              <SingleImageDropzone
                item={mainImage}
                disabled={!canUpdate}
                busy={saving}
                onFiles={(files) => onUploadMedia('images', files)}
                onDelete={mainImage ? () => onDeleteMedia(mainImage.id) : undefined}
              />
              <GalleryManager
                items={galleryImages}
                maxFiles={20}
                disabled={!canUpdate}
                busy={saving}
                onFiles={(files) => onUploadMedia('gallery', files)}
                onDelete={onDeleteMedia}
                onReorder={onReorderMedia}
              />
            </div>
          </Card>
        )}
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
            {saving ? 'Сохраняю…' : draft.id === null ? 'Создать вариацию' : 'Сохранить вариацию'}
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
