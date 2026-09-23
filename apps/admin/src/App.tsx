import { useEffect, useMemo, useState } from 'react'
import {
  AlertTriangle,
  Bell,
  Boxes,
  CheckCircle2,
  ChevronDown,
  ChevronRight,
  CircleAlert,
  ClipboardList,
  ExternalLink,
  FolderTree,
  Loader2,
  MapPin,
  Moon,
  Package,
  Pencil,
  RefreshCw,
  Search,
  Settings,
  SlidersHorizontal,
  Store,
  Sun,
  Truck,
  UserRound,
  Warehouse,
} from 'lucide-react'
import {
  AuthRequiredError,
  backendApi,
  type AdminOrder,
  type AttributeDefinition,
  type CategoryNode,
  type LocationRecord,
  type ProductDetails,
  type ProductSummary,
  type SessionInfo,
  type StoreRecord,
} from './api'

type Theme = 'light' | 'dark'
type ModuleKey = 'products' | 'attributes' | 'orders' | 'stores' | 'locations' | 'warehouses'
type SectionKind = 'categories' | 'rooms'
type ProductTab = 'main' | 'attributes' | 'variants' | 'stock'

type TreeRow = {
  id: number
  slug: string
  name: string
  depth: number
  count: number
}

type ProductDraft = {
  name: string
  sku: string
  gtin: string
  description: string
  state: string
  priority: string
  price: string
  original_price: string
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
      slug: node.slug,
      name: node.name,
      depth,
      count: node.products_count ?? 0,
    },
    ...flattenTree(node.children ?? [], depth + 1),
  ])
}

function stateLabel(state: string): string {
  return {
    active: 'Активен',
    draft: 'Черновик',
    inactive: 'Неактивен',
    unlisted: 'Скрыт',
    unavailable: 'Недоступен',
    retired: 'Снят с продажи',
  }[state] ?? state
}

function productDraft(product: ProductDetails): ProductDraft {
  return {
    name: product.name ?? '',
    sku: product.sku ?? '',
    gtin: product.gtin ?? '',
    description: product.description ?? '',
    state: product.state ?? 'draft',
    priority: String(product.priority ?? 0),
    price: String(product.price ?? 0),
    original_price: product.original_price === null ? '' : String(product.original_price),
  }
}

export function App() {
  const [theme, setTheme] = useState<Theme>(initialTheme)
  const [module, setModule] = useState<ModuleKey>('products')
  const [session, setSession] = useState<SessionInfo | null>(null)
  const [authRequired, setAuthRequired] = useState(false)
  const [sessionError, setSessionError] = useState<string | null>(null)

  useEffect(() => {
    document.documentElement.dataset.theme = theme
    localStorage.setItem('sv-admin-theme', theme)
  }, [theme])

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
      <Topbar theme={theme} setTheme={setTheme} session={session} />

      <div className="frame">
        <Sidebar module={module} setModule={setModule} session={session} />

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
            <UnavailableWorkspace
              icon={Warehouse}
              title="Склады пока не подключены"
              text="В текущем develop для складов нет отдельной policy/permission. Не открываю этот ресурс через общий доступ к панели — сначала добавим нормальные права."
            />
          )}
        </main>
      </div>
    </div>
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
          После входа вернитесь на <strong>http://localhost:5174</strong>.
        </small>
      </div>
    </div>
  )
}

function Topbar({
  theme,
  setTheme,
  session,
}: {
  theme: Theme
  setTheme: (value: Theme) => void
  session: SessionInfo
}) {
  return (
    <header className="topbar">
      <div className="brand">
        <span className="brand-mark"><Package size={17} /></span>
        <div>
          <strong>Светофор Мебели</strong>
          <small>React-админка · Laravel backend</small>
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
      ['warehouses', 'Склады', Warehouse, null],
    ],
  },
]

function Sidebar({
  module,
  setModule,
  session,
}: {
  module: ModuleKey
  setModule: (value: ModuleKey) => void
  session: SessionInfo
}) {
  return (
    <aside className="sidebar">
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

      <div className="sidebar-foot">
        <span />
        <div>
          <strong>Живые данные</strong>
          <small>Источник: svetofor.local</small>
        </div>
      </div>
    </aside>
  )
}

