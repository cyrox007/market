import { useState } from 'react';
import TopBar from './bars/TopBar';
import MainBar from './bars/MainBar';
import CategoryBar from './bars/CategoryBar';
import HeaderMenu from './menu/HeaderMenu';
import type { HeaderCategory } from './bars/CategoryBar';
import type { MenuSection } from './lib/menu-types';
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

  const toggle = (menu: Exclude<OpenMenu, null>) =>
    setOpenMenu((current) => (current === menu ? null : menu));

  const close = () => setOpenMenu(null);

  const sections = shownMenu === 'catalog' ? catalogSections : roomsSections;

  return (
    <header className={cn('sticky top-0 z-40 w-full bg-surface', className)}>
      <TopBar city={city} onCityClick={onCityClick} />
      <MainBar
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
          <div className="overflow-hidden">
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
