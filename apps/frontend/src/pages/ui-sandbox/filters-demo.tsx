import { useRef, useState } from 'react';
import type { RefObject } from 'react';
import { ChevronDown, X } from 'lucide-react';
import {
  AccordionSection,
  Button,
  Checkbox,
  IconButton,
  Popover,
  Radio,
  RadioGroup,
  RangeInputs,
  RangeSlider,
  Sheet,
  Toggle,
} from '../../components/ui/primitives';
import type { RangeValue } from '../../components/ui/primitives';

/** Образцы примитивов фильтров каталога (market-docs/45). Данные выдуманные */

const PRICE_MIN = 890;
const PRICE_MAX = 180350;
const MAKERS = [
  'Мебельный Рай',
  'АСМ',
  'Будунов',
  'Кромма',
  'Олмеко',
  'СТОПМебель',
  'ТЭКС',
  'Элегия',
];
const SORTS = [
  { value: 'popular', label: 'По популярности' },
  { value: 'cheap', label: 'Сначала дешевле' },
  { value: 'expensive', label: 'Сначала дороже' },
  { value: 'rating', label: 'По рейтингу' },
];
const DIMENSIONS = [
  { label: 'Ширина', min: 224, max: 23500 },
  { label: 'Глубина', min: 450, max: 1070 },
  { label: 'Высота', min: 107, max: 1680 },
];

function Divider() {
  return <hr className="my-6 border-surface-border" />;
}

function Dimensions() {
  const [values, setValues] = useState<RangeValue[]>(DIMENSIONS.map(() => [null, null]));
  return (
    <div className="flex flex-col gap-3">
      {DIMENSIONS.map((d, i) => (
        <div key={d.label} className="flex flex-col gap-2">
          <span className="text-14 text-ink-secondary">{d.label}</span>
          <RangeInputs
            label={d.label}
            min={d.min}
            max={d.max}
            value={values[i]}
            onChange={(v) => setValues((all) => all.map((old, j) => (j === i ? v : old)))}
          />
        </div>
      ))}
    </div>
  );
}

function FiltersBody() {
  const [price, setPrice] = useState<[number, number]>([17000, 127000]);
  const [makers, setMakers] = useState<string[]>(['Мебельный Рай']);
  const [promo, setPromo] = useState(false);

  return (
    <>
      <AccordionSection title="Цена, ₽">
        <div className="flex flex-col gap-3.5">
          <RangeInputs
            label="Цена"
            min={PRICE_MIN}
            max={PRICE_MAX}
            value={price}
            onChange={(v) => setPrice([v[0] ?? PRICE_MIN, v[1] ?? PRICE_MAX])}
          />
          <RangeSlider
            label="Цена"
            min={PRICE_MIN}
            max={PRICE_MAX}
            step={10}
            value={price}
            onChange={setPrice}
          />
        </div>
      </AccordionSection>
      <Divider />
      <AccordionSection title="Габариты, мм">
        <Dimensions />
      </AccordionSection>
      <Divider />
      <AccordionSection title="Производители">
        <div className="flex flex-col gap-3">
          {MAKERS.map((m) => (
            <Checkbox
              key={m}
              label={m}
              checked={makers.includes(m)}
              disabled={m === 'ТЭКС'}
              onChange={(on) => setMakers((all) => (on ? [...all, m] : all.filter((x) => x !== m)))}
            />
          ))}
        </div>
      </AccordionSection>
      <Divider />
      <AccordionSection title="Акция" defaultOpen={false}>
        <Toggle label="По акции" checked={promo} onChange={setPromo} />
      </AccordionSection>
    </>
  );
}

function FilterFooter() {
  return (
    <div className="flex flex-col gap-2">
      <Button size="lg" shape="rounded" fullWidth className="shadow-card">
        Показать 226 товаров
      </Button>
      <Button size="lg" shape="rounded" variant="outline" fullWidth>
        Сбросить все фильтры
      </Button>
    </div>
  );
}

function Trigger({
  label,
  open,
  controls,
  onClick,
  anchorRef,
}: {
  label: string;
  open: boolean;
  controls: string;
  onClick: () => void;
  anchorRef: RefObject<HTMLButtonElement | null>;
}) {
  return (
    // Button не пробрасывает ref, а Popover нужен фокусируемый триггер — кнопка в демо своя
    <button
      ref={anchorRef}
      type="button"
      aria-expanded={open}
      aria-controls={controls}
      onClick={onClick}
      className="inline-flex h-11 items-center gap-2 rounded-btn bg-surface-grey px-6 text-14 font-medium text-ink"
    >
      {label}
      <ChevronDown aria-hidden="true" className="size-4" />
    </button>
  );
}

