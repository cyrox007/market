import { useEffect, useMemo, useState } from 'react'
import {
  Bell,
  Boxes,
  CheckCircle2,
  ChevronDown,
  ChevronRight,
  CircleAlert,
  CircleCheck,
  ExternalLink,
  Eye,
  FolderTree,
  Image,
  LayoutDashboard,
  ClipboardList,
  Link2,
  ListFilter,
  MapPin,
  Moon,
  MoreHorizontal,
  Package,
  Pencil,
  Plus,
  Search,
  Settings,
  SlidersHorizontal,
  Store,
  Sun,
  Tag,
  Truck,
  Users,
  Warehouse,
} from 'lucide-react'

type Theme = 'light' | 'dark'
type ModuleKey = 'products' | 'attributes' | 'orders' | 'locations' | 'warehouses' | 'stores'
type SectionKind = 'categories' | 'rooms'
type ProductTab = 'main' | 'attributes' | 'variants' | 'stock' | 'media' | 'relations' | 'service'
type MainStep = 0 | 1 | 2

type Product = {
  id: number
  name: string
  sku: string
  price: string
  stock: number
  state: 'Активен' | 'Черновик' | 'Скрыт'
  category: string
  room: string
  variants: number
  initials: string
}

type TreeNode = {
  id: string
  label: string
  depth: number
  count: number
  note?: string
  active?: boolean
}

const categories: TreeNode[] = [
  { id: 'soft', label: 'Мягкая мебель', depth: 0, count: 684 },
  { id: 'sofas', label: 'Диваны', depth: 1, count: 418, active: true },
  { id: 'straight', label: 'Прямые диваны', depth: 2, count: 186 },
  { id: 'corner', label: 'Угловые диваны', depth: 2, count: 144 },
  { id: 'modular', label: 'Модульные диваны', depth: 2, count: 88 },
  { id: 'chairs', label: 'Кресла', depth: 1, count: 206 },
  { id: 'case', label: 'Корпусная мебель', depth: 0, count: 511 },
  { id: 'beds', label: 'Кровати', depth: 1, count: 173 },
  { id: 'tables', label: 'Столы и стулья', depth: 0, count: 298 },
]

const rooms: TreeNode[] = [
  { id: 'living', label: 'Гостиная', depth: 0, count: 722, note: '6 фильтров' },
  { id: 'soft-zone', label: 'Мягкая зона', depth: 1, count: 384, note: '4 фильтра', active: true },
  { id: 'small-living', label: 'Небольшая гостиная', depth: 2, count: 102, note: '7 фильтров' },
  { id: 'bedroom', label: 'Спальня', depth: 0, count: 489, note: '5 фильтров' },
  { id: 'kids', label: 'Детская', depth: 0, count: 211, note: '3 фильтра' },
  { id: 'kitchen', label: 'Кухня', depth: 0, count: 276, note: '2 фильтра' },
]

const products: Product[] = [
  { id: 1042, name: 'Диван прямой Лига-060', sku: 'SV-1042', price: '64 990 ₽', stock: 12, state: 'Активен', category: 'Диваны', room: 'Мягкая зона', variants: 8, initials: 'ЛГ' },
  { id: 1088, name: 'Диван угловой Лига-042', sku: 'SV-1088', price: '89 500 ₽', stock: 5, state: 'Активен', category: 'Диваны', room: 'Мягкая зона', variants: 12, initials: 'ЛГ' },
  { id: 1140, name: 'Диван прямой Мэдисон', sku: 'SV-1140', price: '52 700 ₽', stock: 0, state: 'Черновик', category: 'Диваны', room: 'Гостиная', variants: 6, initials: 'МД' },
  { id: 1221, name: 'Диван модульный Норд', sku: 'SV-1221', price: '104 900 ₽', stock: 3, state: 'Активен', category: 'Модульные диваны', room: 'Мягкая зона', variants: 16, initials: 'НР' },
  { id: 1304, name: 'Диван прямой Остин', sku: 'SV-1304', price: '73 400 ₽', stock: 7, state: 'Скрыт', category: 'Прямые диваны', room: 'Гостиная', variants: 4, initials: 'ОС' },
]

const attributeGroups = [
  {
    title: 'Габариты',
    description: 'Основные размеры',
    rows: [['Ширина', '238 см'], ['Глубина', '104 см'], ['Высота', '92 см'], ['Спальное место', '196 × 145 см']],
  },
  {
    title: 'Материалы',
    description: 'Каркас, обивка и наполнение',
    rows: [['Каркас', 'Фанера, ЛДСП'], ['Обивка', 'Велюр'], ['Наполнитель', 'ППУ, пружинная змейка'], ['Опоры', 'Пластик']],
  },
  {
    title: 'Внешний вид',
    description: 'Коммерческие свойства',
    rows: [['Цвет', 'Серый'], ['Стиль', 'Современный'], ['Механизм', 'Еврокнижка'], ['Подлокотники', 'Мягкие']],
  },
]

const variants = [
  ['Серый / 238 см', 'SV-1042-GR', '64 990 ₽', '4'],
  ['Бежевый / 238 см', 'SV-1042-BE', '66 490 ₽', '3'],
  ['Графит / 238 см', 'SV-1042-GF', '66 490 ₽', '2'],
  ['Зелёный / 238 см', 'SV-1042-GN', '67 900 ₽', '3'],
]

const nav: Array<{
  title: string
  items: Array<[ModuleKey, string, typeof Package]>
}> = [
  {
    title: 'Каталог',
    items: [
      ['products', 'Товары и разделы', Package],
      ['attributes', 'Характеристики', SlidersHorizontal],
    ],
  },
  {
    title: 'Продажи',
    items: [
      ['orders', 'Заказы', ClipboardList],
      ['stores', 'Магазины', Store],
    ],
  },
  {
    title: 'Логистика',
    items: [
      ['locations', 'Локации', MapPin],
      ['warehouses', 'Склады', Warehouse],
    ],
  },
]

