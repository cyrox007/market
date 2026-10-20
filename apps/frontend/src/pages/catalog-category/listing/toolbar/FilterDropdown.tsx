import { useId, useRef, type ReactNode } from 'react';
import { Popover } from '../../../../components/ui/primitives';
import type { FilterDraft } from '../useFilterDraft';
import FilterPill from '../../../../components/catalog/FilterPill';
import ShowResultsButton from '../fields/ShowResultsButton';

interface FilterDropdownProps {
  label: string;
  /** Имя окна в черновике: открыто только одно */
  surface: string;
  draft: FilterDraft;
  className?: string;
  children: ReactNode;
}

/** Кнопка строки + окно с выбором и «Показать N товаров». Закрыли без кнопки — выбор отменён */
export default function FilterDropdown({
  label,
  surface,
  draft,
  className,
  children,
}: FilterDropdownProps) {
  const anchorRef = useRef<HTMLButtonElement>(null);
  const panelId = useId();
  const open = draft.surface === surface;

  return (
    <>
      <FilterPill
        ref={anchorRef}
        open={open}
        aria-controls={open ? panelId : undefined}
        onClick={() => (open ? draft.discard() : draft.open(surface))}
      >
        {label}
      </FilterPill>
      <Popover
        open={open}
        onClose={draft.discard}
        anchorRef={anchorRef}
        label={label}
        id={panelId}
        className={className}
        footer={<ShowResultsButton count={draft.count} onClick={draft.apply} />}
      >
        {children}
      </Popover>
    </>
  );
}
