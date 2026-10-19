import { useId, useRef, useState } from 'react';
import { X } from 'lucide-react';
import { Popover, Radio, RadioGroup, Sheet } from '../ui/primitives';
import FilterPill from './FilterPill';

interface SortControlProps<T extends string> {
  /** Варианты в порядке показа — CATALOG_SORT_OPTIONS (каталог и поиск) */
  options: readonly { value: T; label: string }[];
  value: T;
  onChange: (sort: T) => void;
  /** Ниже 980 — шторкой снизу (market-docs/44) */
  asSheet: boolean;
}

export default function SortControl<T extends string>({
  options: sortOptions,
  value,
  onChange,
  asSheet,
}: SortControlProps<T>) {
  const [open, setOpen] = useState(false);
  const anchorRef = useRef<HTMLButtonElement>(null);
  const panelId = useId();
  const current = sortOptions.find((o) => o.value === value) ?? sortOptions[0];

  const choose = (next: string) => {
    setOpen(false);
    if (next !== value) onChange(next as T);
  };

  const options = (
    <RadioGroup label="Сортировка" value={value} onChange={choose}>
      {sortOptions.map((o) => (
        <Radio key={o.value} value={o.value} label={o.label} />
      ))}
    </RadioGroup>
  );

  return (
    <>
      <FilterPill
        ref={anchorRef}
        open={open}
        textBelowMd
        aria-controls={open ? panelId : undefined}
        onClick={() => setOpen((v) => !v)}
      >
        {current.label}
      </FilterPill>
      {asSheet ? (
        <Sheet
          open={open}
          onClose={() => setOpen(false)}
          side="bottom"
          title="Сортировка"
          headerAction={
            <button
              type="button"
              aria-label="Закрыть"
              onClick={() => setOpen(false)}
              className="inline-flex size-6 items-center justify-center text-ink transition-colors hover:text-ink-secondary"
            >
              <X aria-hidden className="size-6" />
            </button>
          }
        >
          {options}
        </Sheet>
      ) : (
        <Popover
          open={open}
          onClose={() => setOpen(false)}
          anchorRef={anchorRef}
          label="Сортировка"
          id={panelId}
          className="w-max min-w-60"
        >
          {options}
        </Popover>
      )}
    </>
  );
}
