import { useId, useRef, useState } from 'react';
import { Popover, Radio, RadioGroup, Sheet } from '../../../../components/ui/primitives';
import { CATALOG_SORT_OPTIONS, type CatalogSort } from '../../../../lib/catalog-query';
import FilterPill from './FilterPill';

interface SortControlProps {
  value: CatalogSort;
  onChange: (sort: CatalogSort) => void;
  /** Ниже 980 — шторкой снизу (market-docs/44) */
  asSheet: boolean;
}

export default function SortControl({ value, onChange, asSheet }: SortControlProps) {
  const [open, setOpen] = useState(false);
  const anchorRef = useRef<HTMLButtonElement>(null);
  const panelId = useId();
  const current = CATALOG_SORT_OPTIONS.find((o) => o.value === value) ?? CATALOG_SORT_OPTIONS[0];

  const choose = (next: string) => {
    setOpen(false);
    if (next !== value) onChange(next as CatalogSort);
  };

  const options = (
    <RadioGroup label="Сортировка" value={value} onChange={choose}>
      {CATALOG_SORT_OPTIONS.map((o) => (
        <Radio key={o.value} value={o.value} label={o.label} />
      ))}
    </RadioGroup>
  );

  return (
    <>
      <FilterPill
        ref={anchorRef}
        open={open}
        aria-controls={open ? panelId : undefined}
        onClick={() => setOpen((v) => !v)}
      >
        {current.label}
      </FilterPill>
      {asSheet ? (
        <Sheet open={open} onClose={() => setOpen(false)} side="bottom" title="Сортировка">
          {options}
        </Sheet>
      ) : (
        <Popover
          open={open}
          onClose={() => setOpen(false)}
          anchorRef={anchorRef}
          label="Сортировка"
          id={panelId}
          className="w-60"
        >
          {options}
        </Popover>
      )}
    </>
  );
}
