import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
  ChevronRight,
  Heart,
  ArrowLeftRight,
  ShoppingCart,
  Minus,
  Plus,
  User,
  Menu,
  Columns2,
} from 'lucide-react';
import { Button, IconButton, Badge, Input, SearchInput } from '../../components/ui/primitives';
import { QuantityStepper, Price, ColorSwatches } from '../../components/ui/composites';
import { Header, UserActions } from '../../components/layout/Header';
import { CATALOG_SECTIONS, ROOMS_SECTIONS } from './header-demo-data';
import { cn } from '../../lib/cn';

/**
 * Песочница UI-примитивов. Маршрут /__ui, регистрируется только в dev
 * (router/config.tsx), в прод-сборку не попадает.
 *
 * Зачем: примитивы не подключены ни к одной странице, и посмотреть их вживую
 * больше негде. Рядом с каждым образцом показан фактический размер в пикселях —
 * его и сверяем с ui-kit в Figma (node-id=3-2).
 *
 * Порядок сверки и таблица ожидаемых значений: market-docs/09-ui-primitives-stage-1.md.
 */

/**
 * Меряет первый дочерний элемент и подписывает его фактический размер.
 *
 * `full` нужен тем образцам, у которых ширина задаётся снаружи (поле поиска):
 * по умолчанию обёртка сжимается по содержимому, и замер показывал бы не ту
 * ширину, что в макете.
 */
