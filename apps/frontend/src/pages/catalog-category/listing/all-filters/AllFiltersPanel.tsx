import { X } from 'lucide-react';
import { Button, IconButton, Sheet } from '../../../../components/ui/primitives';
import type { FiltersMeta } from '../../../../lib/api';
import type { CatalogQuery } from '../../../../lib/catalog-query';
import ShowResultsButton from '../fields/ShowResultsButton';
import { useFilterDraft } from '../useFilterDraft';
import type { CheckboxOption } from './CheckboxList';
import FilterSections from './FilterSections';

interface AllFiltersPanelProps {
  /** Панель остаётся на странице и после закрытия — чтобы уехать с анимацией */
  open: boolean;
  /** Экран уже 980: шторка снизу. Приходит от страницы — у новой панели хук дал бы «десктоп» в первом кадре */
  compact: boolean;
  query: CatalogQuery;
  onQueryChange: (next: CatalogQuery) => void;
  /** Закрыть: и после «Показать», и без применения */
  onClose: () => void;
  meta: FiltersMeta | null;
  total: number;
  countProducts: (query: CatalogQuery) => Promise<number>;
  subcategories: CheckboxOption[];
}

/**
 * «Все фильтры» (market-docs/44, 48): от 980 — панель слева 400, ниже — шторка снизу
 * (планшет — две колонки). Закрытие без «Показать» — выбор отменён. Каждое открытие — новый
 * экземпляр (key у страницы): выбор начинается с применённых фильтров.
 */
export default function AllFiltersPanel({
  open,
  compact,
  query,
  onQueryChange,
  onClose,
  meta,
  total,
  countProducts,
  subcategories,
}: AllFiltersPanelProps) {
  const draft = useFilterDraft({
    applied: query,
    appliedTotal: total,
    onApply: onQueryChange,
    countProducts,
    initialSurface: open ? 'all' : undefined,
  });

  // Закрыли — черновик больше не считает «Показать N»
  const close = () => {
    draft.discard();
    onClose();
  };

  const apply = () => {
    draft.apply();
    onClose();
  };

  return (
    <Sheet
      open={open}
      onClose={close}
      side={compact ? 'bottom' : 'left'}
      headerDivider={compact}
      title={compact ? 'Фильтры' : 'Все фильтры'}
      // Ниже 980 по макету — около 80 % высоты экрана, даже если разделов мало
      className={compact ? 'h-[80dvh]' : undefined}
      headerAction={
        compact ? (
          <button
            type="button"
            onClick={draft.reset}
            className="text-14 text-ink-secondary transition-colors hover:text-ink-secondary/60"
          >
            Сбросить
          </button>
        ) : (
          <IconButton variant="grey" size="md" label="Закрыть" onClick={close}>
            <X className="size-6" />
          </IconButton>
        )
      }
      footer={
        <div className="flex flex-col gap-2">
          <ShowResultsButton count={draft.count} onClick={apply} />
          {!compact && (
            <Button size="lg" shape="rounded" variant="muted" fullWidth onClick={draft.reset}>
              Сбросить все фильтры
            </Button>
          )}
        </div>
      }
    >
      <FilterSections
        draft={draft.draft}
        set={draft.set}
        meta={meta}
        subcategories={subcategories}
      />
    </Sheet>
  );
}
