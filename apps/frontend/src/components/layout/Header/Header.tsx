import { useEffect, useRef, useState } from 'react';
import TopBar from './bars/TopBar';
import MainBar from './bars/MainBar';
import CategoryBar from './bars/CategoryBar';
import HeaderMenu from './menu/HeaderMenu';
import type { HeaderCategory } from './bars/CategoryBar';
import type { MenuSection } from './lib/menu-types';
import { useMediaQuery } from '../../../hooks/useMediaQuery';
import { cn } from '../../../lib/cn';

type OpenMenu = 'catalog' | 'rooms' | null;

interface HeaderProps {
  city?: string;
  onCityClick?: () => void;
  categories?: HeaderCategory[];
  /** Разделы выпадающего «Каталога» */
  catalogSections?: MenuSection[];
  /** Разделы выпадающих «Комнат» */
  roomsSections?: MenuSection[];
  onSearch?: (query: string) => void;
  compareCount?: number;
  wishlistCount?: number;
  cartCount?: number;
  isAuthenticated?: boolean;
  className?: string;
}

/**
 * Шапка по макету. Подключена к `RootLayout` через `HeaderConnected`.
 * Замеры, адаптив и открытые вопросы — market-docs/13-header.md.
 */

/** Сумма высот полос над меню: 36 + 80, ниже 980 плюс строка категорий 35 */
const MENU_TOP = 'top-[116px] max-md:top-[151px] max-vsm:top-[116px]';

/** Связывает кнопки с панелью через aria-controls; панель одна на оба меню */
const MENU_ID = 'header-menu';

export default function Header({
  city,
  onCityClick,
  categories,
  catalogSections,
  roomsSections,
  onSearch,
  compareCount,
  wishlistCount,
  cartCount,
  isAuthenticated,
  className,
}: HeaderProps) {
  const [openMenu, setOpenMenu] = useState<OpenMenu>(null);

  // Содержимое должно дожить до конца анимации закрытия
  const [shownMenu, setShownMenu] = useState<Exclude<OpenMenu, null>>('catalog');

  // Правка в рендере, а не в эффекте: иначе при смене меню мелькает кадр
  // со старым содержимым, а useLayoutEffect ругается при серверном рендеринге
  if (openMenu && openMenu !== shownMenu) setShownMenu(openMenu);

  const panelRef = useRef<HTMLDivElement>(null);
  // Кто открыл меню — туда возвращаем фокус при закрытии
  const openerRef = useRef<HTMLElement | null>(null);

  const toggle = (menu: Exclude<OpenMenu, null>) =>
    setOpenMenu((current) => (current === menu ? null : menu));

  const close = () => {
    setOpenMenu(null);
    openerRef.current?.focus();
  };

  useEffect(() => {
    if (!openMenu) return;

    openerRef.current = document.activeElement as HTMLElement | null;
    // Без preventScroll браузер подкручивает страницу к панели
    panelRef.current?.focus({ preventScroll: true });

    // Слушатель живёт только пока меню открыто, а не всё время жизни шапки
    const handleKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') close();
    };
    // Касание мимо меню закрывает его. Кнопки «Каталог» / «Комнаты» переключают сами
    const handlePointerDown = (event: PointerEvent) => {
      const target = event.target as Element | null;
      if (!target || panelRef.current?.contains(target)) return;
      if (target.closest(`[aria-controls="${MENU_ID}"]`)) return;
      setOpenMenu(null);
    };

    document.addEventListener('keydown', handleKey);
    document.addEventListener('pointerdown', handlePointerDown);
    return () => {
      document.removeEventListener('keydown', handleKey);
      document.removeEventListener('pointerdown', handlePointerDown);
    };
  }, [openMenu]);

  // На телефоне меню на весь экран — страница под ним не листается. На шире — листается
  const isPhone = useMediaQuery('(max-width: 549.98px)');
  useEffect(() => {
    if (!openMenu || !isPhone) return;
    // overflow на <html>, не position: fixed на body — тот увёл бы липкую шапку за экран
    const root = document.documentElement;
    const previous = root.style.overflow;
    root.style.overflow = 'hidden';
    return () => {
      root.style.overflow = previous;
    };
  }, [openMenu, isPhone]);

  const sections = shownMenu === 'catalog' ? catalogSections : roomsSections;

  return (
    // translateZ(0): своя композиционная плоскость. Без неё iOS Safari не перерисовывает
    // иконки кнопок в липкой шапке, пока не прокрутишь страницу — market-docs/37
    <header className={cn('sticky top-0 z-40 w-full bg-surface [transform:translateZ(0)]', className)}>
      <TopBar city={city} onCityClick={onCityClick} />
      <MainBar
        menuId={MENU_ID}
        catalogOpen={openMenu === 'catalog'}
        roomsOpen={openMenu === 'rooms'}
        onCatalogToggle={() => toggle('catalog')}
        onRoomsToggle={() => toggle('rooms')}
        onSearch={onSearch}
        compareCount={compareCount}
        wishlistCount={wishlistCount}
        cartCount={cartCount}
        isAuthenticated={isAuthenticated}
      />
      <CategoryBar categories={categories} />

      {sections?.length ? (
        <div
          id={MENU_ID}
          // Закрытая панель выпадает из табуляции, кликов и дерева доступности.
          // Раньше это делал `invisible` по концу анимации — market-docs/16-код-ревью.md
          inert={!openMenu}
          className={cn(
            'absolute inset-x-0 z-50 grid',
            MENU_TOP,
            // 0fr → 1fr даёт настоящую высоту содержимого без max-height
            'transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none',
            openMenu ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
          )}
        >
          {/* tabIndex=-1: сюда уводится фокус при открытии, в табуляцию не встаёт */}
          <div ref={panelRef} tabIndex={-1} className="overflow-hidden outline-none">
            <HeaderMenu
              // Без key выбранный раздел переживает смену меню и панель пустеет
              key={shownMenu}
              sections={sections}
              label={shownMenu === 'catalog' ? 'Каталог' : 'Комнаты'}
              onClose={close}
            />
          </div>
        </div>
      ) : null}
    </header>
  );
}
