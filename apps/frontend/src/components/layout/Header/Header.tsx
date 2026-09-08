import { useEffect, useState } from 'react';
import TopBar from './TopBar';
import MainBar from './MainBar';
import CategoryBar from './CategoryBar';
import HeaderMenu from './HeaderMenu';
import type { HeaderCategory } from './CategoryBar';
import type { MenuSection } from './menu-types';
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
 * Шапка сайта по макету: три полосы и два выпадающих меню.
 *
 * Пока **не подключена**: `RootLayout` продолжает показывать старую
 * `components/feature/Header.tsx`. Переключим, когда шапка будет готова целиком —
 * она общая для 32 страниц, менять её вслепую не стоит. Смотреть в `/__ui`.
 *
 * Замеры и что осталось невыверенным — market-docs/13-header.md.
 *
 * Данные приходят пропами: счётчики, город, категории и разделы меню компонент
 * сам не тянет. Подключение к `useCounters`, `RegionContext` и API категорий —
 * при переключении `RootLayout`, чтобы вёрстку можно было смотреть без бэкенда.
 *
 * Меню взаимоисключающие: открытие одного закрывает другое.
 */

/**
 * Откуда начинается меню. Числа складываются из замеренных высот полос:
 *
 *   116 = 36 (служебная) + 80 (основная)          — меню накрывает строку категорий
 *   151 = 36 + 80 + 35 (категории)                — ниже 980 строка остаётся видна
 *   116                                            — ниже 550 строки категорий нет
 *
 * Так в макете: на десктопе при открытом меню строки категорий не видно, а на
 * планшете она на месте.
 */
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

  // Какое меню показывать, пока идёт закрытие: содержимое должно дожить до конца
  // анимации, иначе панель схлопывается уже пустой.
  const [shownMenu, setShownMenu] = useState<Exclude<OpenMenu, null>>('catalog');

  // Пока false — панель `invisible`, то есть её ссылки не ловят фокус табом.
  // Снимаем на открытии, возвращаем по окончании анимации закрытия.
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    if (openMenu) {
      setShownMenu(openMenu);
      setMounted(true);
    }
  }, [openMenu]);

  const toggle = (menu: Exclude<OpenMenu, null>) =>
    setOpenMenu((current) => (current === menu ? null : menu));

  const close = () => setOpenMenu(null);

  const sections = shownMenu === 'catalog' ? catalogSections : roomsSections;

  return (
    <header className={cn('relative w-full', className)}>
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
          aria-hidden={!openMenu}
          onTransitionEnd={() => {
            if (!openMenu) setMounted(false);
          }}
          className={cn(
            // Меню накрывает страницу, а не раздвигает её: страница под ним
            // остаётся на месте, двигается только сама панель.
            'absolute inset-x-0 z-50 grid',
            MENU_TOP,
            // Раскрытие сверху вниз без магических чисел: анимируется строка
            // грида от 0fr до 1fr, то есть настоящая высота содержимого.
            'transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none',
            openMenu ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
            !openMenu && !mounted && 'invisible',
            !openMenu && 'pointer-events-none',
          )}
        >
          <div className="overflow-hidden">
            <HeaderMenu
              // key заставляет пересоздать меню при смене «Каталог» ↔ «Комнаты».
              // Без него переживал выбранный раздел от прошлого меню, его id в
              // новых разделах не находился, и правая панель открывалась пустой.
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
