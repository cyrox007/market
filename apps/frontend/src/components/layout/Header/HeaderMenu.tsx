import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronDown, ChevronRight } from 'lucide-react';
import type { MenuGroup, MenuSection } from './menu-types';
import { HEADER_CONTAINER } from './container';
import { useMediaQuery } from '../../../hooks/useMediaQuery';
import { cn } from '../../../lib/cn';

interface HeaderMenuProps {
  sections: MenuSection[];
  /** Название меню для читалки: «Каталог» или «Комнаты» */
  label: string;
  onClose?: () => void;
  className?: string;
}

/**
 * Выпадающее меню шапки. Один компонент на оба: «Каталог» и «Комнаты» —
 * у них одинаковое устройство и разные данные.
 *
 * Замерено с макета 07.09.2026:
 *   левая колонка   300
 *   заголовок       20
 *   ссылки справа   14
 *   левая колонка   15 и 14
 *   высота          652 у каталога, 508 у комнат — считается содержимым
 *
 * Три состояния:
 *   ≥ 980   две колонки: список слева, раскрытый раздел справа
 *   ≤ 979   те же две колонки, левая уже, справа два столбца вместо трёх-четырёх
 *   ≤ 549   одна колонка: список превращается в гармошку, раздел раскрывается
 *           прямо под своим пунктом
 *
 * ⚠️ Не замерено: отступы внутри меню, высота строки списка, отступ правой панели
 * от разделителя (стоит 32), ширина левой колонки ниже 980 (стоит 230).
 */
export default function HeaderMenu({ sections, label, onClose, className }: HeaderMenuProps) {
  const firstExpandable = sections.find((section) => section.groups?.length);
  const [activeId, setActiveId] = useState(firstExpandable?.id ?? sections[0]?.id);

  // Гармошка вместо двух колонок. Хук безопасен: меню открывается по клику,
  // то есть к первому показу значение уже уточнено и мигания не будет.
  const isAccordion = useMediaQuery('(max-width: 549.98px)');

  useEffect(() => {
    const handleKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose?.();
    };
    document.addEventListener('keydown', handleKey);
    return () => document.removeEventListener('keydown', handleKey);
  }, [onClose]);

  const active = sections.find((section) => section.id === activeId);

  return (
    <div
      className={cn('w-full border-t border-surface-border bg-surface shadow-card', className)}
      role="region"
      aria-label={label}
    >
      <div className={cn(HEADER_CONTAINER, isAccordion ? 'py-4' : 'flex gap-8 py-6')}>
        <ul
          className={cn(
            'flex flex-col gap-1',
            isAccordion ? 'w-full' : 'w-[300px] shrink-0 border-r border-surface-border pr-4',
            !isAccordion && 'max-md:w-[230px]',
          )}
        >
          {sections.map((section) => {
            const expandable = Boolean(section.groups?.length);
            const isActive = section.id === activeId;

            return (
              <li key={section.id}>
                {section.to && !expandable ? (
                  <Link
                    to={section.to}
                    onClick={onClose}
                    className={cn(
                      'block rounded-btn px-3 py-2 outline-none hover:bg-surface-grey',
                      section.emphasized ? 'text-15 font-semibold text-ink' : 'text-14 text-ink',
                    )}
                  >
                    {section.label}
                  </Link>
                ) : (
                  <button
                    type="button"
                    onClick={() => setActiveId(isActive && isAccordion ? '' : section.id)}
                    aria-expanded={isAccordion ? isActive : undefined}
                    className={cn(
                      'flex w-full items-center justify-between gap-2 rounded-btn px-3 py-2 text-left outline-none',
                      'hover:bg-surface-grey focus-visible:bg-surface-grey',
                      isActive && !isAccordion && 'bg-surface-grey',
                      section.emphasized ? 'text-15 font-semibold text-ink' : 'text-14 text-ink',
                    )}
                  >
                    {section.label}
                    {isAccordion && isActive ? (
                      <ChevronDown className="size-4 shrink-0 text-ink-secondary" />
                    ) : (
                      <ChevronRight className="size-4 shrink-0 text-ink-secondary" />
                    )}
                  </button>
                )}

                {/* В гармошке раздел раскрывается прямо под своим пунктом */}
                {isAccordion && isActive && section.groups?.length ? (
                  <MenuPanelGroups groups={section.groups} onNavigate={onClose} inset />
                ) : null}
              </li>
            );
          })}
        </ul>

        {!isAccordion && active?.groups?.length ? (
          <div className="min-w-0 flex-1">
            <h2 className="mb-6 text-20 font-semibold text-ink">
              {active.heading ?? active.label}
            </h2>
            <MenuPanelGroups groups={active.groups} onNavigate={onClose} />
            {active.promo}
          </div>
        ) : null}
      </div>
    </div>
  );
}

/**
 * Колонки со ссылками. У каталога это одна группа без заголовка, и ссылки просто
 * текут в три столбца. У «Комнат» групп несколько, у каждой свой заголовок, и они
 * раскладываются сеткой.
 */
function MenuPanelGroups({
  groups,
  onNavigate,
  inset = false,
}: {
  groups: MenuGroup[];
  onNavigate?: () => void;
  inset?: boolean;
}) {
  const flowing = groups.length === 1 && !groups[0].title;

  const link = (to: string, label: string) => (
    <Link
      key={to}
      to={to}
      onClick={onNavigate}
      className="block py-1.5 text-14 text-ink-secondary outline-none hover:text-ink focus-visible:text-ink"
    >
      {label}
    </Link>
  );

  if (flowing) {
    return (
      <div className={cn(inset ? 'pl-3' : 'columns-3 gap-8 max-md:columns-2')}>
        {groups[0].links.map(({ to, label }) => link(to, label))}
      </div>
    );
  }

  return (
    <div className={cn(inset ? 'pl-3' : 'grid grid-cols-4 gap-8 max-md:grid-cols-2')}>
      {groups.map((group) => (
        <div key={group.title ?? 'group'}>
          {group.title && <h3 className="mb-2 text-14 font-semibold text-ink">{group.title}</h3>}
          {group.links.map(({ to, label }) => link(to, label))}
        </div>
      ))}
    </div>
  );
}