function initialTheme(): Theme {
  const saved = localStorage.getItem('sv-admin-theme')

  if (saved === 'light' || saved === 'dark') {
    return saved
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export function App() {
  const [theme, setTheme] = useState<Theme>(initialTheme)
  const [module, setModule] = useState<ModuleKey>('products')
  const [sectionKind, setSectionKind] = useState<SectionKind>('categories')
  const [productId, setProductId] = useState(products[0].id)
  const [tab, setTab] = useState<ProductTab>('main')
  const [mainStep, setMainStep] = useState<MainStep>(0)
  const [filledOnly, setFilledOnly] = useState(true)
  const [showProblems, setShowProblems] = useState(true)

  useEffect(() => {
    document.documentElement.dataset.theme = theme
    localStorage.setItem('sv-admin-theme', theme)
  }, [theme])

  const product = useMemo(
    () => products.find((item) => item.id === productId) ?? products[0],
    [productId],
  )

  const openProblem = () => {
    setTab('main')
    setMainStep(0)
    setShowProblems(true)
  }

  return (
    <div className="app">
      <Topbar theme={theme} setTheme={setTheme} />

      <div className="frame">
        <Sidebar module={module} setModule={setModule} />

        <main className="workspace">
          <ModuleHeader module={module} />

          {module === 'products' && (
            <div className="catalog-layout">
              <SectionTree kind={sectionKind} setKind={setSectionKind} />
              <ProductList selected={productId} select={setProductId} />
              <ProductEditor
                product={product}
                tab={tab}
                setTab={setTab}
                mainStep={mainStep}
                setMainStep={setMainStep}
                filledOnly={filledOnly}
                setFilledOnly={setFilledOnly}
                showProblems={showProblems}
                setShowProblems={setShowProblems}
                openProblem={openProblem}
              />
            </div>
          )}

          {module === 'attributes' && <AttributesWorkspace />}
          {module === 'orders' && <OrdersWorkspace />}
          {module === 'locations' && <LocationsWorkspace />}
          {module === 'warehouses' && <WarehousesWorkspace />}
          {module === 'stores' && <StoresWorkspace />}
        </main>
      </div>
    </div>
  )
}

function Topbar({ theme, setTheme }: { theme: Theme; setTheme: (value: Theme) => void }) {
  return (
    <header className="topbar">
      <div className="brand">
        <span className="brand-mark"><Package size={18} /></span>
        <div><strong>Светофор Мебели</strong><small>Новая админ-панель · прототип</small></div>
      </div>

      <div className="top-tools">
        <label className="global-search">
          <Search size={16} />
          <input placeholder="Товар, SKU, раздел..." />
          <kbd>⌘ K</kbd>
        </label>

        <a className="icon-btn" href="/" target="_blank" rel="noreferrer" aria-label="Открыть сайт">
          <ExternalLink size={18} />
        </a>

        <button
          className="icon-btn"
          type="button"
          aria-label="Переключить тему"
          onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
        >
          {theme === 'light' ? <Moon size={18} /> : <Sun size={18} />}
        </button>

        <button className="icon-btn notify" type="button" aria-label="Уведомления">
          <Bell size={18} />
          <i />
        </button>

        <button className="user-btn" type="button">
          <span>SA</span>
          <div><strong>Администратор</strong><small>Каталог и контент</small></div>
          <ChevronDown size={15} />
        </button>
      </div>
    </header>
  )
}

function Sidebar({
  module,
  setModule,
}: {
  module: ModuleKey
  setModule: (value: ModuleKey) => void
}) {
  return (
    <aside className="sidebar">
      <nav>
        {nav.map((group) => (
          <div className="nav-group" key={group.title}>
            <div className="nav-title">{group.title}</div>

            {group.items.map(([key, label, Icon]) => (
              <button
                className={module === key ? 'nav-link active' : 'nav-link'}
                type="button"
                onClick={() => setModule(key)}
                key={key}
              >
                <Icon size={18} />
                <span>{label}</span>
              </button>
            ))}
          </div>
        ))}
      </nav>

      <div className="sidebar-foot">
        <span />
        <div><strong>develop</strong><small>Актуальная схема проекта</small></div>
      </div>
    </aside>
  )
}

const moduleHeaders: Record<ModuleKey, {
  eyebrow: string
  title: string
  description: string
}> = {
  products: {
    eyebrow: 'Рабочее место оператора',
    title: 'Товары и разделы',
    description: 'Категории, комнаты, список товаров и редактор находятся рядом. Прокручивается только активная рабочая область.',
  },
  attributes: {
    eyebrow: 'Справочник каталога',
    title: 'Характеристики товаров',
    description: 'Отдельно настраиваем структуру свойства и его значения. В карточке товара оператор только назначает готовые свойства.',
  },
  orders: {
    eyebrow: 'Продажи',
    title: 'Заказы',
    description: 'Список заказов и карточка выбранного заказа в одном экране без постоянных переходов назад.',
  },
  locations: {
    eyebrow: 'Логистика',
    title: 'Локации доставки',
    description: 'Дерево территорий и настройки выбранной локации: тарифы, обработка, перевозчики и услуги.',
  },
  warehouses: {
    eyebrow: 'Логистика',
    title: 'Склады',
    description: 'Склад, остатки и способы доставки разделены на понятные рабочие вкладки.',
  },
  stores: {
    eyebrow: 'Продажи',
    title: 'Магазины',
    description: 'Точки продаж: адреса, контакты, режим работы и параметры отображения на сайте.',
  },
}

function ModuleHeader({ module }: { module: ModuleKey }) {
  const meta = moduleHeaders[module]

  return (
    <header className="page-head">
      <div>
        <span className="eyebrow">{meta.eyebrow}</span>
        <h1>{meta.title}</h1>
        <p>{meta.description}</p>
      </div>
    </header>
  )
}

function SectionTree({ kind, setKind }: { kind: SectionKind; setKind: (value: SectionKind) => void }) {
  const nodes = kind === 'categories' ? categories : rooms

  return (
    <section className="panel section-panel">
      <PanelHead eyebrow="Структура" title="Разделы" />

      <div className="segmented">
        <button className={kind === 'categories' ? 'active' : ''} type="button" onClick={() => setKind('categories')}>Категории</button>
        <button className={kind === 'rooms' ? 'active' : ''} type="button" onClick={() => setKind('rooms')}>Комнаты</button>
      </div>

      <label className="small-search">
        <Search size={14} />
        <input placeholder={kind === 'categories' ? 'Найти категорию' : 'Найти комнату'} />
      </label>

      <div className="tree">
        {nodes.map((node) => (
          <button
            className={node.active ? 'tree-row active' : 'tree-row'}
            style={{ paddingLeft: 8 + node.depth * 13 }}
            type="button"
            key={node.id}
          >
            <span>{node.depth > 0 ? <ChevronRight size={12} /> : <FolderTree size={14} />}</span>
            <div><strong>{node.label}</strong>{node.note && <small>{node.note}</small>}</div>
            <em>{node.count}</em>
          </button>
        ))}
      </div>

      <footer className="panel-foot">
        <strong>{kind === 'categories' ? 'Категории' : 'Комнаты'}</strong>
        <span>
          {kind === 'categories'
            ? 'Основное дерево каталога и правила характеристик.'
            : 'Отдельная таксономия до 4 уровней с наследуемыми фильтрами.'}
        </span>
      </footer>
    </section>
  )
}

function ProductList({ selected, select }: { selected: number; select: (id: number) => void }) {
  return (
    <section className="panel products-panel">
      <PanelHead eyebrow="Категория" title="Диваны" subtitle="418 товаров · включая дочерние разделы" />

      <div className="product-tools">
        <label className="small-search">
          <Search size={14} />
          <input placeholder="Название или SKU" />
        </label>
        <button type="button">Активные <ChevronDown size={13} /></button>
      </div>

      <div className="products">
        {products.map((product) => (
          <button
            className={selected === product.id ? 'product-row selected' : 'product-row'}
            type="button"
            onClick={() => select(product.id)}
            key={product.id}
          >
            <span className="thumb">{product.initials}</span>
            <div>
              <strong>{product.name}</strong>
              <small>{product.sku} · {product.price}</small>
              <p><em>{product.state}</em><span>{product.stock} шт.</span><span>{product.variants} вар.</span></p>
            </div>
          </button>
        ))}
      </div>

      <footer className="panel-foot pager">
        <span>1–5 из 418</span>
        <div><button type="button">‹</button><button type="button">›</button></div>
      </footer>
    </section>
  )
}

function ProductEditor({
  product,
  tab,
  setTab,
  mainStep,
  setMainStep,
  filledOnly,
  setFilledOnly,
  showProblems,
  setShowProblems,
  openProblem,
}: {
  product: Product
  tab: ProductTab
  setTab: (value: ProductTab) => void
  mainStep: MainStep
  setMainStep: (value: MainStep) => void
  filledOnly: boolean
  setFilledOnly: (value: boolean) => void
  showProblems: boolean
  setShowProblems: (value: boolean) => void
  openProblem: () => void
}) {
  return (
    <section className="panel editor">
      <div className="editor-head">
        <div className="product-title">
          <span className="hero-thumb">{product.initials}</span>
          <div>
            <span className="eyebrow">Товар #{product.id}</span>
            <h2>{product.name}</h2>
            <p>{product.sku} · {product.category} · {product.room}</p>
          </div>
        </div>

        <div className="editor-actions">
          <Status value={product.state} />
          <button className="btn ghost small" type="button"><Eye size={14} /> На сайте</button>
          <button className="icon-btn" type="button" aria-label="Дополнительные действия"><MoreHorizontal size={17} /></button>
        </div>
      </div>

      <CompletenessBar
        showProblems={showProblems}
        setShowProblems={setShowProblems}
        openProblem={openProblem}
      />

      <EditorTabs tab={tab} setTab={setTab} variants={product.variants} />

      <div className="editor-scroll">
        {tab === 'main' && <MainTab product={product} step={mainStep} setStep={setMainStep} />}
        {tab === 'attributes' && <AttributesTab filledOnly={filledOnly} setFilledOnly={setFilledOnly} />}
        {tab === 'variants' && <VariantsTab />}
        {tab === 'stock' && <StockTab />}
        {tab === 'media' && <MediaTab />}
        {tab === 'relations' && <RelationsTab product={product} />}
        {tab === 'service' && <ServiceTab product={product} />}
      </div>

      <footer className="editor-foot">
        <span><CircleCheck size={15} /> Последнее сохранение 2 минуты назад</span>
        <div>
          <button className="btn ghost" type="button">Отменить</button>
          <button className="btn primary" type="button">Сохранить</button>
        </div>
      </footer>
    </section>
  )
}

function CompletenessBar({
  showProblems,
  setShowProblems,
  openProblem,
}: {
  showProblems: boolean
  setShowProblems: (value: boolean) => void
  openProblem: () => void
}) {
  return (
    <div className={showProblems ? 'quality quality-open' : 'quality'}>
      <div className="quality-summary">
        <div className="quality-score">
          <div className="progress-ring"><span>82%</span></div>
          <div><strong>Заполненность карточки</strong><small>Обязательные поля: 7 из 7</small></div>
        </div>

        <div className="quality-chips">
          <span className="chip ok"><CheckCircle2 size={13} /> Обязательные 7/7</span>
          <button className="chip error" type="button" onClick={openProblem}><CircleAlert size={13} /> 1 ошибка</button>
          <span className="chip warn">3 рекомендации</span>
        </div>

        <button className="quality-toggle" type="button" onClick={() => setShowProblems(!showProblems)}>
          {showProblems ? 'Скрыть' : 'Показать проблемы'}
          <ChevronDown size={14} />
        </button>
      </div>

      {showProblems && (
        <div className="quality-problems">
          <button type="button" onClick={openProblem}>
            <CircleAlert size={15} />
            <div>
              <strong>Цена до скидки должна быть выше текущей цены</strong>
              <span>Основное → Шаг 1 «Карточка» → Цена до скидки</span>
            </div>
            <ChevronRight size={15} />
          </button>
          <div className="quality-note">
            <strong>Рекомендации:</strong> заполнить SEO-описание, добавить фото интерьера, указать гарантию.
          </div>
        </div>
      )}
    </div>
  )
}

function EditorTabs({ tab, setTab, variants }: { tab: ProductTab; setTab: (value: ProductTab) => void; variants: number }) {
  const items: Array<[ProductTab, string, number?]> = [
    ['main', 'Основное'],
    ['attributes', 'Характеристики'],
    ['variants', 'Вариации', variants],
    ['stock', 'Остатки'],
    ['media', 'Медиа'],
    ['relations', 'Связи'],
    ['service', 'SEO / 1С'],
  ]

  return (
    <div className="tabs">
      {items.map(([key, label, count]) => (
        <button className={tab === key ? 'active' : ''} type="button" onClick={() => setTab(key)} key={key}>
          {label}
          {count !== undefined && <span>{count}</span>}
        </button>
      ))}
    </div>
  )
}

function MainTab({ product, step, setStep }: { product: Product; step: MainStep; setStep: (value: MainStep) => void }) {
  const steps = [
    ['Карточка', 'Название, идентификаторы и цены'],
    ['Размещение', 'Категории, комнаты и описание'],
    ['Публикация', 'Статус, приоритет и проверка'],
  ] as const

  return (
    <div className="step-layout">
      <aside className="step-nav">
        <div className="step-nav-title">Основное</div>

        {steps.map(([title, text], index) => (
          <button
            className={step === index ? 'step-item active' : 'step-item'}
            type="button"
            onClick={() => setStep(index as MainStep)}
            key={title}
          >
            <span>{index + 1}</span>
            <div><strong>{title}</strong><small>{text}</small></div>
            {index < step && <CheckCircle2 size={15} />}
          </button>
        ))}

        <div className="step-hint">
          <strong>Зачем шаги?</strong>
          <span>На экране только один смысловой блок. Оператор не ищет поля в длинной форме.</span>
        </div>
      </aside>

      <div className="step-content">
        {step === 0 && (
          <Card title="Шаг 1. Карточка товара" subtitle="Основные данные, которые оператор меняет чаще всего">
            <div className="form-grid">
              <Field label="Название" value={product.name} wide required />
              <Field label="SKU" value={product.sku} required />
              <Field label="GTIN / штрихкод" value="4601234567890" />
              <Field label="Цена" value="64 990" suffix="₽" required />
              <Field label="Цена до скидки" value="59 990" suffix="₽" error="Должна быть выше текущей цены" />
            </div>
          </Card>
        )}

        {step === 1 && (
          <div className="stack">
            <Card title="Шаг 2. Размещение" subtitle="Категории и комнаты — две разные структуры каталога">
              <Tags label="Категории" values={['Мягкая мебель', 'Диваны', 'Прямые диваны']} required />
              <Tags label="Комнаты" values={['Гостиная', 'Мягкая зона']} secondary />
            </Card>

            <Card title="Описание" subtitle="Контент карточки товара на сайте">
              <textarea defaultValue="Прямой диван для современной гостиной. Мягкие подлокотники, механизм трансформации «Еврокнижка», вместительный бельевой ящик." />
            </Card>
          </div>
        )}

        {step === 2 && (
          <div className="stack">
            <Card title="Шаг 3. Публикация" subtitle="Финальная проверка перед публикацией">
              <div className="form-grid">
                <label className="field">
                  <span>Статус <b>*</b></span>
                  <select defaultValue={product.state}>
                    <option>Активен</option>
                    <option>Черновик</option>
                    <option>Скрыт</option>
                  </select>
                </label>
                <Field label="Приоритет" value="100" />
              </div>
            </Card>

            <div className="checklist">
              <div className="checklist-head"><CheckCircle2 size={18} /><div><strong>Критерии готовности</strong><span>Оператор видит, что мешает публикации, до сохранения.</span></div></div>
              <ul>
                <li className="done"><CheckCircle2 size={15} /> Название и SKU заполнены</li>
                <li className="done"><CheckCircle2 size={15} /> Есть категория каталога</li>
                <li className="done"><CheckCircle2 size={15} /> Указана цена продажи</li>
                <li className="problem"><CircleAlert size={15} /> Исправить цену до скидки</li>
                <li><CircleAlert size={15} /> Рекомендуется заполнить SEO-описание</li>
              </ul>
            </div>
          </div>
        )}

        <div className="step-controls">
          <button className="btn ghost" type="button" disabled={step === 0} onClick={() => setStep(Math.max(0, step - 1) as MainStep)}>Назад</button>
          <span>Шаг {step + 1} из 3</span>
          <button className="btn secondary" type="button" disabled={step === 2} onClick={() => setStep(Math.min(2, step + 1) as MainStep)}>Далее</button>
        </div>
      </div>
    </div>
  )
}

function AttributesTab({ filledOnly, setFilledOnly }: { filledOnly: boolean; setFilledOnly: (value: boolean) => void }) {
  return (
    <div className="attributes-tab">
      <div className="tab-toolbar">
        <label className="small-search"><Search size={14} /><input placeholder="Найти характеристику" /></label>

        <label className="toggle-line">
          <input type="checkbox" checked={filledOnly} onChange={(event) => setFilledOnly(event.target.checked)} />
          <span />
          Только заполненные
        </label>

        <button className="btn secondary small" type="button"><Plus size={14} /> Добавить</button>
      </div>

      <div className="attribute-grid">
        {attributeGroups.map((group) => <AttributeCard key={group.title} {...group} />)}

        {!filledOnly && (
          <AttributeCard
            title="Дополнительные"
            description="Необязательные свойства"
            rows={[['Страна производства', '—'], ['Гарантия', '—'], ['Тип ткани', '—'], ['Коллекция', '—']]}
          />
        )}
      </div>
    </div>
  )
}

function AttributeCard({ title, description, rows }: { title: string; description: string; rows: string[][] }) {
  return (
    <Card title={title} subtitle={description}>
      <div className="attribute-list">
        {rows.map(([label, value]) => (
          <div className="attribute-row" key={label}>
            <span>{label}</span>
            <strong className={value === '—' ? 'empty' : ''}>{value}</strong>
            <button className="row-action" type="button" aria-label={'Изменить ' + label}><Pencil size={13} /></button>
          </div>
        ))}
      </div>
    </Card>
  )
}

function VariantsTab() {
  return (
    <SingleTab
      title="Торговые предложения"
      subtitle="Цвет и размер живут на уровне вариаций и не дублируются в характеристиках родителя."
      action="Добавить вариацию"
    >
      <div className="info-line">
        <CircleCheck size={16} />
        Атрибуты вариаций: <strong>Цвет</strong>, <strong>Размер</strong>. Их можно изменить отдельным действием, не смешивая с таблицей вариантов.
      </div>

      <Table headers={['Вариация', 'SKU', 'Цена', 'Остаток', 'Статус']}>
        {variants.map((row) => (
          <tr key={row[1]}>
            <td><strong>{row[0]}</strong></td>
            <td>{row[1]}</td>
            <td>{row[2]}</td>
            <td>{row[3]} шт.</td>
            <td><Status value="Активен" compact /></td>
          </tr>
        ))}
      </Table>
    </SingleTab>
  )
}

function StockTab() {
  const rows = [['Воронеж', '7', '1'], ['Москва', '5', '0'], ['Белгород', '0', '0']]

  return (
    <SingleTab title="Остатки по складам" subtitle="Распределение товара без перехода в отдельный ресурс.">
      <div className="stock-grid">
        {rows.map(([name, qty, reserve]) => (
          <article className="stock-card" key={name}>
            <Warehouse size={18} />
            <div><strong>{name}</strong><small>Резерв: {reserve}</small></div>
            <em>{qty}</em>
          </article>
        ))}
      </div>

      <div className="callout muted">
        <Warehouse size={17} />
        <div><strong>Складской учёт включён</strong><span>Для вариативного товара остаток можно раскрыть до каждой вариации.</span></div>
      </div>
    </SingleTab>
  )
}

function MediaTab() {
  return (
    <SingleTab title="Изображения" subtitle="Главное изображение и галерея в одном месте." action="Загрузить">
      <div className="media-grid">
        {['Главное', 'Фасад', 'Сбоку', 'В интерьере'].map((label, index) => (
          <article className={index === 0 ? 'media-card main' : 'media-card'} key={label}>
            <Image size={26} />
            <strong>{label}</strong>
            <small>{index === 0 ? 'Используется в каталоге' : 'Галерея товара'}</small>
          </article>
        ))}
      </div>
    </SingleTab>
  )
}

function RelationsTab({ product }: { product: Product }) {
  const rows = [
    [Link2, 'Связанные товары', '6', 'Альтернативы и сопутствующие'],
    [Package, 'Комплекты', '3', 'Товары в комплекте'],
    [MapPin, 'Региональные правила', '2', 'Доступность по регионам'],
    [FolderTree, 'Категории', '3', product.category],
  ] as const

  return (
    <div className="relation-grid">
      {rows.map(([Icon, title, value, text]) => (
        <article className="relation-card" key={title}>
          <span><Icon size={18} /></span>
          <div><small>{title}</small><strong>{value}</strong><p>{text}</p></div>
          <ChevronRight size={16} />
        </article>
      ))}
    </div>
  )
}

function ServiceTab({ product }: { product: Product }) {
  return (
    <div className="two-col">
      <Card title="SEO" subtitle="Редкие поля вынесены из основной работы оператора">
        <div className="form-grid">
          <Field label="URL" value="divan-pryamoy-liga-060" wide />
          <Field label="SEO title" value={product.name} wide />
          <Field label="SEO description" value="" placeholder="Не заполнено" wide />
        </div>
      </Card>

      <Card title="Интеграция с 1С" subtitle="Служебные идентификаторы не мешают основному редактированию">
        <div className="form-grid">
          <Field label="ID 1С" value="7e0d84c1-90b3-4a73-85a2-001042" wide />
          <Field label="SKU" value={product.sku} />
          <Field label="Источник" value="1С" />
        </div>
      </Card>
    </div>
  )
}

function SingleTab({
  title,
  subtitle,
  action,
  children,
}: {
  title: string
  subtitle: string
  action?: string
  children: React.ReactNode
}) {
  return (
    <div className="single-tab">
      <div className="single-head">
        <div><h3>{title}</h3><p>{subtitle}</p></div>
        {action && <button className="btn primary small" type="button"><Plus size={14} /> {action}</button>}
      </div>
      {children}
    </div>
  )
}

function Card({ title, subtitle, children }: { title: string; subtitle: string; children: React.ReactNode }) {
  return (
    <section className="card">
      <header><h3>{title}</h3><p>{subtitle}</p></header>
      <div className="card-body">{children}</div>
    </section>
  )
}

function Field({
  label,
  value,
  suffix,
  wide,
  placeholder,
  required,
  error,
}: {
  label: string
  value: string
  suffix?: string
  wide?: boolean
  placeholder?: string
  required?: boolean
  error?: string
}) {
  return (
    <label className={wide ? 'field wide' : 'field'}>
      <span>{label} {required && <b>*</b>}</span>
      <div className={error ? 'input-wrap invalid' : 'input-wrap'}>
        <input key={value} defaultValue={value} placeholder={placeholder} />
        {suffix && <em>{suffix}</em>}
      </div>
      {error && <small className="field-error"><CircleAlert size={12} /> {error}</small>}
    </label>
  )
}

function Tags({
  label,
  values,
  secondary,
  required,
}: {
  label: string
  values: string[]
  secondary?: boolean
  required?: boolean
}) {
  return (
    <div className="tag-field">
      <span>{label} {required && <b>*</b>}</span>
      <div>
        {values.map((value) => (
          <button className={secondary ? 'tag secondary' : 'tag'} type="button" key={value}>{value}</button>
        ))}
        <button className="tag add" type="button"><Plus size={11} />Добавить</button>
      </div>
    </div>
  )
}

function Status({ value, compact }: { value: string; compact?: boolean }) {
  const ok = value === 'Активен'

  return (
    <span className={'status ' + (ok ? 'ok ' : '') + (compact ? 'compact' : '')}>
      <i />
      {value}
    </span>
  )
}

function PanelHead({ eyebrow, title, subtitle }: { eyebrow: string; title: string; subtitle?: string }) {
  return (
    <header className="panel-head">
      <div>
        <span className="eyebrow">{eyebrow}</span>
        <h2>{title}</h2>
        {subtitle && <p>{subtitle}</p>}
      </div>
      <button className="icon-btn compact" type="button"><Plus size={16} /></button>
    </header>
  )
}

function Table({ headers, children }: { headers: string[]; children: React.ReactNode }) {
  return (
    <div className="table-wrap">
      <table>
        <thead><tr>{headers.map((header) => <th key={header}>{header}</th>)}</tr></thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  )
}


type AttributePrototype = {
  id: number
  name: string
  slug: string
  type: string
  values: string[]
  flags: string[]
  usage: number
}

const prototypeAttributes: AttributePrototype[] = [
  { id: 1, name: 'Цвет', slug: 'color', type: 'Список', values: ['Серый', 'Бежевый', 'Графит', 'Зелёный', 'Синий'], flags: ['Вариации', 'Фильтр', 'Обязательная'], usage: 418 },
  { id: 2, name: 'Размер', slug: 'size', type: 'Список', values: ['180 см', '200 см', '220 см', '238 см'], flags: ['Вариации', 'Фильтр'], usage: 356 },
  { id: 3, name: 'Материал обивки', slug: 'upholstery', type: 'Список', values: ['Велюр', 'Рогожка', 'Шенилл', 'Экокожа'], flags: ['Фильтр'], usage: 302 },
  { id: 4, name: 'Механизм', slug: 'mechanism', type: 'Список', values: ['Еврокнижка', 'Дельфин', 'Аккордеон'], flags: ['Фильтр'], usage: 188 },
  { id: 5, name: 'Гарантия', slug: 'warranty', type: 'Текст', values: [], flags: ['Ручное значение'], usage: 91 },
]

function AttributesWorkspace() {
  const [selectedId, setSelectedId] = useState(prototypeAttributes[0].id)
  const [flags, setFlags] = useState(prototypeAttributes[0].flags)
  const selected = prototypeAttributes.find((item) => item.id === selectedId) ?? prototypeAttributes[0]

  const choose = (attribute: AttributePrototype) => {
    setSelectedId(attribute.id)
    setFlags(attribute.flags)
  }

  const toggleFlag = (flag: string) => {
    setFlags((current) => current.includes(flag)
      ? current.filter((item) => item !== flag)
      : [...current, flag])
  }

  return (
    <div className="module-layout attributes-workspace">
      <section className="panel module-list">
        <PanelHead eyebrow="Справочник" title="Свойства" subtitle="Что можно назначать товарам" />
        <label className="small-search workspace-search"><Search size={16} /><input placeholder="Найти характеристику" /></label>

        <div className="module-list-scroll">
          {prototypeAttributes.map((attribute) => (
            <button
              className={attribute.id === selected.id ? 'entity-row selected' : 'entity-row'}
              type="button"
              onClick={() => choose(attribute)}
              key={attribute.id}
            >
              <span className="entity-icon"><SlidersHorizontal size={17} /></span>
              <div>
                <strong>{attribute.name}</strong>
                <small>{attribute.type} · {attribute.usage} товаров</small>
              </div>
              <ChevronRight size={16} />
            </button>
          ))}
        </div>
      </section>

      <section className="panel module-detail">
        <div className="module-detail-head">
          <div>
            <span className="eyebrow">Характеристика</span>
            <h2>{selected.name}</h2>
            <p>Сначала задаём поведение свойства. Затем — допустимые значения. После этого оператор выбирает их в товаре.</p>
          </div>
          <button className="btn primary" type="button">Сохранить</button>
        </div>

        <div className="workflow-strip">
          <div className="done"><span>1</span><div><strong>Настройка</strong><small>Тип и поведение</small></div></div>
          <ChevronRight size={16} />
          <div className={selected.type === 'Список' ? 'active' : ''}><span>2</span><div><strong>Значения</strong><small>Справочник вариантов</small></div></div>
          <ChevronRight size={16} />
          <div><span>3</span><div><strong>Использование</strong><small>Назначение товарам</small></div></div>
        </div>

        <div className="module-detail-scroll">
          <div className="attribute-settings-grid">
            <Card title="Основные настройки" subtitle="Редко меняются после начала использования">
              <div className="form-grid readable">
                <Field label="Название" value={selected.name} required />
                <Field label="Код" value={selected.slug} required />
                <label className="field">
                  <span>Тип значения <b>*</b></span>
                  <select defaultValue={selected.type}>
                    <option>Список</option>
                    <option>Текст</option>
                    <option>Число</option>
                    <option>Логическое</option>
                  </select>
                </label>
                <Field label="Порядок" value="100" />
              </div>
            </Card>

            <Card title="Поведение" subtitle="Понятные переключатели вместо набора технических флагов">
              <div className="flag-grid">
                {[
                  ['Вариации', 'Цвет/размер создают отдельные торговые предложения'],
                  ['Фильтр', 'Показывать покупателю в фильтрах каталога'],
                  ['Обязательная', 'Без значения карточка считается незаполненной'],
                  ['Множественная', 'У товара можно выбрать несколько значений'],
                  ['Ручное значение', 'Разрешить оператору ввести значение вне справочника'],
                ].map(([flag, description]) => (
                  <button
                    className={flags.includes(flag) ? 'flag-card enabled' : 'flag-card'}
                    type="button"
                    onClick={() => toggleFlag(flag)}
                    key={flag}
                  >
                    <span className="fake-switch"><i /></span>
                    <div><strong>{flag}</strong><small>{description}</small></div>
                  </button>
                ))}
              </div>
            </Card>
          </div>

          {selected.type === 'Список' ? (
            <Card title="Допустимые значения" subtitle="Именно этот список увидит оператор при редактировании товара">
              <div className="values-toolbar">
                <label className="small-search values-search"><Search size={15} /><input placeholder="Найти значение" /></label>
                <button className="btn secondary small" type="button"><Plus size={15} /> Добавить значение</button>
              </div>
              <div className="value-grid">
                {selected.values.map((value, index) => (
                  <button className="value-card" type="button" key={value}>
                    <span>{index + 1}</span>
                    <strong>{value}</strong>
                    <small>{selected.slug}-{index + 1}</small>
                    <Pencil size={14} />
                  </button>
                ))}
              </div>
            </Card>
          ) : (
            <div className="callout muted">
              <CircleCheck size={18} />
              <div><strong>Справочник значений не нужен</strong><span>Для текстового свойства оператор вводит значение непосредственно в карточке товара.</span></div>
            </div>
          )}
        </div>
      </section>
    </div>
  )
}

const prototypeOrders = [
  ['ORD202609230001', 'Иван Петров', '124 500 ₽', 'Новый', 'Сегодня, 10:42'],
  ['ORD202609220018', 'Анна Смирнова', '68 990 ₽', 'Принят', 'Вчера, 18:05'],
  ['ORD202609220011', 'Сергей Волков', '91 200 ₽', 'В пути', 'Вчера, 13:27'],
  ['ORD202609210044', 'Мария Котова', '47 300 ₽', 'Доставлен', '21 сен, 16:10'],
]

function OrdersWorkspace() {
  const [selected, setSelected] = useState(0)
  const [tab, setTab] = useState<'items' | 'delivery' | 'payment' | 'history'>('items')
  const order = prototypeOrders[selected]

  return (
    <div className="module-layout split-workspace">
      <section className="panel module-list wide-list">
        <PanelHead eyebrow="Очередь" title="Заказы" subtitle="Последние заказы" />
        <label className="small-search workspace-search"><Search size={16} /><input placeholder="Номер, имя, телефон" /></label>
        <div className="module-list-scroll">
          {prototypeOrders.map((item, index) => (
            <button className={selected === index ? 'order-row selected' : 'order-row'} type="button" onClick={() => setSelected(index)} key={item[0]}>
              <div><strong>{item[0]}</strong><small>{item[1]} · {item[4]}</small></div>
              <div><strong>{item[2]}</strong><Status value={item[3] === 'Новый' ? 'Черновик' : 'Активен'} compact /></div>
            </button>
          ))}
        </div>
      </section>

      <section className="panel module-detail">
        <div className="module-detail-head">
          <div><span className="eyebrow">Заказ</span><h2>{order[0]}</h2><p>{order[1]} · {order[2]}</p></div>
          <div className="editor-actions"><button className="btn ghost" type="button">Печать</button><button className="btn primary" type="button">Изменить статус</button></div>
        </div>

        <div className="tabs static-tabs">
          <button className={tab === 'items' ? 'active' : ''} type="button" onClick={() => setTab('items')}>Состав</button>
          <button className={tab === 'delivery' ? 'active' : ''} type="button" onClick={() => setTab('delivery')}>Доставка</button>
          <button className={tab === 'payment' ? 'active' : ''} type="button" onClick={() => setTab('payment')}>Оплата</button>
          <button className={tab === 'history' ? 'active' : ''} type="button" onClick={() => setTab('history')}>История</button>
        </div>

        <div className="module-detail-scroll">
          {tab === 'items' && (
            <>
              <div className="summary-grid">
                <Card title="Покупатель" subtitle="Контактные данные">
                  <dl className="detail-list"><div><dt>Имя</dt><dd>{order[1]}</dd></div><div><dt>Телефон</dt><dd>+7 900 123-45-67</dd></div><div><dt>Email</dt><dd>client@example.ru</dd></div></dl>
                </Card>
                <Card title="Заказ" subtitle="Ключевые параметры">
                  <dl className="detail-list"><div><dt>Сумма</dt><dd>{order[2]}</dd></div><div><dt>Статус</dt><dd>{order[3]}</dd></div><div><dt>Создан</dt><dd>{order[4]}</dd></div></dl>
                </Card>
              </div>

              <Card title="Состав заказа" subtitle="3 позиции">
                <Table headers={['Товар', 'Количество', 'Цена', 'Сумма']}>
                  <tr><td><strong>Диван прямой Лига-060</strong><small className="table-sub">Серый / 238 см</small></td><td>1</td><td>64 990 ₽</td><td>64 990 ₽</td></tr>
                  <tr><td><strong>Кресло Лига</strong><small className="table-sub">Серый</small></td><td>1</td><td>31 500 ₽</td><td>31 500 ₽</td></tr>
                  <tr><td><strong>Пуф Лига</strong></td><td>1</td><td>28 010 ₽</td><td>28 010 ₽</td></tr>
                </Table>
              </Card>
            </>
          )}

          {tab === 'delivery' && (
            <div className="summary-grid">
              <Card title="Адрес доставки" subtitle="Снимок на момент оформления">
                <dl className="detail-list"><div><dt>Город</dt><dd>Воронеж</dd></div><div><dt>Адрес</dt><dd>ул. Ленина, 10</dd></div><div><dt>Получатель</dt><dd>{order[1]}</dd></div></dl>
              </Card>
              <Card title="Логистика" subtitle="Склад и выбранный вариант">
                <dl className="detail-list"><div><dt>Склад</dt><dd>Воронеж</dd></div><div><dt>Способ</dt><dd>Курьерская доставка</dd></div><div><dt>Стоимость</dt><dd>900 ₽</dd></div><div><dt>Срок</dt><dd>1–2 дня</dd></div></dl>
              </Card>
            </div>
          )}

          {tab === 'payment' && (
            <div className="summary-grid">
              <Card title="Оплата" subtitle="Текущее состояние">
                <dl className="detail-list"><div><dt>Метод</dt><dd>Банковская карта</dd></div><div><dt>Сумма</dt><dd>{order[2]}</dd></div><div><dt>Статус</dt><dd>Оплачено</dd></div></dl>
              </Card>
              <Card title="Транзакция" subtitle="Служебная информация">
                <dl className="detail-list"><div><dt>ID</dt><dd>PAY-23091842</dd></div><div><dt>Шлюз</dt><dd>Raiffeisen</dd></div><div><dt>Время</dt><dd>10:44</dd></div></dl>
              </Card>
            </div>
          )}

          {tab === 'history' && (
            <Card title="История заказа" subtitle="Изменения статусов и ключевые события">
              <div className="timeline">
                <div><span /><strong>Заказ создан</strong><small>Сегодня, 10:42</small></div>
                <div><span /><strong>Оплата подтверждена</strong><small>Сегодня, 10:44</small></div>
                <div><span /><strong>Передан на склад</strong><small>Сегодня, 10:47</small></div>
              </div>
            </Card>
          )}
        </div>
      </section>
    </div>
  )
}

const prototypeLocations = [
  ['Центральный федеральный округ', 'ФО', '18 регионов'],
  ['Воронежская область', 'Регион', '34 города'],
  ['Воронеж', 'Город', 'Активна'],
  ['Москва', 'Город', 'Активна'],
  ['Московская область', 'Регион', '42 города'],
]

function LocationsWorkspace() {
  const [selected, setSelected] = useState(2)
  const [tab, setTab] = useState<'main' | 'delivery' | 'relations'>('main')
  const location = prototypeLocations[selected]

  return (
    <div className="module-layout split-workspace">
      <section className="panel module-list">
        <PanelHead eyebrow="География" title="Локации" subtitle="ФО → регион → город" />
        <label className="small-search workspace-search"><Search size={16} /><input placeholder="Найти локацию" /></label>
        <div className="module-list-scroll">
          {prototypeLocations.map((item, index) => (
            <button className={selected === index ? 'entity-row selected' : 'entity-row'} type="button" onClick={() => setSelected(index)} key={item[0]}>
              <span className="entity-icon"><MapPin size={17} /></span>
              <div><strong>{item[0]}</strong><small>{item[1]} · {item[2]}</small></div>
              <ChevronRight size={16} />
            </button>
          ))}
        </div>
      </section>

      <section className="panel module-detail">
        <div className="module-detail-head"><div><span className="eyebrow">{location[1]}</span><h2>{location[0]}</h2><p>Настройки действуют на эту территорию и могут наследоваться дочерними локациями.</p></div><button className="btn primary" type="button">Сохранить</button></div>
        <div className="tabs static-tabs">
          <button className={tab === 'main' ? 'active' : ''} type="button" onClick={() => setTab('main')}>Основное</button>
          <button className={tab === 'delivery' ? 'active' : ''} type="button" onClick={() => setTab('delivery')}>Доставка</button>
          <button className={tab === 'relations' ? 'active' : ''} type="button" onClick={() => setTab('relations')}>Связи</button>
        </div>
        <div className="module-detail-scroll">
          {tab === 'main' && (
            <Card title="Основные данные" subtitle="Положение в дереве">
              <div className="form-grid readable"><Field label="Название" value={location[0]} required /><Field label="Код" value="voronezh" required /><Field label="Родитель" value="Воронежская область" wide /></div>
            </Card>
          )}
          {tab === 'delivery' && (
            <div className="summary-grid">
              <Card title="Тариф по умолчанию" subtitle="Используется, если способ доставки не переопределил значение">
                <div className="form-grid readable"><Field label="Стоимость" value="900" suffix="₽" /><Field label="Бесплатно от" value="15 000" suffix="₽" /><Field label="Срок от" value="1" /><Field label="Срок до" value="2" /></div>
              </Card>
              <Card title="Обработка" subtitle="Дополнительные параметры">
                <dl className="detail-list"><div><dt>Подъём</dt><dd>Разрешён</dd></div><div><dt>Сборка</dt><dd>Доступна</dd></div><div><dt>Доп. услуги</dt><dd>3</dd></div></dl>
              </Card>
            </div>
          )}
          {tab === 'relations' && (
            <div className="relation-grid">
              <article className="relation-card"><span><Truck size={18} /></span><div><small>Перевозчики</small><strong>3</strong><p>Доступны для этой локации</p></div><ChevronRight size={16} /></article>
              <article className="relation-card"><span><Warehouse size={18} /></span><div><small>Склады</small><strong>2</strong><p>Могут доставлять сюда</p></div><ChevronRight size={16} /></article>
            </div>
          )}
        </div>
      </section>
    </div>
  )
}

const prototypeWarehouses = [
  ['Воронеж', 'Bo00001', '12 480 ед.', '3 способа'],
  ['Москва', 'Mo00007', '24 113 ед.', '5 способов'],
  ['Белгород', 'Bl00003', '4 827 ед.', '2 способа'],
]

function WarehousesWorkspace() {
  const [selected, setSelected] = useState(0)
  const [tab, setTab] = useState<'main' | 'stock' | 'delivery'>('main')
  const warehouse = prototypeWarehouses[selected]

  return (
    <div className="module-layout split-workspace">
      <section className="panel module-list">
        <PanelHead eyebrow="Остатки" title="Склады" subtitle="Физические источники товара" />
        <div className="module-list-scroll">
          {prototypeWarehouses.map((item, index) => (
            <button className={selected === index ? 'entity-row selected' : 'entity-row'} type="button" onClick={() => setSelected(index)} key={item[1]}>
              <span className="entity-icon"><Warehouse size={17} /></span>
              <div><strong>{item[0]}</strong><small>{item[1]} · {item[2]}</small></div>
              <ChevronRight size={16} />
            </button>
          ))}
        </div>
      </section>

      <section className="panel module-detail">
        <div className="module-detail-head"><div><span className="eyebrow">Склад</span><h2>{warehouse[0]}</h2><p>{warehouse[1]} · {warehouse[2]} · {warehouse[3]}</p></div><button className="btn primary" type="button">Сохранить</button></div>
        <div className="tabs static-tabs">
          <button className={tab === 'main' ? 'active' : ''} type="button" onClick={() => setTab('main')}>Основное</button>
          <button className={tab === 'stock' ? 'active' : ''} type="button" onClick={() => setTab('stock')}>Остатки</button>
          <button className={tab === 'delivery' ? 'active' : ''} type="button" onClick={() => setTab('delivery')}>Способы доставки</button>
        </div>
        <div className="module-detail-scroll">
          {tab === 'main' && (
            <div className="summary-grid">
              <Card title="Основное" subtitle="Идентификаторы склада"><div className="form-grid readable"><Field label="Название" value={warehouse[0]} required /><Field label="Внешний ID" value={warehouse[1]} required /></div></Card>
              <Card title="Сводка" subtitle="То, что оператору важно видеть сразу"><dl className="detail-list"><div><dt>Остатки</dt><dd>{warehouse[2]}</dd></div><div><dt>Способы доставки</dt><dd>{warehouse[3]}</dd></div><div><dt>Статус</dt><dd>Активен</dd></div></dl></Card>
            </div>
          )}
          {tab === 'stock' && (
            <Card title="Остатки по складу" subtitle="Быстрый контроль без перехода в другой раздел">
              <Table headers={['Товар', 'SKU', 'Остаток', 'Резерв']}>
                <tr><td>Диван прямой Лига-060</td><td>SV-1042</td><td>7</td><td>1</td></tr>
                <tr><td>Кресло Лига</td><td>SV-2040</td><td>12</td><td>2</td></tr>
                <tr><td>Пуф Лига</td><td>SV-3050</td><td>8</td><td>0</td></tr>
              </Table>
            </Card>
          )}
          {tab === 'delivery' && (
            <Card title="Способы доставки" subtitle="Отдельные схемы доставки этого склада">
              <div className="value-grid">
                <button className="value-card" type="button"><span>1</span><strong>Курьерская доставка</strong><small>14 зон</small><ChevronRight size={14} /></button>
                <button className="value-card" type="button"><span>2</span><strong>Доставка ТК</strong><small>32 зоны</small><ChevronRight size={14} /></button>
                <button className="value-card" type="button"><span>3</span><strong>Резервный маршрут</strong><small>5 зон</small><ChevronRight size={14} /></button>
              </div>
            </Card>
          )}
        </div>
      </section>
    </div>
  )
}

const prototypeStores = [
  ['Светофор — Московский проспект', 'Воронеж', '10:00–20:00'],
  ['Светофор — Левый берег', 'Воронеж', '10:00–19:00'],
  ['Светофор — Москва', 'Москва', '10:00–21:00'],
]

function StoresWorkspace() {
  const [selected, setSelected] = useState(0)
  const [tab, setTab] = useState<'main' | 'contacts' | 'schedule'>('main')
  const store = prototypeStores[selected]

  return (
    <div className="module-layout split-workspace">
      <section className="panel module-list">
        <PanelHead eyebrow="Точки продаж" title="Магазины" subtitle="Физические магазины" />
        <div className="module-list-scroll">
          {prototypeStores.map((item, index) => (
            <button className={selected === index ? 'entity-row selected' : 'entity-row'} type="button" onClick={() => setSelected(index)} key={item[0]}>
              <span className="entity-icon"><Store size={17} /></span>
              <div><strong>{item[0]}</strong><small>{item[1]} · {item[2]}</small></div>
              <ChevronRight size={16} />
            </button>
          ))}
        </div>
      </section>

      <section className="panel module-detail">
        <div className="module-detail-head"><div><span className="eyebrow">Магазин</span><h2>{store[0]}</h2><p>{store[1]} · сегодня {store[2]}</p></div><button className="btn primary" type="button">Сохранить</button></div>
        <div className="tabs static-tabs">
          <button className={tab === 'main' ? 'active' : ''} type="button" onClick={() => setTab('main')}>Основное</button>
          <button className={tab === 'contacts' ? 'active' : ''} type="button" onClick={() => setTab('contacts')}>Контакты</button>
          <button className={tab === 'schedule' ? 'active' : ''} type="button" onClick={() => setTab('schedule')}>Режим работы</button>
        </div>
        <div className="module-detail-scroll">
          {tab === 'main' && (
            <Card title="Адрес" subtitle="Отображается покупателю"><div className="form-grid readable"><Field label="Город" value={store[1]} required /><Field label="Улица" value="Московский проспект, 90/1" required /><Field label="Координаты" value="51.7062, 39.1667" wide /></div></Card>
          )}
          {tab === 'contacts' && (
            <Card title="Контакты" subtitle="Связь с магазином"><div className="form-grid readable"><Field label="Телефон" value="+7 (473) 200-00-00" /><Field label="Email" value="store@example.ru" /><Field label="Комментарий" value="Основной городской магазин" wide /></div></Card>
          )}
          {tab === 'schedule' && (
            <Card title="Режим работы" subtitle="Расписание по дням недели">
              <Table headers={['День', 'Открытие', 'Закрытие', 'Статус']}>
                <tr><td>Пн–Пт</td><td>10:00</td><td>20:00</td><td><Status value="Активен" compact /></td></tr>
                <tr><td>Суббота</td><td>10:00</td><td>19:00</td><td><Status value="Активен" compact /></td></tr>
                <tr><td>Воскресенье</td><td>10:00</td><td>18:00</td><td><Status value="Активен" compact /></td></tr>
              </Table>
            </Card>
          )}
        </div>
      </section>
    </div>
  )
}