const headers: Record<ModuleKey, [string, string, string]> = {
  products: [
    'Рабочее место оператора',
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
    'Подключение отложено до появления отдельной политики доступа.',
  ],
}

function ModuleHeader({ module }: { module: ModuleKey }) {
  const [eyebrow, title, text] = headers[module]

  return (
    <header className="page-head">
      <div>
        <span className="eyebrow">{eyebrow}</span>
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
  const [productsLoading, setProductsLoading] = useState(true)
  const [productsError, setProductsError] = useState<string | null>(null)
  const [selectedProductId, setSelectedProductId] = useState<number | null>(null)
  const [search, setSearch] = useState('')

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
      const request = kind === 'rooms' && selectedSection
        ? backendApi.publicProductsByRoom(selectedSection.slug)
        : backendApi.products({
            search: search.trim() || undefined,
            categoryId: kind === 'categories' ? selectedSection?.id ?? null : null,
          })

      request
        .then((response) => {
          if (cancelled) return

          const rows: ProductSummary[] = response.data.map((row) => ({
            id: row.id,
            name: row.name,
            slug: row.slug,
            sku: 'sku' in row && row.sku ? row.sku : '',
            gtin: 'gtin' in row ? row.gtin ?? null : null,
            state: 'state' in row ? row.state : 'active',
            price: Number(row.price ?? 0),
            original_price: 'original_price' in row ? row.original_price ?? null : null,
            stock: Number(row.stock ?? 0),
            variants_count: 'variants_count' in row ? row.variants_count : 0,
            categories: row.categories ?? [],
          }))

          setProducts(rows)
          setProductsTotal(response.meta?.total ?? rows.length)
          setProductsLoading(false)

          if (rows.length > 0 && !rows.some((row) => row.id === selectedProductId)) {
            setSelectedProductId(rows[0].id)
          }

          if (rows.length === 0) {
            setSelectedProductId(null)
          }
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
  }, [kind, selectedSection, search])

  return (
    <div className="catalog-layout">
      <section className="panel section-panel">
        <PanelHead eyebrow="Структура" title="Разделы" subtitle="Данные из Laravel" />

        <div className="segmented">
          <button
            className={kind === 'categories' ? 'active' : ''}
            type="button"
            onClick={() => {
              setKind('categories')
              setSelectedSection(null)
            }}
          >
            Категории
          </button>
          <button
            className={kind === 'rooms' ? 'active' : ''}
            type="button"
            onClick={() => {
              setKind('rooms')
              setSelectedSection(null)
            }}
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
              onClick={() => setSelectedSection(null)}
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
                onClick={() => setSelectedSection(node)}
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

      <section className="panel products-panel">
        <PanelHead
          eyebrow={kind === 'categories' ? 'Категория' : 'Комната'}
          title={selectedSection?.name ?? 'Все товары'}
          subtitle={productsTotal ? `${productsTotal} товаров` : 'Реальные записи'}
        />

        <label className="small-search workspace-search">
          <Search size={15} />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Название, SKU, штрихкод"
          />
        </label>

        <div className="products">
          {productsLoading && <InlineLoading text="Загружаю товары…" />}
          {productsError && <InlineError text={productsError} />}

          {!productsLoading && !productsError && products.map((product) => (
            <button
              className={selectedProductId === product.id ? 'product-row selected' : 'product-row'}
              type="button"
              onClick={() => setSelectedProductId(product.id)}
              key={product.id}
            >
              <span className="thumb">{product.name.slice(0, 2).toUpperCase()}</span>
              <div>
                <strong>{product.name}</strong>
                <small>
                  {product.sku || `#${product.id}`} · {formatMoney(product.price)}
                </small>
                <p>
                  <em>{stateLabel(product.state)}</em>
                  <span>{product.stock} шт.</span>
                  {product.variants_count > 0 && <span>{product.variants_count} вар.</span>}
                </p>
              </div>
            </button>
          ))}

          {!productsLoading && !productsError && products.length === 0 && (
            <EmptyState text="В этом разделе товаров нет." />
          )}
        </div>
      </section>

      <ProductEditor
        productId={selectedProductId}
        session={session}
        onProductSaved={(saved) => {
          setProducts((current) => current.map((item) => (
            item.id === saved.id ? saved : item
          )))
        }}
      />
    </div>
  )
}

function ProductEditor({
  productId,
  session,
  onProductSaved,
}: {
  productId: number | null
  session: SessionInfo
  onProductSaved: (product: ProductDetails) => void
}) {
  const [product, setProduct] = useState<ProductDetails | null>(null)
  const [draft, setDraft] = useState<ProductDraft | null>(null)
  const [tab, setTab] = useState<ProductTab>('main')
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!productId) {
      setProduct(null)
      setDraft(null)
      return
    }

    let cancelled = false
    setLoading(true)
    setError(null)
    setMessage(null)

    backendApi.product(productId)
      .then(({ product: value }) => {
        if (cancelled) return
        setProduct(value)
        setDraft(productDraft(value))
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

  if (!productId) {
    return (
      <section className="panel editor">
        <EmptyState text="Выберите товар слева." />
      </section>
    )
  }

  if (loading || !product || !draft) {
    return (
      <section className="panel editor">
        <InlineLoading text="Загружаю карточку товара…" />
      </section>
    )
  }

  const requiredChecks = [
    Boolean(draft.name.trim()),
    Boolean(draft.sku.trim()),
    Number(draft.price) >= 0,
    Boolean(draft.state),
  ]

  const completion = Math.round(
    requiredChecks.filter(Boolean).length / requiredChecks.length * 100,
  )

  const canUpdate = Boolean(session.permissions.products?.update)

  const save = async () => {
    if (!canUpdate) return

    setSaving(true)
    setError(null)
    setMessage(null)

    try {
      const response = await backendApi.updateProduct(
        product.id,
        {
          name: draft.name.trim(),
          sku: draft.sku.trim(),
          gtin: draft.gtin.trim() || null,
          description: draft.description.trim() || null,
          state: draft.state,
          priority: Number(draft.priority || 0),
          price: Number(draft.price || 0),
          original_price: draft.original_price.trim() === ''
            ? null
            : Number(draft.original_price),
        },
        session.csrf_token,
      )

      setProduct(response.product)
      setDraft(productDraft(response.product))
      setMessage(response.message)
      onProductSaved(response.product)
    } catch (saveError) {
      setError(saveError instanceof Error ? saveError.message : String(saveError))
    } finally {
      setSaving(false)
    }
  }

  return (
    <section className="panel editor">
      <div className="editor-head">
        <div className="product-title">
          <span className="hero-thumb">{product.name.slice(0, 2).toUpperCase()}</span>
          <div>
            <span className="eyebrow">Товар #{product.id}</span>
            <h2>{product.name}</h2>
            <p>
              {product.sku}
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
            <div className="progress-ring" style={{ background: `conic-gradient(var(--secondary) 0 ${completion}%, var(--border) ${completion}% 100%)` }}>
              <span>{completion}%</span>
            </div>
            <div>
              <strong>Обязательные поля</strong>
              <small>{requiredChecks.filter(Boolean).length} из {requiredChecks.length} заполнены</small>
            </div>
          </div>

          {error && <span className="chip error"><CircleAlert size={13} /> Ошибка сохранения</span>}
          {message && <span className="chip ok"><CheckCircle2 size={13} /> {message}</span>}
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

      <div className="tabs">
        <button className={tab === 'main' ? 'active' : ''} type="button" onClick={() => setTab('main')}>Основное</button>
        <button className={tab === 'attributes' ? 'active' : ''} type="button" onClick={() => setTab('attributes')}>Характеристики <span>{product.attributes.length}</span></button>
        <button className={tab === 'variants' ? 'active' : ''} type="button" onClick={() => setTab('variants')}>Вариации <span>{product.variants.length}</span></button>
        <button className={tab === 'stock' ? 'active' : ''} type="button" onClick={() => setTab('stock')}>Остатки</button>
      </div>

      <div className="editor-scroll">
        {tab === 'main' && (
          <div className="stack">
            <Card title="Основная карточка" subtitle="Эти поля сохраняются в реальный товар">
              <div className="form-grid readable">
                <LiveField
                  label="Название"
                  value={draft.name}
                  required
                  onChange={(value) => setDraft({ ...draft, name: value })}
                />
                <LiveField
                  label="SKU"
                  value={draft.sku}
                  required
                  onChange={(value) => setDraft({ ...draft, sku: value })}
                />
                <LiveField
                  label="GTIN / штрихкод"
                  value={draft.gtin}
                  onChange={(value) => setDraft({ ...draft, gtin: value })}
                />
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
                <LiveField
                  label="Приоритет"
                  value={draft.priority}
                  onChange={(value) => setDraft({ ...draft, priority: value })}
                  inputMode="numeric"
                />
              </div>
            </Card>

            <Card title="Описание" subtitle="Текст карточки на сайте">
              <textarea
                value={draft.description}
                onChange={(event) => setDraft({ ...draft, description: event.target.value })}
                disabled={!canUpdate}
              />
            </Card>
          </div>
        )}

        {tab === 'attributes' && (
          <Card
            title="Характеристики товара"
            subtitle="Это реальные связи product_product_attributes из базы"
          >
            {product.attributes.length === 0 ? (
              <EmptyState text="У товара нет характеристик." />
            ) : (
              <div className="attribute-list live-attributes">
                {product.attributes.map((attribute) => (
                  <div className="attribute-row" key={attribute.id}>
                    <span>{attribute.name}</span>
                    <strong>
                      {attribute.custom_value || attribute.value || '—'}
                    </strong>
                    <small>
                      {attribute.is_use_in_variations ? 'вариация' : ''}
                    </small>
                  </div>
                ))}
              </div>
            )}

            <div className="callout muted">
              <AlertTriangle size={17} />
              <div>
                <strong>Редактирование значений подключим следующим шагом</strong>
                <span>Сейчас здесь уже реальные данные товара, но изменение pivot-связей ещё не отправляется в backend.</span>
              </div>
            </div>
          </Card>
        )}

        {tab === 'variants' && (
          <Card title="Вариации" subtitle="Реальные торговые предложения товара">
            {product.variants.length === 0 ? (
              <EmptyState text="У товара нет вариаций." />
            ) : (
              <Table headers={['Название', 'SKU', 'Цена', 'Остаток', 'Статус']}>
                {product.variants.map((variant) => (
                  <tr key={variant.id}>
                    <td><strong>{variant.name}</strong></td>
                    <td>{variant.sku}</td>
                    <td>{formatMoney(variant.price)}</td>
                    <td>{variant.stock}</td>
                    <td><Status value={stateLabel(variant.state)} compact /></td>
                  </tr>
                ))}
              </Table>
            )}
          </Card>
        )}

        {tab === 'stock' && (
          <Card title="Остатки по складам" subtitle="Реальные записи product_warehouse_stocks">
            {product.warehouse_stocks.length === 0 ? (
              <EmptyState text="Отдельных складских остатков для товара нет." />
            ) : (
              <div className="stock-grid">
                {product.warehouse_stocks.map((stock) => (
                  <article className="stock-card" key={stock.warehouse_id}>
                    <Warehouse size={18} />
                    <div>
                      <strong>{stock.warehouse_name || `Склад #${stock.warehouse_id}`}</strong>
                      <small>ID {stock.warehouse_id}</small>
                    </div>
                    <em>{stock.quantity}</em>
                  </article>
                ))}
              </div>
            )}
          </Card>
        )}
      </div>

      <footer className="editor-foot">
        <span>
          {canUpdate
            ? 'Сохранение идёт через ProductPolicy и Laravel validation'
            : 'У пользователя нет разрешения update products'}
        </span>
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
            onClick={save}
            disabled={saving || !canUpdate}
          >
            {saving ? <Loader2 className="spin" size={15} /> : null}
            {saving ? 'Сохраняю…' : 'Сохранить'}
          </button>
        </div>
      </footer>
    </section>
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
                        <option value="string">Строка</option>
                        <option value="text">Текст</option>
                        <option value="integer">Целое число</option>
                        <option value="decimal">Число</option>
                        <option value="boolean">Да / нет</option>
                        <option value="select">Список</option>
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
  children: React.ReactNode
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
  children: React.ReactNode
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

function UnavailableWorkspace({
  icon: Icon,
  title,
  text,
}: {
  icon: typeof Warehouse
  title: string
  text: string
}) {
  return (
    <section className="panel workspace-state">
      <Icon size={28} />
      <h2>{title}</h2>
      <p>{text}</p>
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
