import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Header from './Header';
import LocationModal from '../../LocationModal';
import type { HeaderCategory } from './bars/CategoryBar';
import type { MenuSection } from './lib/menu-types';
import { toMenuSections } from './lib/menu-sections';
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

const toCategoryLink = (category: Category) => ({
  to: `/catalog/${category.slug}`,
  label: category.name,
});

export default function HeaderConnected() {
  const navigate = useNavigate();
  const { categories } = useCategoryTree();
  const { rooms } = useRoomTree();
  const { cartCount, wishlistCount, compareCount } = useCounters();
  const { isAuthenticated } = useAuth();
  const { region, locality } = useRegion();
  const [isCityModalOpen, setCityModalOpen] = useState(false);

  const categoryBar = useMemo<HeaderCategory[]>(
    // Только категории из админки: «Акция» и «Сток» теперь там же — market-docs/39
    () => categories.map(toCategoryLink),
    [categories],
  );

  const catalogSections = useMemo<MenuSection[]>(
    () => toMenuSections(categories, '/catalog'),
    [categories],
  );

  const roomsSections = useMemo<MenuSection[]>(() => toMenuSections(rooms, '/rooms'), [rooms]);

  return (
    <>
      <Header
        city={locality?.name ?? region?.name}
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