export default function FiltersDemo() {
  const [popover, setPopover] = useState<'size' | 'sort' | null>(null);
  const [sheet, setSheet] = useState<'left' | 'bottom' | null>(null);
  const [sort, setSort] = useState('popular');
  const [checks, setChecks] = useState([true, false]);
  const [toggle, setToggle] = useState(true);
  const sizeRef = useRef<HTMLButtonElement>(null);
  const sortRef = useRef<HTMLButtonElement>(null);

  const sortList = (
    <RadioGroup label="Сортировка" value={sort} onChange={setSort}>
      {SORTS.map((s) => (
        <Radio key={s.value} value={s.value} label={s.label} />
      ))}
    </RadioGroup>
  );

  return (
    <section className="border-t border-surface-border pt-8">
      <h2 className="text-24 font-semibold text-ink">Фильтры каталога</h2>
      <p className="mt-1 max-w-[70ch] text-14 text-ink-secondary">
        Примитивы задачи 01: Popover, Sheet, Checkbox, Radio, Toggle, RangeSlider, RangeInputs,
        AccordionSection. Замеры из Figma 730:53152 и допущения — market-docs/45.
      </p>

      <div className="mt-6 flex flex-wrap items-start gap-x-10 gap-y-8">
        <div className="flex w-[280px] flex-col gap-3">
          <Checkbox
            label="Выбран"
            checked={checks[0]}
            onChange={(v) => setChecks([v, checks[1]])}
          />
          <Checkbox
            label="Не выбран"
            checked={checks[1]}
            onChange={(v) => setChecks([checks[0], v])}
          />
          <Checkbox label="Нет товаров" checked={false} disabled onChange={() => {}} />
          <Checkbox label="Выбран, выключен" checked disabled onChange={() => {}} />
          <Toggle label="По акции" checked={toggle} onChange={setToggle} />
          <Toggle label="Выключен" checked={false} disabled onChange={() => {}} />
        </div>
        <div className="w-[280px] rounded-card p-2 shadow-card">{sortList}</div>

        <div className="flex flex-wrap gap-2">
          <Trigger
            label="Габариты"
            open={popover === 'size'}
            controls="demo-size"
            anchorRef={sizeRef}
            onClick={() => setPopover((p) => (p === 'size' ? null : 'size'))}
          />
          <Trigger
            label="Сортировка"
            open={popover === 'sort'}
            controls="demo-sort"
            anchorRef={sortRef}
            onClick={() => setPopover((p) => (p === 'sort' ? null : 'sort'))}
          />
          <Button variant="secondary" size="sm" shape="rounded" onClick={() => setSheet('left')}>
            Все фильтры (слева)
          </Button>
          <Button variant="secondary" size="sm" shape="rounded" onClick={() => setSheet('bottom')}>
            Сортировка (шторка)
          </Button>
        </div>
      </div>

      <Popover
        id="demo-size"
        label="Габариты"
        open={popover === 'size'}
        onClose={() => setPopover(null)}
        anchorRef={sizeRef}
        className="w-[312px]"
        footer={
          <Button size="lg" shape="rounded" fullWidth className="shadow-card">
            Показать 226 товаров
          </Button>
        }
      >
        <Dimensions />
      </Popover>
      <Popover
        id="demo-sort"
        label="Сортировка"
        open={popover === 'sort'}
        onClose={() => setPopover(null)}
        anchorRef={sortRef}
        className="w-[280px]"
      >
        {sortList}
      </Popover>

      <Sheet
        open={sheet === 'left'}
        onClose={() => setSheet(null)}
        side="left"
        title="Все фильтры"
        headerAction={
          <IconButton label="Закрыть" variant="grey" onClick={() => setSheet(null)}>
            <X className="size-6" />
          </IconButton>
        }
        footer={<FilterFooter />}
      >
        <FiltersBody />
      </Sheet>
      <Sheet
        open={sheet === 'bottom'}
        onClose={() => setSheet(null)}
        side="bottom"
        title="Сортировка"
        headerAction={
          <IconButton label="Закрыть" variant="grey" onClick={() => setSheet(null)}>
            <X className="size-6" />
          </IconButton>
        }
      >
        {sortList}
      </Sheet>
    </section>
  );
}
