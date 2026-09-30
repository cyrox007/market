import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Header from './Header';
import LocationModal from '../../LocationModal';
import type { HeaderCategory } from './bars/CategoryBar';
import type { MenuLink, MenuSection } from './lib/menu-types';
import { useCategoryTree } from '../../../hooks/useCategoryTree';
import { useRoomTree } from '../../../hooks/useRoomTree';
import { useCounters } from '../../../hooks/useCounters';
import { useAuth } from '../../../hooks/useAuth';
import { useRegion } from '../../../hooks/useRegion';
import type { Category } from '../../../lib/api';

/**
 * Шапка, подключённая к данным. Сам `Header` остаётся без зависимостей, чтобы
 * его можно было смотреть в песочнице без бэкенда.
 *
 * Что откуда берётся и что осталось нерешённым — market-docs/14-header-wiring.md.
 */

/** Раздела распродажи в маршрутах нет, поэтому пункт неактивен */
const SALE_CATEGORY: HeaderCategory = {
  to: '#',
  label: 'Распродажа',
  accent: true,
  badge: 'до −60%',
  disabled: true,
};

/** «Сток» и «Акции» из макета: маршрутов под них тоже нет */
const CATALOG_EXTRA: MenuSection[] = [
  { id: 'stock', label: 'Сток', emphasized: true, disabled: true },
  { id: 'sale', label: 'Акции', emphasized: true, disabled: true },
];

const toCategoryLink = (category: Category) => ({
  to: `/catalog/${category.slug}`,
  label: category.name,
});

const toRoomLink = (room: Category): MenuLink => ({
  to: `/rooms/${room.slug}`,
  label: room.name,
  children: (room.children ?? []).map((child) => ({
    to: `/rooms/${child.slug}`,
    label: child.name,
  })),
});

export default function HeaderConnected() {
  const navigate = useNavigate();
  const { categories } = useCategoryTree();
  const { rooms } = useRoomTree();
  const { cartCount, wishlistCount, compareCount } = useCounters();
  const { isAuthenticated } = useAuth();
  const { region } = useRegion();
  const [isCityModalOpen, setCityModalOpen] = useState(false);

  const categoryBar = useMemo<HeaderCategory[]>(
    () => [SALE_CATEGORY, ...categories.map(toCategoryLink)],
    [categories],
  );

  const catalogSections = useMemo<MenuSection[]>(
    () => [
      ...CATALOG_EXTRA,
      ...categories.map((category) => {
        const children = category.children ?? [];
        return children.length
          ? {
              id: category.slug,
              label: category.name,
              groups: [{ links: children.map(toCategoryLink) }],
            }
          : { id: category.slug, label: category.name, to: `/catalog/${category.slug}` };
      }),
    ],
    [categories],
  );

  const roomsSections = useMemo<MenuSection[]>(
    () =>
      rooms.map((room) => {
        const children = room.children ?? [];
        if (!children.length) {
          return { id: room.slug, label: room.name, to: `/rooms/${room.slug}` };
        }
        // Три уровня: подкомнаты с детьми — колонки с заголовком (их дети = ссылки);
        // подкомнаты-листья — одной колонкой без заголовка.
        const branches = children.filter((c) => (c.children ?? []).length);
        const leaves = children.filter((c) => !(c.children ?? []).length);
        const groups = branches.map((child) => ({
          title: child.name,
          titleTo: `/rooms/${child.slug}`,
          links: (child.children ?? []).map(toRoomLink),
        }));
        if (leaves.length) {
          groups.push({ title: undefined, links: leaves.map(toRoomLink) });
        }
        return { id: room.slug, label: room.name, groups };
      }),
    [rooms],
  );

  return (
    <>
      <Header
        city={region?.name}
        onCityClick={() => setCityModalOpen(true)}
        categories={categoryBar}
        catalogSections={catalogSections}
        roomsSections={roomsSections}
        onSearch={(query) => {
          const trimmed = query.trim();
          if (trimmed) navigate(`/search?q=${encodeURIComponent(trimmed)}`);
        }}
        compareCount={compareCount}
        wishlistCount={wishlistCount}
        cartCount={cartCount}
        isAuthenticated={isAuthenticated}
      />

      <LocationModal isOpen={isCityModalOpen} onClose={() => setCityModalOpen(false)} />
    </>
  );
}
