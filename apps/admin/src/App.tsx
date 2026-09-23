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
  Sun,
  Tag,
  Truck,
  Users,
  Warehouse,
} from 'lucide-react'

type Theme = 'light' | 'dark'
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

const nav = [
  ['Работа', [['Главная', LayoutDashboard], ['Каталог', Package], ['Заказы', Tag]]],
  ['Каталог', [['Товары', Package], ['Категории', FolderTree], ['Комнаты', MapPin], ['Характеристики', SlidersHorizontal], ['Остатки', Boxes]]],
  ['Доставка', [['Склады', Warehouse], ['Перевозчики', Truck]]],
  ['Система', [['Пользователи', Users], ['Настройки', Settings]]],
] as const

function initialTheme(): Theme {
  const saved = localStorage.getItem('sv-admin-theme')

  if (saved === 'light' || saved === 'dark') {
    return saved
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export function App() {
  const [theme, setTheme] = useState<Theme>(initialTheme)
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
        <Sidebar />

        <main className="workspace">
          <PageHeader />

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

function Sidebar() {
  return (
    <aside className="sidebar">
      <nav>
        {nav.map(([title, items]) => (
          <div className="nav-group" key={title}>
            <div className="nav-title">{title}</div>

            {items.map(([label, Icon]) => (
              <a
                className={label === 'Каталог' || label === 'Товары' ? 'nav-link active' : 'nav-link'}
                href="#"
                key={label}
              >
                <Icon size={18} />
                <span>{label}</span>
              </a>
            ))}
          </div>
        ))}
      </nav>

      <div className="sidebar-foot">
        <span />
        <div><strong>develop</strong><small>Источник актуальной схемы</small></div>
      </div>
    </aside>
  )
}

function PageHeader() {
  return (
    <header className="page-head">
      <div>
        <span className="eyebrow">Рабочее место оператора</span>
        <h1>Каталог товаров</h1>
        <p>Разделы, список товаров и редактирование находятся в одном рабочем пространстве. Прокручивается только активная область.</p>
      </div>

      <div className="head-actions">
        <button className="btn ghost" type="button"><ListFilter size={16} /> Фильтры</button>
        <button className="btn primary" type="button"><Plus size={16} /> Новый товар</button>
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
        <input defaultValue={value} placeholder={placeholder} />
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