function Spec({
  label,
  note,
  full = false,
  children,
}: {
  label: string;
  note?: string;
  full?: boolean;
  children: ReactNode;
}) {
  const ref = useRef<HTMLDivElement>(null);
  const [size, setSize] = useState<string>('—');

  useEffect(() => {
    const host = ref.current;
    const el = host?.firstElementChild as HTMLElement | null;
    if (!el) return;

    const measure = () => {
      const rect = el.getBoundingClientRect();
      const round = (n: number) => Math.round(n * 10) / 10;
      setSize(`${round(rect.width)} × ${round(rect.height)}`);
    };

    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <div className={full ? 'flex w-full flex-col gap-2' : 'flex flex-col items-start gap-2'}>
      <div
        ref={ref}
        className={
          full ? 'flex min-h-[48px] w-full items-center' : 'flex min-h-[48px] items-center'
        }
      >
        {children}
      </div>
      <div className="font-mono text-10 leading-tight text-ink-secondary">
        <div className="text-ink">{label}</div>
        <div>
          {size}
          {note ? ` · ${note}` : ''}
        </div>
      </div>
    </div>
  );
}

/**
 * `shade` подкладывает серый фон под образцы. Нужен там, где элемент белый:
 * на карточке товара кнопки лежат на фотографии, а на белой странице песочницы
 * их просто не видно.
 */
function Section({
  title,
  hint,
  shade = false,
  children,
}: {
  title: string;
  hint?: string;
  shade?: boolean;
  children: ReactNode;
}) {
  return (
    <section className="border-t border-surface-border pt-8">
      <h2 className="text-24 font-semibold text-ink">{title}</h2>
      {hint && <p className="mt-1 max-w-[70ch] text-14 text-ink-secondary">{hint}</p>}
      <div
        className={cn(
          'mt-6 flex flex-wrap items-start gap-x-10 gap-y-8',
          shade && 'rounded-btn bg-surface-grey p-6',
        )}
      >
        {children}
      </div>
    </section>
  );
}

export default function UiSandbox() {
  const [outline, setOutline] = useState(false);
  const [qty, setQty] = useState(1);

  return (
    <div className="min-h-screen bg-surface">
      {/* Шапка первым потомком страницы, а не внутри секции: она sticky,
          а sticky держится только в пределах родителя. В RootLayout она тоже
          прямой потомок высокой обёртки, так что превью соответствует бою. */}
      <Header
        wishlistCount={1}
        cartCount={1}
        compareCount={0}
        catalogSections={CATALOG_SECTIONS}
        roomsSections={ROOMS_SECTIONS}
      />

      <section className="border-b border-surface-border">
        <div className="mx-auto max-w-[1440px] px-[100px] pb-4 pt-10">
          <h2 className="text-24 font-semibold text-ink">Шапка по макету</h2>
          <p className="mt-1 max-w-[70ch] text-14 text-ink-secondary">
            Замеры 07.09.2026: полосы 36 / 80 / 35, контейнер 1440 с отступами 100, правая группа
            298 × 43 с промежутком 20, служебные ссылки с промежутком 24. Крупные блоки разложены
            justify-between — в макете расстояния между ними неровные, значит это распределение.
            Нажми «Каталог» или «Комнаты» — меню раскрывается под шапкой. Ниже 550 оно превращается
            в гармошку.
          </p>
        </div>
      </section>

      <div
        className={
          outline
            ? 'mx-auto max-w-[1200px] px-6 py-10 [&_button]:outline [&_button]:outline-1 [&_button]:outline-brand-red [&_input]:outline [&_input]:outline-1 [&_input]:outline-brand-red'
            : 'mx-auto max-w-[1200px] px-6 py-10'
        }
      >
        <header className="pb-8">
          <h1 className="font-display text-52 font-bold text-ink">UI-примитивы</h1>
          <p className="mt-2 max-w-[70ch] text-16 text-ink-secondary">
            Под каждым образцом — фактический размер, снятый из DOM. Сверять с ui-kit в Figma:
            сначала высота, потом паддинг. Высота получается некруглой из-за lineHeight: normal, и
            это первое, что стоит проверить.
          </p>

          <label className="mt-4 inline-flex cursor-pointer items-center gap-2 text-14 text-ink">
            <input
              type="checkbox"
              checked={outline}
              onChange={(event) => setOutline(event.target.checked)}
            />
            Обводка границ
          </label>
        </header>

        <div className="flex flex-col gap-10 pb-20">
          <Section
            title="Шапка · проверка размера подписи"
            hint="Группа в макете 298 × 43. Размер подписи напрямую не замерен, поэтому сверяем шириной: какой вариант даёт 298, тот и верный."
          >
            <Spec label="подпись 14" note="ожидание 298 × 43">
              <UserActions wishlistCount={1} cartCount={1} />
            </Spec>
            <Spec label="подпись 12" note="для сравнения">
              <UserActions wishlistCount={1} cartCount={1} className="[&_a]:!text-12" />
            </Spec>
          </Section>

          <Section
            title="Как писать брейкпоинты"
            hint="Брейкпоинты объявлены как min-width (vsm 550, sm 769, md 980), а макет описан сверху вниз — поэтому пишем через max-*. Ширина под каждым пробником настоящая: сузь окно и смотри, какое написание ведёт себя как надо."
          >
            <Spec label="✅ max-* сверху вниз" note="400 → 300 → 200">
              <div className="h-6 w-[400px] max-w-full bg-brand-yellow max-md:w-[300px] max-vsm:w-[200px]" />
            </Spec>
            <Spec label="❌ без max-, снизу вверх" note="это min-width, направление обратное">
              <div className="h-6 w-[100px] max-w-full bg-surface-grey vsm:w-[200px] md:w-[400px]" />
            </Spec>
            <Spec label="✅ диапазон: vsm + max-md" note="только 550…979">
              <div className="h-6 w-[100px] max-w-full bg-brand-green max-md:vsm:w-[300px]" />
            </Spec>
          </Section>

          <Section
            title="По макету · «Каталог» и «Комнаты»"
            hint="Замеры владельца 07.09.2026: паддинг 10/24, радиус 100, кегль 14, иконка 24 — высота выходит 44. Ниже 979 (max-md) остаётся круг 40 с иконкой 20, ниже 549 (max-vsm) — голая иконка 24 без подложки. Сузь окно, чтобы проверить."
          >
            <Spec label="десктоп" note="ожидание 44 по высоте">
              <div className="flex gap-3">
                <Button size="sm" leftIcon={<Menu className="size-6" />}>
                  Каталог
                </Button>
                <Button size="sm" variant="secondary" leftIcon={<Columns2 className="size-6" />}>
                  Комнаты
                </Button>
              </div>
            </Spec>

            <Spec label="адаптивный" note="перестраивается на max-md и max-vsm">
              <div className="flex gap-3">
                <Button
                  size="sm"
                  className="max-md:size-10 max-md:rounded-full max-md:p-0 max-vsm:size-auto max-vsm:bg-transparent max-vsm:hover:bg-transparent"
                  leftIcon={<Menu className="size-6 max-md:size-5 max-vsm:size-6" />}
                >
                  <span className="max-md:hidden">Каталог</span>
                </Button>
                <Button
                  size="sm"
                  variant="secondary"
                  className="max-md:size-10 max-md:rounded-full max-md:p-0 max-vsm:size-auto max-vsm:bg-transparent max-vsm:hover:bg-transparent"
                  leftIcon={<Columns2 className="size-6 max-md:size-5 max-vsm:size-6" />}
                >
                  <span className="max-md:hidden">Комнаты</span>
                </Button>
              </div>
            </Spec>
          </Section>

          <Section
            title="По макету · «Подробнее»"
            hint="Ширина в макете фиксированная: 302 на десктопе, 224 ниже 979. Паддинг по вертикали 16 и 12, радиус 12, шеврон 20."
          >
            <Spec label="десктоп" note="302 × 52">
              <Button
                size="lg"
                shape="rounded"
                className="w-[302px]"
                rightIcon={<ChevronRight className="size-5" />}
              >
                Подробнее
              </Button>
            </Spec>
            <Spec label="мобильный" note="224 × 44">
              <Button
                size="md"
                shape="rounded"
                className="w-[224px]"
                rightIcon={<ChevronRight className="size-5" />}
              >
                Подробнее
              </Button>
            </Spec>
            <Spec label="адаптивный" note="302 → 224 на max-md">
              <Button
                size="lg"
                shape="rounded"
                className="w-[302px] max-md:h-11 max-md:w-[224px]"
                rightIcon={<ChevronRight className="size-5" />}
              >
                Подробнее
              </Button>
            </Spec>
          </Section>

          <Section
            title="Button · размеры"
            hint="Высота задана явно, а не паддингом: 44 / 44 / 52. Иначе рамка и lineHeight normal сдвигают её на 1–2 px, и outline оказывается выше primary."
          >
            <Spec label="sm · pill" note="ожидание 44">
              <Button size="sm">Каталог</Button>
            </Spec>
            <Spec label="md · pill" note="ожидание 44">
              <Button size="md">Каталог</Button>
            </Spec>
            <Spec label="lg · pill" note="ожидание 52">
              <Button size="lg">Каталог</Button>
            </Spec>
            <Spec label="lg · rounded 12">
              <Button size="lg" shape="rounded">
                Каталог
              </Button>
            </Spec>
          </Section>

          <Section
            title="Button · варианты"
            hint="Наведи и пройди Tab — фокус должен давать кольцо, а цвет меняться мгновенно."
          >
            <Spec label="primary">
              <Button variant="primary">Подробнее</Button>
            </Spec>
            <Spec label="secondary">
              <Button variant="secondary">Комнаты</Button>
            </Spec>
            <Spec label="outline">
              <Button variant="outline">Комнаты</Button>
            </Spec>
            <Spec label="ghost">
              <Button variant="ghost">Все категории</Button>
            </Spec>
            <Spec label="disabled">
              <Button disabled>Недоступно</Button>
            </Spec>
            <Spec label="isLoading">
              <Button isLoading>Отправка</Button>
            </Spec>
          </Section>

          <Section
            title="Button · иконки и ссылки"
            hint="Размер иконки в кнопку не зашит и задаётся явно: в макете он не следует за размером текста — бургер 24 при тексте 14, шеврон 20 при тексте 16. Промежуток замерен: 8 у кнопок шапки (sm), 4 у «Подробнее» (md и lg)."
          >
            <Spec label="rightIcon">
              <Button rightIcon={<ChevronRight className="size-5" />}>Подробнее</Button>
            </Spec>
            <Spec label="leftIcon · lg">
              <Button size="lg" leftIcon={<ShoppingCart className="size-5" />}>
                В корзину
              </Button>
            </Spec>
            <Spec label="to= (Link)">
              <Button to="/catalog" variant="outline">
                Внутренняя ссылка
              </Button>
            </Spec>
            <Spec label="fullWidth">
              <div className="w-[320px]">
                <Button fullWidth>Оформить заказ</Button>
              </div>
            </Spec>
          </Section>

          <Section
            title="Button · заготовка заливки"
            hint="Дизайнер 07.09.2026 подтвердил, что движения нет: по умолчанию fill=none, цвет меняется мгновенно. Здесь три направления на случай, если решение поменяется."
          >
            <Spec label="fill=up">
              <Button fill="up">Снизу вверх</Button>
            </Spec>
            <Spec label="fill=left">
              <Button fill="left">Слева направо</Button>
            </Spec>
            <Spec label="fill=center">
              <Button fill="center">Из центра</Button>
            </Spec>
            <Spec label="fill=none (принято)">
              <Button>Мгновенно</Button>
            </Spec>
          </Section>

          <Section
            title="IconButton"
            hint="36 / 40 / 48. Замерены первые два: 36 — кнопка внутри поиска, 40 — свёрнутый «Каталог». 48 не замерен."
          >
            <Spec label="sm · 36" note="замерено">
              <IconButton label="В избранное" size="sm">
                <Heart className="size-5" />
              </IconButton>
            </Spec>
            <Spec label="md · 40" note="замерено">
              <IconButton label="В избранное" elevated>
                <Heart className="size-5" />
              </IconButton>
            </Spec>
            <Spec label="lg · 48" note="не замерен">
              <IconButton label="В избранное" size="lg">
                <Heart className="size-6" />
              </IconButton>
            </Spec>
            <Spec label="yellow">
              <IconButton label="В корзину" variant="yellow" elevated>
                <ShoppingCart className="size-5" />
              </IconButton>
            </Spec>
            <Spec label="grey">
              <IconButton label="Сравнить" variant="grey">
                <ArrowLeftRight className="size-5" />
              </IconButton>
            </Spec>
            <Spec label="danger">
              <IconButton label="Удалить" variant="danger">
                <Minus className="size-5" />
              </IconButton>
            </Spec>
            <Spec label="isActive · white">
              <IconButton label="В избранном" isActive elevated>
                <Heart className="size-5" fill="currentColor" />
              </IconButton>
            </Spec>
            <Spec label="elevated">
              <IconButton label="Вперёд" elevated>
                <ChevronRight className="size-5" />
              </IconButton>
            </Spec>
            <Spec label="disabled">
              <IconButton label="Недоступно" disabled>
                <Plus className="size-5" />
              </IconButton>
            </Spec>
          </Section>

          <Section
            title="По макету · бейджи трёх верхних карточек"
            hint="Замеры 07.09.2026: «Сезонная распродажа» 8/16, «Выгодно» и «Гарантия» 8/12, размер шрифта 12, радиус 100 у всех трёх. Ниже 549 сжимаются до 6/12 — сузь окно. Заглавные буквы задаются в месте использования, а не компонентом: на карточках товара те же бейджи набраны обычными."
          >
            <Spec label="lg · sale" note="8 / 16">
              <Badge variant="sale" size="lg" className="uppercase">
                Сезонная распродажа до −60%
              </Badge>
            </Spec>
            <Spec label="md · new" note="8 / 12">
              <Badge variant="new" className="uppercase">
                Выгодно
              </Badge>
            </Spec>
            <Spec label="md · info" note="8 / 12">
              <Badge variant="info" className="uppercase">
                Гарантия
              </Badge>
            </Spec>
          </Section>

          <Section
            title="По макету · бейджи карточки товара и навигации"
            hint="Карточка товара: 6/12, пилюля, цвета разные. Навигация: 5/10, радиус 6 — единственный бейдж в макете, который не пилюля."
          >
            <Spec label="sm · info" note="6 / 12, карточка">
              <Badge variant="info" size="sm">
                Уточняйте у менеджера
              </Badge>
            </Spec>
            <Spec label="sm · new" note="6 / 12, карточка">
              <Badge variant="new" size="sm">
                Новинка
              </Badge>
            </Spec>
            <Spec label="sm · sale" note="6 / 12, карточка">
              <Badge variant="sale" size="sm">
                Хит продаж
              </Badge>
            </Spec>
            <Spec label="xs · soft · badge" note="5 / 10, радиус 6">
              <Badge variant="soft" size="xs" shape="badge">
                до −60%
              </Badge>
            </Spec>
          </Section>

          <Section
            title="По макету · кнопки на карточке товара"
            shade
            hint="Круг 40, иконка 20 — совпало с размером md, замеренным на свёрнутом «Каталоге». Тень замерена отдельно: X 0, Y 2, размытие 3, чёрный 10 % — это токен shadow-btn, он мельче тени под самой карточкой. Избранное в нажатом состоянии красное с залитым сердцем, корзина жёлтая."
          >
            <Spec label="сравнить" note="40, иконка 20">
              <IconButton label="Сравнить" elevated>
                <ArrowLeftRight className="size-5" />
              </IconButton>
            </Spec>
            <Spec label="избранное" note="в покое">
              <IconButton label="В избранное" elevated>
                <Heart className="size-5" />
              </IconButton>
            </Spec>
            <Spec label="избранное · нажато" note="красный, сердце залито">
              <IconButton label="В избранном" isActive elevated>
                <Heart className="size-5" fill="currentColor" />
              </IconButton>
            </Spec>
            <Spec label="в корзину" note="жёлтый">
              <IconButton label="В корзину" variant="yellow" elevated>
                <ShoppingCart className="size-5" />
              </IconButton>
            </Spec>
            <Spec label="минус / плюс" note="счётчик количества">
              <div className="flex items-center gap-2">
                <IconButton label="Убрать одну" elevated>
                  <Minus className="size-5" />
                </IconButton>
                <span className="inline-flex h-10 min-w-[52px] items-center justify-center rounded-btn bg-surface text-16 text-ink">
                  1
                </span>
                <IconButton label="Добавить одну" elevated>
                  <Plus className="size-5" />
                </IconButton>
              </div>
            </Spec>
          </Section>

          <Section
            title="Этап 2 · счётчик количества"
            hint="Замеры 07.09.2026: круги 40 с иконками 20, минус серый, плюс белый, значение — прямоугольник 55 шириной с радиусом 12, промежуток 8. На всех экранах одинаково. Прямоугольник со значением: 55 × 40, шрифт 14, без рамки. Все размеры замерены."
            shade
          >
            <Spec label="значение 1" note="минус выключен на минимуме">
              <QuantityStepper value={qty} onChange={setQty} max={99} />
            </Spec>
            <Spec label="статичный, значение 4">
              <QuantityStepper value={4} />
            </Spec>
            <Spec label="на максимуме" note="плюс выключен">
              <QuantityStepper value={10} max={10} />
            </Spec>
          </Section>

          <Section
            title="Этап 2 · цена"
            hint="Замеры 07.09.2026: «от» 16, число 20, старая цена 14 целиком вместе со своим «от». Обычная цена тёмная, акционная и зачёркнутая старая — обе красные. Размер 20 в шкале отсутствовал и добавлен в конфиг ради этого места."
          >
            <Spec label="обычная">
              <Price value={46210} from />
            </Spec>
            <Spec label="акция" note="обе красные">
              <Price value={15330} oldValue={25330} from />
            </Spec>
            <Spec label="без «от»">
              <Price value={7990} />
            </Spec>
            <Spec label="акция без «от»">
              <Price value={7990} oldValue={12500} />
            </Spec>
          </Section>

          <Section
            title="Этап 2 · образцы цвета"
            hint="Квадрат 24, радиус 4, промежуток 8. Если цветов больше четырёх — видно три образца, а четвёртым местом счётчик «+N» со шрифтом 8. Ширина счётчика ждёт ответа дизайнера — по ожиданию значение не превысит «+9», но двузначное в 24 тоже влезает."
          >
            <Spec label="три цвета">
              <ColorSwatches
                colors={[
                  { value: '#3E5C76', title: 'Синий' },
                  { value: '#D8C3A5', title: 'Бежевый' },
                  { value: '#8CA8A0', title: 'Мятный' },
                ]}
              />
            </Spec>
            <Spec label="ровно четыре" note="счётчика нет">
              <ColorSwatches
                colors={[
                  { value: '#3E5C76' },
                  { value: '#D8C3A5' },
                  { value: '#8CA8A0' },
                  { value: '#141414' },
                ]}
              />
            </Spec>
            <Spec label="одиннадцать" note="три образца и +8">
              <ColorSwatches
                colors={Array.from({ length: 11 }, (_, i) => ({
                  value: `hsl(${i * 33} 35% 60%)`,
                }))}
              />
            </Spec>
            <Spec label="двадцать" note="проверка ширины счётчика">
              <ColorSwatches
                colors={Array.from({ length: 20 }, (_, i) => ({
                  value: `hsl(${i * 18} 35% 60%)`,
                }))}
              />
            </Spec>
            <Spec label="белый образец" note="рамка, иначе не виден">
              <ColorSwatches colors={[{ value: '#FFFFFF' }, { value: '#F5F3F1' }]} />
            </Spec>
          </Section>

          <Section
            title="Badge · не замеренное"
            hint="Вариант neutral и бейдж с иконкой в макете не встречались — оставлены как есть. Размеры шрифта у xs и sm тоже не замерены: стоят 10 и 12."
          >
            <Spec label="neutral · sm">
              <Badge variant="neutral" size="sm">
                Гарантия
              </Badge>
            </Spec>
            <Spec label="pill + иконка">
              <Badge icon={<Heart className="size-[1em]" />}>Выгодно</Badge>
            </Spec>
          </Section>

          <Section
            title="Input"
            hint="Высота 36 / 44 / 52 задана на обёртке, горизонтальный паддинг 16. Замерен только md (поиск в шапке): 44 при шрифте 14. Рамка замерена: в покое #F5F3F1, в фокусе #E8E5E1 — обе светлые. sm и lg в макете не встречались."
          >
            <div className="w-[280px]">
              <Spec label="sm · grey">
                <Input inputSize="sm" placeholder="Поиск по каталогу" />
              </Spec>
            </div>
            <div className="w-[280px]">
              <Spec label="md · grey">
                <Input placeholder="Поиск по каталогу" />
              </Spec>
            </div>
            <div className="w-[280px]">
              <Spec label="lg · grey">
                <Input inputSize="lg" placeholder="Поиск по каталогу" />
              </Spec>
            </div>
            <div className="w-[280px]">
              <Spec label="white + рамка">
                <Input tone="white" placeholder="Имя" />
              </Spec>
            </div>
            <div className="w-[280px]">
              <Spec label="leftIcon">
                <Input tone="white" leftIcon={<User className="size-5" />} placeholder="Имя" />
              </Spec>
            </div>
            <div className="w-[280px]">
              <Spec label="invalid">
                <Input tone="white" invalid defaultValue="почта@" />
              </Spec>
            </div>
            <div className="w-[280px]">
              <Spec label="disabled">
                <Input disabled placeholder="Недоступно" />
              </Spec>
            </div>
            <div className="w-[280px]">
              <Spec label="rounded 12">
                <Input shape="rounded" tone="white" placeholder="Комментарий" />
              </Spec>
            </div>
          </Section>

          <Section
            title="По макету · поиск в шапке"
            hint="Замеры 07.09.2026: высота 44, шрифт 14, строка 17, паддинг 13.5 по вертикали, круглая кнопка 36 с иконкой 20, отбита по 4. Ширина адаптивная, максимум 475. Рамка в покое #F5F3F1, в фокусе #E8E5E1. Ниже 549 поле в шапке исчезает целиком, остаётся голая иконка 24, раскрывающаяся по клику — это поведение шапки, в примитив не зашито."
          >
            <div className="w-[475px] max-w-full shrink-0">
              <Spec label="покой" note="ожидание 475 × 44" full>
                <SearchInput placeholder="Диван-кровать, шкаф-купе, матрас..." />
              </Spec>
            </div>
            <div className="w-[475px] max-w-full shrink-0">
              <Spec label="заполненное" full>
                <SearchInput defaultValue="Диван-кровать" />
              </Spec>
            </div>
            <div className="w-[475px] max-w-full shrink-0">
              <Spec label="сжатое до 320" note="ниже максимума" full>
                <div className="w-[320px]">
                  <SearchInput placeholder="Диван-кровать, шкаф-купе..." />
                </div>
              </Spec>
            </div>
            <div className="w-[475px] max-w-full shrink-0">
              <Spec label="disabled">
                <SearchInput placeholder="Поиск недоступен" disabled />
              </Spec>
            </div>
          </Section>
        </div>
      </div>
    </div>
  );
}
