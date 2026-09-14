import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronDown, ChevronRight } from 'lucide-react';
import type { MenuGroup, MenuLink, MenuSection } from '../lib/menu-types';
import { DISABLED_LINK } from '../lib/container';
import { PAGE_CONTAINER } from '../../../../lib/layout';
import { useMediaQuery } from '../../../../hooks/useMediaQuery';
import { cn } from '../../../../lib/cn';

interface HeaderMenuProps {
  sections: MenuSection[];
  /** Название меню для читалки: «Каталог» или «Комнаты» */
  label: string;
  onClose?: () => void;
  className?: string;
}

/**
 * Выпадающее меню шапки, одно на «Каталог» и «Комнаты».
 * Замеры, три состояния и открытые вопросы — market-docs/13-header.md.
 */
export default function HeaderMenu({ sections, label, onClose, className }: HeaderMenuProps) {
  const firstExpandable = sections.find((section) => section.groups?.length);
  const [activeId, setActiveId] = useState(firstExpandable?.id ?? sections[0]?.id);

  // Хук, а не CSS: раскладки слишком разные. Мигания нет — меню закрыто до клика
  const isAccordion = useMediaQuery('(max-width: 549.98px)');

  useEffect(() => {
    const handleKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose?.();
    };
    document.addEventListener('keydown', handleKey);
    return () => document.removeEventListener('keydown', handleKey);
  }, [onClose]);

  const explicit = sections.find((section) => section.id === activeId);

  // Разделы приезжают асинхронно: на первом рендере их ещё нет, и выбранным
  // оказывается пункт без содержимого. Поэтому падаем на первый раскрываемый.
  const active = isAccordion || explicit?.groups?.length ? explicit : firstExpandable;

  return (
    <div
      className={cn(
        'w-full border-t border-surface-border bg-surface shadow-card',
        // Шапка липкая: те же отступы сверху, что в Header, иначе меню уедет за низ
        'max-h-[calc(100dvh-116px)] overflow-y-auto max-md:max-h-[calc(100dvh-151px)] max-vsm:max-h-[calc(100dvh-116px)]',
        className,
      )}
      role="region"
      aria-label={label}
    >
      <div className={cn(PAGE_CONTAINER, isAccordion ? 'py-4' : 'flex gap-8 py-6')}>
        <ul
          className={cn(
            'flex flex-col gap-1',
            isAccordion ? 'w-full' : 'w-[300px] shrink-0 border-r border-surface-border pr-4',
            !isAccordion && 'max-md:w-[230px]',
          )}
        >
          {sections.map((section) => {
            const expandable = Boolean(section.groups?.length);
            const isActive = isAccordion ? section.id === activeId : section.id === active?.id;

            return (
              <li key={section.id}>
                {section.disabled ? (
                  <span
                    aria-disabled="true"
                    className={cn(
                      'block rounded-btn px-3 py-2',
                      DISABLED_LINK,
                      section.emphasized ? 'text-15 font-semibold' : 'text-14',
                    )}
                  >
                    {section.label}
                  </span>
                ) : section.to && !expandable ? (
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

  // Ключ по адресу и подписи: у неактивных ссылок адреса нет, все они «#»
  const link = ({ to, label, disabled, children }: MenuLink) => (
    <div key={`${to}:${label}`}>
      {disabled ? (
        <span aria-disabled="true" className={cn('block py-1.5 text-14', DISABLED_LINK)}>
          {label}
        </span>
      ) : (
        <Link
          to={to}
          onClick={onNavigate}
          className="block py-1.5 text-14 text-ink-secondary outline-none hover:text-ink focus-visible:text-ink"
        >
          {label}
        </Link>
      )}
      {children?.length ? (
        <div className="ml-3">
          {children.map((child) => (
            <Link
              key={`${child.to}:${child.label}`}
              to={child.to}
              onClick={onNavigate}
              className="block py-1 text-12 text-ink-secondary/80 outline-none hover:text-ink focus-visible:text-ink"
            >
              {child.label}
            </Link>
          ))}
        </div>
      ) : null}
    </div>
  );

  if (flowing) {
    return (
      <div className={cn(inset ? 'pl-3' : 'columns-3 gap-8 max-md:columns-2')}>
        {groups[0].links.map(link)}
      </div>
    );
  }

  return (
    <div className={cn(inset ? 'pl-3' : 'grid grid-cols-4 gap-8 max-md:grid-cols-2')}>
      {groups.map((group) => (
        <div key={group.titleTo ?? group.title ?? 'leaves'}>
          {group.title &&
            (group.titleTo ? (
              <h3 className="mb-2 text-14 font-semibold text-ink">
                <Link to={group.titleTo} className="hover:text-primary">
                  {group.title}
                </Link>
              </h3>
            ) : (
              <h3 className="mb-2 text-14 font-semibold text-ink">{group.title}</h3>
            ))}
          {group.links.map(link)}
        </div>
      ))}
    </div>
  );
}
